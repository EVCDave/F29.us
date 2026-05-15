<?php
declare(strict_types=1);

class QrController
{
    // ── QR list ───────────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $validStatuses = ['active', 'paused', 'disabled', 'archived', ''];
        if (!in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $where = ['qr.user_id = ?'];
        $args  = [$userId];

        if ($status !== '') {
            $where[] = 'sl.status = ?';
            $args[]  = $status;
        }
        if ($search !== '') {
            $where[] = '(qr.name LIKE ? OR sl.slug LIKE ? OR sl.current_target_url LIKE ?)';
            $args[]  = '%' . $search . '%';
            $args[]  = '%' . $search . '%';
            $args[]  = '%' . $search . '%';
        }

        $sql = "
            SELECT qr.id, qr.name, qr.created_at,
                   sl.slug, sl.current_target_url, sl.status
            FROM   qr_codes    AS qr
            JOIN   short_links AS sl ON sl.id = qr.short_link_id
            WHERE  " . implode(' AND ', $where) . "
            ORDER  BY qr.created_at DESC
            LIMIT  100
        ";

        $stmt = Database::get()->prepare($sql);
        $stmt->execute($args);
        $qrCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('qr/index', [
            'pageTitle' => 'My QR Codes — f29.us Dynamic QR',
            'qrCodes'   => $qrCodes,
            'baseUrl'   => $this->qrBaseUrl(),
            'search'    => $search,
            'status'    => $status,
            'flash'     => $flash,
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

        $maxQr        = QrQuotaService::maxForUser($userId);
        $limitReached = !QrQuotaService::canCreateForUser($userId);

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
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        EmailVerificationService::requireVerifiedEmail($userId);

        if (!EntitlementService::isEnabled($userId, 'can_create_qr')) {
            $this->forbidden('Your plan does not allow QR code creation.');
        }

        $maxQr = QrQuotaService::maxForUser($userId);
        if (!QrQuotaService::canCreateForUser($userId)) {
            $this->forbidden('You have reached your plan limit for active QR codes. Archive an existing QR code or upgrade your plan to create more.');
        }

        $name       = trim($_POST['name']            ?? '');
        $destUrl    = trim($_POST['destination_url'] ?? '');
        $customSlug = trim($_POST['custom_slug']     ?? '');
        $canCustom  = EntitlementService::isEnabled($userId, 'can_use_custom_slug');

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 200) {
            $errors[] = 'Name must be 200 characters or fewer.';
        }

        if ($destUrl === '') {
            $errors[] = 'Destination URL is required.';
        } elseif (strlen($destUrl) > 2048) {
            $errors[] = 'Destination URL must be 2048 characters or fewer.';
        } elseif (!$this->isValidUrl($destUrl)) {
            $errors[] = 'Destination URL must be a valid http or https URL.';
        } elseif (DomainBlocklistService::isBlockedUrl($destUrl)['blocked']) {
            $errors[] = 'This destination domain is not allowed.';
        }

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

        if ($resolvedSlug === null) {
            $resolvedSlug = SlugService::generateUniqueSlug();
        }

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

            DestinationHistoryService::recordInitial($pdo, $shortLinkId, $destUrl, $userId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'QR code created successfully.'];
        redirect('/qr/' . $qrCodeId);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function detail(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $style = $this->resolveRenderStyle(QrStyleService::getForQr((int) $qr['id']), $userId);

        // SVG preview is always generated for in-app display (not an export)
        $qrPreviewSvg = null;
        try {
            $qrPreviewSvg = base64_encode(
                QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug'], 300, $style)
            );
        } catch (Throwable) {
            // Preview is best-effort; missing preview does not fail the page
        }

        $pdo = Database::get();
        $destinationHistory = DestinationHistoryService::fetchForShortLink($pdo, (int) $qr['short_link_id']);

        $atQuotaLimit = $qr['status'] === 'archived' && !QrQuotaService::canRestoreForUser($userId);

        View::render('qr/detail', [
            'pageTitle'          => View::e($qr['name']) . ' — f29.us Dynamic QR',
            'qr'                 => $qr,
            'shortUrl'           => $this->qrBaseUrl() . '/' . $qr['slug'],
            'canEditDestination' => EntitlementService::isEnabled($userId, 'can_edit_destination'),
            'canPauseLinks'      => EntitlementService::isEnabled($userId, 'can_pause_links'),
            'canExportPng'       => EntitlementService::isEnabled($userId, 'can_export_png'),
            'canExportSvg'       => EntitlementService::isEnabled($userId, 'can_export_svg'),
            'canCustomizeQr'     => EntitlementService::isEnabled($userId, 'can_customize_qr_colors'),
            'qrPreviewSvg'       => $qrPreviewSvg,
            'flash'              => $flash,
            'destinationHistory' => $destinationHistory,
            'atQuotaLimit'       => $atQuotaLimit,
        ]);
    }

    // ── Edit form ─────────────────────────────────────────────────────────────

    public function editPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        View::render('qr/edit', [
            'pageTitle'          => 'Edit QR Code — f29.us Dynamic QR',
            'qr'                 => $qr,
            'errors'             => [],
            'oldName'            => $qr['name'],
            'oldUrl'             => $qr['current_target_url'],
            'canEditDestination' => EntitlementService::isEnabled($userId, 'can_edit_destination'),
        ]);
    }

    // ── Update (name + destination) ───────────────────────────────────────────

    public function update(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr          = $this->loadOwnedQrCode($qrId, $userId);
        $canEditDest = EntitlementService::isEnabled($userId, 'can_edit_destination');

        if ($canEditDest) {
            EmailVerificationService::requireVerifiedEmail($userId);
        }

        $newName = trim($_POST['name']            ?? '');
        $newUrl  = trim($_POST['destination_url'] ?? '');
        $errors  = [];

        if ($newName === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($newName) > 200) {
            $errors[] = 'Name must be 200 characters or fewer.';
        }

        if ($canEditDest) {
            if ($newUrl === '') {
                $errors[] = 'Destination URL is required.';
            } elseif (strlen($newUrl) > 2048) {
                $errors[] = 'Destination URL must be 2048 characters or fewer.';
            } elseif (!$this->isValidUrl($newUrl)) {
                $errors[] = 'Destination URL must be a valid http or https URL.';
            } elseif (DomainBlocklistService::isBlockedUrl($newUrl)['blocked']) {
                $errors[] = 'This destination domain is not allowed.';
            }
        }

        if (!empty($errors)) {
            View::render('qr/edit', [
                'pageTitle'          => 'Edit QR Code — f29.us Dynamic QR',
                'qr'                 => $qr,
                'errors'             => $errors,
                'oldName'            => $newName,
                'oldUrl'             => $canEditDest ? $newUrl : $qr['current_target_url'],
                'canEditDestination' => $canEditDest,
            ]);
            return;
        }

        $nameChanged = $newName !== $qr['name'];
        $urlChanged  = $canEditDest && $newUrl !== $qr['current_target_url'];

        if (!$nameChanged && !$urlChanged) {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'No changes were made.'];
            redirect('/qr/' . $qrId);
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            if ($nameChanged) {
                $pdo->prepare(
                    "UPDATE qr_codes SET name = ?, updated_at = ? WHERE id = ?"
                )->execute([$newName, $now, $qrId]);
            }

            if ($urlChanged) {
                $pdo->prepare(
                    "UPDATE short_links SET current_target_url = ?, updated_at = ? WHERE id = ?"
                )->execute([$newUrl, $now, $qr['short_link_id']]);

                DestinationHistoryService::record(
                    $pdo, (int) $qr['short_link_id'], $userId,
                    $qr['current_target_url'], $newUrl, 'user_edit'
                );
            }

            $meta = [];
            if ($nameChanged) {
                $meta['old_name'] = $qr['name'];
                $meta['new_name'] = $newName;
            }
            if ($urlChanged) {
                $meta['old_url'] = $qr['current_target_url'];
                $meta['new_url'] = $newUrl;
            }
            AuditLogService::log($userId, 'qr_code', $qrId, 'updated', $meta);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'QR code updated successfully.'];
        redirect('/qr/' . $qrId);
    }

