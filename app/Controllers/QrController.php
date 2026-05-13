<?php
declare(strict_types=1);

class QrController
{
    // ── QR list ───────────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        $stmt = Database::get()->prepare("
            SELECT
                qr.id,
                qr.name,
                qr.created_at,
                sl.slug,
                sl.current_target_url,
                sl.status
            FROM qr_codes    AS qr
            JOIN short_links AS sl ON sl.id = qr.short_link_id
            WHERE qr.user_id = ?
            ORDER BY qr.created_at DESC
        ");
        $stmt->execute([$userId]);
        $qrCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('qr/index', [
            'pageTitle' => 'My QR Codes — f29.us Dynamic QR',
            'qrCodes'   => $qrCodes,
            'baseUrl'   => $this->qrBaseUrl(),
        ]);
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function createPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        if (!EntitlementService::isEnabled($userId, 'can_create_qr')) {
            $this->forbidden('Your plan does not allow QR code creation.');
        }

        $maxQr       = (int) EntitlementService::getValue($userId, 'max_qr_codes', 0);
        $limitReached = $this->countUserQrCodes($userId) >= $maxQr;

        View::render('qr/create', [
            'pageTitle'     => 'Create QR Code — f29.us Dynamic QR',
            'errors'        => [],
            'oldValues'     => [],
            'canCustomSlug' => EntitlementService::isEnabled($userId, 'can_use_custom_slug'),
            'limitReached'  => $limitReached,
            'maxQr'         => $maxQr,
        ]);
    }

    // ── Create submit ─────────────────────────────────────────────────────────

    public function createSubmit(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        // Entitlement gates
        if (!EntitlementService::isEnabled($userId, 'can_create_qr')) {
            $this->forbidden('Your plan does not allow QR code creation.');
        }

        $maxQr = (int) EntitlementService::getValue($userId, 'max_qr_codes', 0);
        if ($this->countUserQrCodes($userId) >= $maxQr) {
            $this->forbidden("You have reached the {$maxQr} QR code limit for your plan.");
        }

        $name        = trim($_POST['name']            ?? '');
        $destUrl     = trim($_POST['destination_url'] ?? '');
        $customSlug  = trim($_POST['custom_slug']     ?? '');
        $canCustom   = EntitlementService::isEnabled($userId, 'can_use_custom_slug');

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if ($destUrl === '') {
            $errors[] = 'Destination URL is required.';
        } elseif (!$this->isValidUrl($destUrl)) {
            $errors[] = 'Destination URL must be a valid http or https URL.';
        }

        // Slug resolution
        $resolvedSlug = null;
        if ($customSlug !== '') {
            $slugResult = SlugService::validateCustomSlugForUser($userId, $customSlug);
            if (!$slugResult['valid']) {
                $errors = array_merge($errors, $slugResult['errors']);
            } else {
                $resolvedSlug = $slugResult['slug'];
            }
        }

        if (!empty($errors)) {
            View::render('qr/create', [
                'pageTitle'     => 'Create QR Code — f29.us Dynamic QR',
                'errors'        => $errors,
                'oldValues'     => compact('name', 'destUrl', 'customSlug'),
                'canCustomSlug' => $canCustom,
                'limitReached'  => false,
                'maxQr'         => $maxQr,
            ]);
            return;
        }

        // Auto-generate slug when none was provided
        if ($resolvedSlug === null) {
            $resolvedSlug = SlugService::generateUniqueSlug();
        }

        // Transaction: short_link + qr_code + audit_logs
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            $now = gmdate('Y-m-d H:i:s');

            $pdo->prepare("
                INSERT INTO short_links
                    (user_id, slug, current_target_url, status, created_at, updated_at)
                VALUES (?, ?, ?, 'active', ?, ?)
            ")->execute([$userId, $resolvedSlug, $destUrl, $now, $now]);

            $shortLinkId = (int) $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO qr_codes
                    (user_id, short_link_id, name, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$userId, $shortLinkId, $name, $now, $now]);

            $qrCodeId = (int) $pdo->lastInsertId();

            AuditLogService::log($userId, 'short_link', $shortLinkId, 'created', [
                'slug' => $resolvedSlug,
            ]);
            AuditLogService::log($userId, 'qr_code', $qrCodeId, 'created', [
                'name' => $name,
                'slug' => $resolvedSlug,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Slug uniqueness race condition
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                View::render('qr/create', [
                    'pageTitle'     => 'Create QR Code — f29.us Dynamic QR',
                    'errors'        => ['That slug was just taken. Please try a different one or leave it blank for auto-generation.'],
                    'oldValues'     => compact('name', 'destUrl', 'customSlug'),
                    'canCustomSlug' => $canCustom,
                    'limitReached'  => false,
                    'maxQr'         => $maxQr,
                ]);
                return;
            }
            throw $e;
        }

        redirect('/qr/' . $qrCodeId);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function detail(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        View::render('qr/detail', [
            'pageTitle'          => View::e($qr['name']) . ' — f29.us Dynamic QR',
            'qr'                 => $qr,
            'shortUrl'           => $this->qrBaseUrl() . '/' . $qr['slug'],
            'canEditDestination' => EntitlementService::isEnabled($userId, 'can_edit_destination'),
            'canPauseLinks'      => EntitlementService::isEnabled($userId, 'can_pause_links'),
            'canExportPng'       => EntitlementService::isEnabled($userId, 'can_export_png'),
            'canExportSvg'       => EntitlementService::isEnabled($userId, 'can_export_svg'),
        ]);
    }

    // ── Edit destination form ─────────────────────────────────────────────────

    public function editPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_edit_destination')) {
            $this->forbidden('Editing the destination URL is not available on your plan.');
        }

        View::render('qr/edit', [
            'pageTitle' => 'Edit Destination — f29.us Dynamic QR',
            'qr'        => $qr,
            'errors'    => [],
            'oldUrl'    => $qr['current_target_url'],
        ]);
    }

    // ── Update destination ────────────────────────────────────────────────────

    public function updateDestination(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_edit_destination')) {
            $this->forbidden('Editing the destination URL is not available on your plan.');
        }

        $newUrl = trim($_POST['destination_url'] ?? '');
        $errors = [];

        if ($newUrl === '') {
            $errors[] = 'Destination URL is required.';
        } elseif (!$this->isValidUrl($newUrl)) {
            $errors[] = 'Destination URL must be a valid http or https URL.';
        }

        if (!empty($errors)) {
            View::render('qr/edit', [
                'pageTitle' => 'Edit Destination — f29.us Dynamic QR',
                'qr'        => $qr,
                'errors'    => $errors,
                'oldUrl'    => $newUrl,
            ]);
            return;
        }

        $oldUrl = $qr['current_target_url'];
        $pdo    = Database::get();
        $now    = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE short_links
                SET current_target_url = ?, updated_at = ?
                WHERE id = ?
            ")->execute([$newUrl, $now, $qr['short_link_id']]);

            AuditLogService::log($userId, 'short_link', (int) $qr['short_link_id'], 'destination_updated', [
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        redirect('/qr/' . $qrId);
    }

    // ── Pause ─────────────────────────────────────────────────────────────────

    public function pause(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_pause_links')) {
            $this->forbidden('Pausing links is not available on your plan.');
        }

        if ($qr['status'] !== 'active') {
            redirect('/qr/' . $qrId);
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE short_links SET status = 'paused', updated_at = ? WHERE id = ?"
            )->execute([$now, $qr['short_link_id']]);

            AuditLogService::log($userId, 'short_link', (int) $qr['short_link_id'], 'paused', [
                'old_status' => 'active',
                'new_status' => 'paused',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        redirect('/qr/' . $qrId);
    }

    // ── Resume ────────────────────────────────────────────────────────────────

    public function resume(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_pause_links')) {
            $this->forbidden('Resuming links is not available on your plan.');
        }

        if ($qr['status'] !== 'paused') {
            redirect('/qr/' . $qrId);
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE short_links SET status = 'active', updated_at = ? WHERE id = ?"
            )->execute([$now, $qr['short_link_id']]);

            AuditLogService::log($userId, 'short_link', (int) $qr['short_link_id'], 'resumed', [
                'old_status' => 'paused',
                'new_status' => 'active',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        redirect('/qr/' . $qrId);
    }

    // ── Downloads ─────────────────────────────────────────────────────────────

    public function downloadPng(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_export_png')) {
            $this->forbidden('PNG export is not available on your plan.');
        }

        $data     = QrCodeService::generatePng($this->qrBaseUrl() . '/' . $qr['slug']);
        $filename = $this->safeFilename($qr['name'], $qr['slug']) . '.png';

        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    public function downloadSvg(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_export_svg')) {
            $this->forbidden('SVG export is not available on your plan.');
        }

        $data     = QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug']);
        $filename = $this->safeFilename($qr['name'], $qr['slug']) . '.svg';

        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Load a QR code (joined with its short link) that belongs to $userId.
     * Outputs a 404 and exits if the row doesn't exist or isn't owned by this user.
     */
    private function loadOwnedQrCode(int $qrId, int $userId): array
    {
        if ($qrId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare("
            SELECT
                qr.id,
                qr.name,
                qr.created_at,
                qr.updated_at             AS qr_updated_at,
                sl.id                     AS short_link_id,
                sl.slug,
                sl.current_target_url,
                sl.status,
                sl.updated_at             AS sl_updated_at
            FROM qr_codes    AS qr
            JOIN short_links AS sl ON sl.id = qr.short_link_id
            WHERE qr.id = ? AND qr.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$qrId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->notFound();
        }

        return $row;
    }

    private function countUserQrCodes(int $userId): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM qr_codes WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    private function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * The base URL that gets encoded into QR images.
     * Always uses QR_BASE_URL (production domain) so downloaded QR files
     * point to the live short-link service, not the dev server.
     */
    private function qrBaseUrl(): string
    {
        return rtrim($_ENV['QR_BASE_URL'] ?? 'https://f29.us', '/');
    }

    /** Produce a safe ASCII filename from the QR name + slug. */
    private function safeFilename(string $name, string $slug): string
    {
        $safe = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $safe = trim($safe, '-');
        return ($safe !== '' ? $safe . '-' : '') . $slug;
    }

    private function forbidden(string $message): never
    {
        http_response_code(403);
        View::render('errors/forbidden', [
            'pageTitle' => '403 — Access Denied',
            'message'   => $message,
        ]);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
        exit;
    }
}