    // ── Pause ─────────────────────────────────────────────────────────────────

    public function pause(array $params = []): void
    {
        CsrfService::requireValid();
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

        $_SESSION['flash'] = ['type' => 'info', 'text' => 'This QR code has been paused.'];
        redirect('/qr/' . $qrId);
    }

    // ── Resume ────────────────────────────────────────────────────────────────

    public function resume(array $params = []): void
    {
        CsrfService::requireValid();
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

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'This QR code is active again.'];
        redirect('/qr/' . $qrId);
    }

    // ── Archive ───────────────────────────────────────────────────────────────

    public function archive(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        // Only allow archiving active or paused links; disabled is admin-managed
        if (!in_array($qr['status'], ['active', 'paused'], true)) {
            redirect('/qr/' . $qrId);
        }

        $pdo       = Database::get();
        $now       = gmdate('Y-m-d H:i:s');
        $oldStatus = $qr['status'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE short_links SET status = 'archived', updated_at = ? WHERE id = ?"
            )->execute([$now, $qr['short_link_id']]);

            AuditLogService::log($userId, 'short_link', (int) $qr['short_link_id'], 'archived', [
                'old_status' => $oldStatus,
                'new_status' => 'archived',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $_SESSION['flash'] = ['type' => 'info',
            'text' => 'This QR code has been archived and will no longer redirect.'];
        redirect('/qr/' . $qrId);
    }

    // ── Restore ───────────────────────────────────────────────────────────────

    public function restore(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if ($qr['status'] !== 'archived') {
            redirect('/qr/' . $qrId);
        }

        if (!QrQuotaService::canRestoreForUser($userId)) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'This QR code cannot be restored because your active QR code limit has been reached. Archive another QR code to free up capacity.'];
            redirect('/qr/' . $qrId);
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE short_links SET status = 'active', updated_at = ? WHERE id = ?"
            )->execute([$now, $qr['short_link_id']]);

            AuditLogService::log($userId, 'short_link', (int) $qr['short_link_id'], 'restored', [
                'old_status' => 'archived',
                'new_status' => 'active',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'This QR code has been restored and is active again.'];
        redirect('/qr/' . $qrId);
    }

    // ── Restore previous destination ─────────────────────────────────────────

    public function restoreDestination(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId    = (int) AuthService::userId();
        $qrId      = (int) ($params['id']        ?? 0);
        $historyId = (int) ($params['historyId'] ?? 0);

        EmailVerificationService::requireVerifiedEmail($userId);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        // Archived and disabled links cannot have their destination changed
        if (in_array($qr['status'], ['archived', 'disabled'], true)) {
            redirect('/qr/' . $qrId);
        }

        $pdo     = Database::get();
        $history = DestinationHistoryService::fetchRow($pdo, $historyId, (int) $qr['short_link_id']);

        if ($history === null) {
            $this->notFound();
        }

        $restoreUrl = $history['new_target_url'];

        if (!$this->isValidUrl($restoreUrl)) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'That historical URL is no longer valid and cannot be restored.'];
            redirect('/qr/' . $qrId);
        }

        $block = DomainBlocklistService::isBlockedUrl($restoreUrl);
        if ($block['blocked']) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'This destination domain is not allowed.'];
            redirect('/qr/' . $qrId);
        }

        $oldUrl = $qr['current_target_url'];

        if ($restoreUrl === $oldUrl) {
            $_SESSION['flash'] = ['type' => 'info',
                'text' => 'That destination is already current — no change made.'];
            redirect('/qr/' . $qrId);
        }

        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE short_links SET current_target_url = ?, updated_at = ? WHERE id = ?"
            )->execute([$restoreUrl, $now, $qr['short_link_id']]);

            DestinationHistoryService::record(
                $pdo, (int) $qr['short_link_id'], $userId, $oldUrl, $restoreUrl, 'restore'
            );

            AuditLogService::log($userId, 'qr_code', $qrId, 'destination_restored', [
                'old_url'           => $oldUrl,
                'restored_url'      => $restoreUrl,
                'source_history_id' => $historyId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Destination restored successfully.'];
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

        try {
            $style = $this->resolveRenderStyle(QrStyleService::getForQr((int) $qr['id']), $userId);
            $data  = QrCodeService::generatePng($this->qrBaseUrl() . '/' . $qr['slug'], 300, $style);
        } catch (Throwable $e) {
            error_log('QR PNG generation failed: ' . $e->getMessage());
            $this->forbidden('PNG download is temporarily unavailable. Please try again later.');
        }

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

        try {
            $style = $this->resolveRenderStyle(QrStyleService::getForQr((int) $qr['id']), $userId);
            $data  = QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug'], 300, $style);
        } catch (Throwable $e) {
            error_log('QR SVG generation failed: ' . $e->getMessage());
            $this->forbidden('SVG download is temporarily unavailable. Please try again later.');
        }

        $filename = $this->safeFilename($qr['name'], $qr['slug']) . '.svg';

        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    // ── Analytics ─────────────────────────────────────────────────────────────

    public function analytics(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr          = $this->loadOwnedQrCode($qrId, $userId);
        $shortLinkId = (int) $qr['short_link_id'];

        $retentionDays = (int) EntitlementService::getValue($userId, 'analytics_retention_days', 30);
        $f             = $this->resolveAnalyticsFilters($retentionDays);

        $humanScans = AnalyticsService::getTotalScans($shortLinkId, $f['fromDate'], $f['toDate'], false);
        $botScans   = AnalyticsService::getBotCount($shortLinkId, $f['fromDate'], $f['toDate']);
        $totalScans = $f['includeBots'] ? $humanScans + $botScans : $humanScans;

        $dailyCounts     = AnalyticsService::getDailyCounts($shortLinkId, $f['fromDate'], $f['toDate'], $f['includeBots']);
        $deviceBreakdown = AnalyticsService::getDeviceBreakdown($shortLinkId, $f['fromDate'], $f['toDate'], $f['includeBots']);
        $topReferers     = AnalyticsService::getTopReferers($shortLinkId, $f['fromDate'], $f['toDate'], $f['includeBots']);

        $daysInRange = (int) round((strtotime($f['toDate']) - strtotime($f['fromDate'])) / 86400) + 1;
        $avgPerDay   = $daysInRange > 0 ? round($totalScans / $daysInRange, 1) : 0;

        $peakDay = null;
        if (!empty($dailyCounts)) {
            $peak = array_reduce($dailyCounts, static function (?array $carry, array $row): array {
                return ($carry === null || (int) $row['total'] > (int) $carry['total']) ? $row : $carry;
            });
            if ($peak !== null && (int) $peak['total'] > 0) {
                $peakDay = $peak;
            }
        }

        View::render('qr/analytics', [
            'pageTitle'          => $qr['name'] . ' — Analytics — f29.us Dynamic QR',
            'qr'                 => $qr,
            'shortUrl'           => $this->qrBaseUrl() . '/' . $qr['slug'],
            'retentionDays'      => $retentionDays,
            'allowedFrom'        => $f['allowedFrom'],
            'fromDate'           => $f['fromDate'],
            'toDate'             => $f['toDate'],
            'clamped'            => $f['clamped'],
            'includeBots'        => $f['includeBots'],
            'humanScans'         => $humanScans,
            'botScans'           => $botScans,
            'totalScans'         => $totalScans,
            'avgPerDay'          => $avgPerDay,
            'peakDay'            => $peakDay,
            'dailyCounts'        => $dailyCounts,
            'deviceBreakdown'    => $deviceBreakdown,
            'topReferers'        => $topReferers,
            'canExportAnalytics' => EntitlementService::isEnabled($userId, 'can_export_analytics'),
        ]);
    }

    // ── Analytics CSV export ──────────────────────────────────────────────────

    public function exportAnalytics(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_export_analytics')) {
            $this->forbidden('Analytics export is not available on your plan.');
        }

        $shortLinkId   = (int) $qr['short_link_id'];
        $retentionDays = (int) EntitlementService::getValue($userId, 'analytics_retention_days', 30);
        $f             = $this->resolveAnalyticsFilters($retentionDays);

        $rows     = AnalyticsService::getExportRows($shortLinkId, $f['fromDate'], $f['toDate'], $f['includeBots']);
        $slug     = preg_replace('/[^a-z0-9-]/', '', strtolower($qr['slug']));
        $filename = 'f29-analytics-' . $slug . '-' . $f['fromDate'] . '-to-' . $f['toDate'] . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['scanned_at', 'device_type', 'bot_flag', 'referer',
                       'user_agent', 'country_code', 'region', 'city']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['scanned_at'],
                $row['device_type']  ?? '',
                (int) $row['bot_flag'],
                $this->csvSafe($row['referer']   ?? ''),
                $this->csvSafe($row['user_agent'] ?? ''),
                $row['country_code'] ?? '',
                $row['region']       ?? '',
                $row['city']         ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // ── Style ─────────────────────────────────────────────────────────────────

    public function stylePage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr              = $this->loadOwnedQrCode($qrId, $userId);
        $canCustomize    = EntitlementService::isEnabled($userId, 'can_customize_qr_colors');
        $canUploadLogo   = EntitlementService::isEnabled($userId, 'can_upload_qr_logo');
        $logoMaxKb       = (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0);
        $logoMaxPercent  = (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0);
        $style           = QrStyleService::getForQr($qrId);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $qrPreviewSvg = null;
        try {
            $qrPreviewSvg = base64_encode(
                QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug'], 200,
                    $this->resolveRenderStyle($style, $userId))
            );
        } catch (Throwable) {}

        View::render('qr/style', [
            'pageTitle'      => 'Customize QR — ' . View::e($qr['name']) . ' — f29.us Dynamic QR',
            'qr'             => $qr,
            'style'          => $style,
            'canCustomize'   => $canCustomize,
            'canUploadLogo'  => $canUploadLogo,
            'logoMaxKb'      => $logoMaxKb,
            'logoMaxPercent' => $logoMaxPercent,
            'qrPreviewSvg'   => $qrPreviewSvg,
            'flash'          => $flash,
            'errors'         => [],
        ]);
    }

    public function styleSubmit(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_customize_qr_colors')) {
            $this->forbidden('QR color customization is not available on your plan.');
        }

        $foreground  = trim($_POST['foreground_color'] ?? '');
        $background  = trim($_POST['background_color'] ?? '');
        $transparent = isset($_POST['background_transparent']);

        $errors = QrStyleService::validateColors($foreground, $background);

        if (!empty($errors)) {
            $style = QrStyleService::getForQr($qrId);

            $qrPreviewSvg = null;
            try {
                $qrPreviewSvg = base64_encode(
                    QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug'], 200,
                        $this->resolveRenderStyle($style, $userId))
                );
            } catch (Throwable) {}

            View::render('qr/style', [
                'pageTitle'      => 'Customize QR — ' . View::e($qr['name']) . ' — f29.us Dynamic QR',
                'qr'             => $qr,
                'style'          => array_merge($style, [
                    'foreground_color'       => $foreground,
                    'background_color'       => $background,
                    'background_transparent' => $transparent,
                ]),
                'canCustomize'   => true,
                'canUploadLogo'  => EntitlementService::isEnabled($userId, 'can_upload_qr_logo'),
                'logoMaxKb'      => (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0),
                'logoMaxPercent' => (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0),
                'qrPreviewSvg'   => $qrPreviewSvg,
                'flash'          => null,
                'errors'         => $errors,
            ]);
            return;
        }

        $fg = QrStyleService::normalizeHexColor($foreground);
        $bg = QrStyleService::normalizeHexColor($background);

        $oldStyle = QrStyleService::getForQr($qrId);

        QrStyleService::saveColors($qrId, $fg, $bg, $transparent);

        $newEcl = $oldStyle['logo_enabled'] ? 'H' : 'Q';

        AuditLogService::log($userId, 'qr_code', $qrId, 'style_updated', [
            'old_foreground_color'       => $oldStyle['foreground_color'],
            'new_foreground_color'       => $fg,
            'old_background_color'       => $oldStyle['background_color'],
            'new_background_color'       => $bg,
            'old_background_transparent' => $oldStyle['background_transparent'],
            'new_background_transparent' => $transparent,
            'old_error_correction_level' => $oldStyle['error_correction_level'],
            'new_error_correction_level' => $newEcl,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'QR style saved.'];
        redirect('/qr/' . $qrId . '/style');
    }

    public function styleReset(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_customize_qr_colors')) {
            $this->forbidden('QR color customization is not available on your plan.');
        }

        $oldStyle = QrStyleService::getForQr($qrId);

        QrStyleService::reset($qrId);

        AuditLogService::log($userId, 'qr_code', $qrId, 'style_reset', [
            'old_foreground_color'       => $oldStyle['foreground_color'],
            'new_foreground_color'       => '#000000',
            'old_background_color'       => $oldStyle['background_color'],
            'new_background_color'       => '#FFFFFF',
            'old_background_transparent' => $oldStyle['background_transparent'],
            'new_background_transparent' => false,
            'old_error_correction_level' => $oldStyle['error_correction_level'],
            'new_error_correction_level' => 'M',
        ]);

        $_SESSION['flash'] = ['type' => 'info', 'text' => 'QR style reset to default.'];
        redirect('/qr/' . $qrId . '/style');
    }

    // ── Logo upload ───────────────────────────────────────────────────────────

    public function styleLogoSubmit(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $qr = $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
            $this->forbidden('Logo upload is not available on your current plan.');
        }

        $file   = $_FILES['logo'] ?? [];
        $errors = QrStyleService::validateLogoUpload($file, $userId);

        if (!empty($errors)) {
            $style          = QrStyleService::getForQr($qrId);
            $logoMaxKb      = (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0);
            $logoMaxPercent = (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0);

            $qrPreviewSvg = null;
            try {
                $qrPreviewSvg = base64_encode(
                    QrCodeService::generateSvg($this->qrBaseUrl() . '/' . $qr['slug'], 200,
                        $this->resolveRenderStyle($style, $userId))
                );
            } catch (Throwable) {}

            View::render('qr/style', [
                'pageTitle'      => 'Customize QR — ' . View::e($qr['name']) . ' — f29.us Dynamic QR',
                'qr'             => $qr,
                'style'          => $style,
                'canCustomize'   => EntitlementService::isEnabled($userId, 'can_customize_qr_colors'),
                'canUploadLogo'  => true,
                'logoMaxKb'      => $logoMaxKb,
                'logoMaxPercent' => $logoMaxPercent,
                'qrPreviewSvg'   => $qrPreviewSvg,
                'flash'          => null,
                'errors'         => $errors,
            ]);
            return;
        }

        $oldStyle = QrStyleService::getForQr($qrId);
        $result   = QrStyleService::saveLogo($qrId, $file);

        AuditLogService::log($userId, 'qr_code', $qrId, 'qr_logo_uploaded', [
            'original_filename' => $result['original_filename'],
            'mime_type'         => $result['mime_type'],
            'size_bytes'        => $result['size_bytes'],
            'max_size_kb'       => (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0),
            'logo_percent'      => (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0),
            'old_logo_present'  => $oldStyle['logo_enabled'],
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Logo uploaded successfully.'];
        redirect('/qr/' . $qrId . '/style');
    }

    public function styleLogoRemove(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $qrId   = (int) ($params['id'] ?? 0);

        $this->loadOwnedQrCode($qrId, $userId);

        if (!EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
            $this->forbidden('Logo management is not available on your current plan.');
        }

        $oldStyle = QrStyleService::getForQr($qrId);

        QrStyleService::removeLogo($qrId);

        AuditLogService::log($userId, 'qr_code', $qrId, 'qr_logo_removed', [
            'old_logo_original_filename' => $oldStyle['logo_original_filename'],
            'old_logo_mime_type'         => $oldStyle['logo_mime_type'],
            'old_logo_size_bytes'        => $oldStyle['logo_size_bytes'],
        ]);

        $_SESSION['flash'] = ['type' => 'info', 'text' => 'Logo removed.'];
        redirect('/qr/' . $qrId . '/style');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Prepares the style array for QR generation by the current user's entitlements.
     *
     * When logo_enabled is true, checks can_upload_qr_logo and qr_logo_max_percent.
     * If the user no longer has logo entitlement the logo is suppressed for rendering
     * only — the stored logo file and DB row are not touched.
     */
    private function resolveRenderStyle(array $style, int $userId): array
    {
        if (!$style['logo_enabled']) {
            return $style;
        }

        $canRenderLogo  = EntitlementService::isEnabled($userId, 'can_upload_qr_logo');
        $logoMaxPercent = (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0);

        if (!$canRenderLogo || $logoMaxPercent <= 0) {
            // Suppress logo rendering without touching stored data
            $style['logo_enabled']    = false;
            $style['logo_max_percent'] = 0;

            $hasCustomStyle = $style['foreground_color'] !== '#000000'
                           || $style['background_color']  !== '#FFFFFF'
                           || ($style['background_transparent'] ?? false);
            $style['error_correction_level'] = $hasCustomStyle ? 'Q' : 'M';
        } else {
            $style['logo_max_percent']       = $logoMaxPercent;
            $style['error_correction_level'] = 'H';
        }

        return $style;
    }

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

    private function isValidUrl(string $url): bool
    {
        if (preg_match('/[\r\n\t\0]/', $url)) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    private function qrBaseUrl(): string
    {
        return rtrim($_ENV['QR_BASE_URL'] ?? 'https://f29.us', '/');
    }

    /** Produce a safe, prefixed filename: f29-qr-{safe-name}-{slug} */
    private function safeFilename(string $name, string $slug): string
    {
        $safe = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
        $safe = trim($safe, '-');
        $safe = mb_substr($safe, 0, 50);
        return 'f29-qr-' . ($safe !== '' ? $safe . '-' : '') . $slug;
    }

    /**
     * Resolve analytics date range and bot toggle from GET params.
     * Used by both analytics() and exportAnalytics() so behavior is identical.
     * Default from = full retention window start; default to = today.
     */
    private function resolveAnalyticsFilters(int $retentionDays): array
    {
        $allowedFrom = date('Y-m-d', strtotime("-{$retentionDays} days"));
        $today       = date('Y-m-d');

        $fromParam   = trim($_GET['from'] ?? '');
        $toParam     = trim($_GET['to']   ?? '');
        $includeBots = isset($_GET['include_bots']) && $_GET['include_bots'] === '1';

        $fromDate = ($fromParam !== '' && $this->isValidDate($fromParam))
            ? $fromParam
            : $allowedFrom;

        $toDate = ($toParam !== '' && $this->isValidDate($toParam))
            ? $toParam
            : $today;

        if ($toDate > $today) {
            $toDate = $today;
        }

        $clamped = false;
        if ($fromDate < $allowedFrom) {
            $fromDate = $allowedFrom;
            $clamped  = true;
        }

        if ($fromDate > $toDate) {
            $fromDate = $toDate;
        }

        return compact('fromDate', 'toDate', 'allowedFrom', 'today', 'includeBots', 'clamped');
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /** Neutralize CSV formula injection for values starting with =, +, -, @. */
    private function csvSafe(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }
        return $value;
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
