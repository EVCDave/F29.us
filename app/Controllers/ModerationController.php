<?php
declare(strict_types=1);

class ModerationController
{
    /**
     * Allowed disable-reason values surfaced on the moderation form. Stored as
     * the human label in `short_links.disabled_reason` so existing free-text
     * entries continue to render, while admins now pick from a controlled list.
     */
    public const DISABLE_REASONS = [
        'phishing'         => 'Phishing / credential theft',
        'malware'          => 'Malware / harmful download',
        'spam'             => 'Spam',
        'impersonation'    => 'Impersonation / deception',
        'illegal'          => 'Illegal content',
        'harassment'       => 'Harassment / threats',
        'policy_violation' => 'Policy violation',
        'other'            => 'Other',
    ];

    // ── Moderated links list ──────────────────────────────────────────────────

    public function links(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $statusFilter   = trim($_GET['status'] ?? 'disabled');
        $ownerFilter    = trim($_GET['owner']  ?? '');
        $slugFilter     = trim($_GET['slug']   ?? '');
        $destFilter     = trim($_GET['dest']   ?? '');
        $domainFilter   = trim($_GET['domain'] ?? '');
        $abuseFilter    = trim($_GET['has_abuse_reports'] ?? '');

        $validStatuses = ['', 'active', 'paused', 'disabled', 'archived'];
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'disabled';
        }
        if (!in_array($abuseFilter, ['', 'yes', 'no'], true)) {
            $abuseFilter = '';
        }

        $where = [];
        $args  = [];

        if ($statusFilter !== '') {
            $where[] = 'sl.status = ?';
            $args[]  = $statusFilter;
        }
        if ($ownerFilter !== '') {
            $where[] = 'u.email LIKE ?';
            $args[]  = '%' . $ownerFilter . '%';
        }
        if ($slugFilter !== '') {
            $where[] = 'sl.slug LIKE ?';
            $args[]  = '%' . $slugFilter . '%';
        }
        if ($destFilter !== '') {
            $where[] = 'sl.current_target_url LIKE ?';
            $args[]  = '%' . $destFilter . '%';
        }
        if ($domainFilter !== '') {
            // Match destination URLs whose host equals (or is a subdomain of)
            // the supplied filter. Cheap LIKE that the existing index can use.
            $where[] = '(sl.current_target_url LIKE ? OR sl.current_target_url LIKE ?)';
            $args[]  = '%://' . $domainFilter . '/%';
            $args[]  = '%://' . $domainFilter;
        }
        if ($abuseFilter === 'yes') {
            $where[] = 'abuse_counts.report_count > 0';
        } elseif ($abuseFilter === 'no') {
            $where[] = '(abuse_counts.report_count IS NULL OR abuse_counts.report_count = 0)';
        }

        $sql = "
            SELECT
                sl.id, sl.slug, sl.current_target_url, sl.status,
                sl.disabled_reason, sl.disabled_at, sl.created_at,
                qr.id    AS qr_id,
                qr.name  AS qr_name,
                u.email  AS owner_email,
                du.email AS disabled_by_email,
                COALESCE(scan_counts.total_scans, 0)        AS total_scans,
                COALESCE(abuse_counts.report_count, 0)      AS abuse_report_count
            FROM  short_links sl
            LEFT JOIN qr_codes qr ON qr.short_link_id = sl.id
            LEFT JOIN users    u  ON u.id  = sl.user_id
            LEFT JOIN users    du ON du.id = sl.disabled_by_user_id
            LEFT JOIN (
                SELECT short_link_id, COUNT(*) AS total_scans
                FROM   scan_events
                GROUP  BY short_link_id
            ) AS scan_counts ON scan_counts.short_link_id = sl.id
            LEFT JOIN (
                SELECT related_short_link_id, COUNT(*) AS report_count
                FROM   contact_messages
                WHERE  category = 'abuse' AND related_short_link_id IS NOT NULL
                GROUP  BY related_short_link_id
            ) AS abuse_counts ON abuse_counts.related_short_link_id = sl.id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY sl.disabled_at DESC, sl.id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/links', [
            'pageTitle'    => 'Admin: Moderated Links — f29.us Dynamic QR',
            'links'        => $links,
            'statusFilter' => $statusFilter,
            'ownerFilter'  => $ownerFilter,
            'slugFilter'   => $slugFilter,
            'destFilter'   => $destFilter,
            'domainFilter' => $domainFilter,
            'abuseFilter'  => $abuseFilter,
            'flash'        => $flash,
        ]);
    }

    // ── Link detail ───────────────────────────────────────────────────────────

    public function linkDetail(array $params = []): void
    {
        $this->requireAdmin();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        $pdo = Database::get();

        // Scan-count windows: total, last 24h, last 7d. Single column-COUNT
        // queries against an indexed (short_link_id, scanned_at) lookup —
        // cheap to run together on the detail page.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scan_events WHERE short_link_id = ?");
        $stmt->execute([$shortLinkId]);
        $totalScans = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM scan_events
            WHERE short_link_id = ? AND scanned_at >= ?
        ");
        $stmt->execute([$shortLinkId, gmdate('Y-m-d H:i:s', strtotime('-24 hours'))]);
        $scans24h = (int) $stmt->fetchColumn();

        $stmt->execute([$shortLinkId, gmdate('Y-m-d H:i:s', strtotime('-7 days'))]);
        $scans7d = (int) $stmt->fetchColumn();

        // Destination URL → host, used by the "Block destination domain" action.
        $destinationDomain = DomainBlocklistService::extractHost((string) $link['current_target_url']);
        $domainAlreadyBlocked = false;
        if ($destinationDomain !== null) {
            $normalized = DomainBlocklistService::normalizeDomain($destinationDomain);
            $check      = DomainBlocklistService::isBlockedDomain($normalized);
            $domainAlreadyBlocked = (bool) $check['blocked'];
        }

        // Destination history for this short link (most recent first).
        $destinationHistory = [];
        if (class_exists('DestinationHistoryService')) {
            $destinationHistory = DestinationHistoryService::fetchForShortLink($pdo, $shortLinkId);
        }

        // Related abuse reports — both via the structured FK and via subject/url
        // fallbacks for reports created before Phase 42 (legacy rows whose body
        // mentions the slug). Deduplicate by message ID.
        $stmt = $pdo->prepare("
            SELECT id, status, subject, name, email, reported_url, reported_domain, created_at
            FROM   contact_messages
            WHERE  category = 'abuse'
              AND (related_short_link_id = ? OR related_qr_code_id = ?)
            ORDER  BY created_at DESC
            LIMIT  25
        ");
        $stmt->execute([$shortLinkId, (int) ($link['qr_id'] ?? 0)]);
        $relatedAbuseReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent audit logs for this short link AND, if attached, the QR code.
        $auditArgs = ['short_link', $shortLinkId];
        $auditSql  = "(entity_type = ? AND entity_id = ?)";
        if (!empty($link['qr_id'])) {
            $auditSql .= " OR (entity_type = ? AND entity_id = ?)";
            $auditArgs[] = 'qr_code';
            $auditArgs[] = (int) $link['qr_id'];
        }
        $stmt = $pdo->prepare("
            SELECT al.id, al.user_id, al.entity_type, al.entity_id, al.action,
                   al.metadata_json, al.created_at,
                   u.email AS actor_email
            FROM   audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE  $auditSql
            ORDER  BY al.created_at DESC, al.id DESC
            LIMIT  25
        ");
        $stmt->execute($auditArgs);
        $auditEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/link_detail', [
            'pageTitle'            => 'Admin: Link #' . $shortLinkId . ' — f29.us Dynamic QR',
            'link'                 => $link,
            'totalScans'           => $totalScans,
            'scans24h'             => $scans24h,
            'scans7d'              => $scans7d,
            'destinationDomain'    => $destinationDomain,
            'domainAlreadyBlocked' => $domainAlreadyBlocked,
            'destinationHistory'   => $destinationHistory,
            'relatedAbuseReports'  => $relatedAbuseReports,
            'auditEntries'         => $auditEntries,
            'disableReasons'       => self::DISABLE_REASONS,
            'flash'                => $flash,
        ]);
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    public function disable(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId     = (int) AuthService::userId();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        if ($link['status'] === 'disabled') {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'This link is already disabled.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $reasonRaw = trim($_POST['disabled_reason'] ?? '');
        $note      = trim($_POST['moderation_note']  ?? '');

        if ($reasonRaw === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'A disable reason is required.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        // Accept either the controlled-list key or, for backwards compatibility
        // with the old free-text dialog, the raw label. Store the label so the
        // existing admin/list rendering stays unchanged.
        if (isset(self::DISABLE_REASONS[$reasonRaw])) {
            $reason = self::DISABLE_REASONS[$reasonRaw];
        } else {
            $reason = mb_substr($reasonRaw, 0, 255);
        }
        $now    = gmdate('Y-m-d H:i:s');

        Database::get()->prepare("
            UPDATE short_links
            SET    status              = 'disabled',
                   disabled_reason    = ?,
                   disabled_by_user_id = ?,
                   disabled_at        = ?,
                   moderation_note    = ?,
                   updated_at         = ?
            WHERE  id = ?
        ")->execute([
            $reason,
            $adminId,
            $now,
            $note !== '' ? $note : null,
            $now,
            $shortLinkId,
        ]);

        AuditLogService::log($adminId, 'short_link', $shortLinkId, 'admin_disabled', [
            'slug'            => $link['slug'],
            'old_status'      => $link['status'],
            'disabled_reason' => $reason,
            'has_note'        => $note !== '',
        ]);

        NotificationService::linkDisabled($shortLinkId);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Link #' . $shortLinkId . ' (' . $link['slug'] . ') has been disabled.'];
        redirect('/admin/moderation/links/' . $shortLinkId);
    }

    // ── Admin restore ─────────────────────────────────────────────────────────

    public function restore(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId     = (int) AuthService::userId();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        if ($link['status'] !== 'disabled') {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Only disabled links can be restored via moderation.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $now = gmdate('Y-m-d H:i:s');

        Database::get()->prepare(
            "UPDATE short_links SET status = 'active', updated_at = ? WHERE id = ?"
        )->execute([$now, $shortLinkId]);

        AuditLogService::log($adminId, 'short_link', $shortLinkId, 'admin_restored', [
            'slug'            => $link['slug'],
            'old_status'      => 'disabled',
            'new_status'      => 'active',
            'disabled_reason' => $link['disabled_reason'],
        ]);

        NotificationService::linkRestored($shortLinkId);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Link #' . $shortLinkId . ' (' . $link['slug'] . ') has been restored to active.'];
        redirect('/admin/moderation/links/' . $shortLinkId);
    }

    // ── Blocked domains list + add form ───────────────────────────────────────

    public function domains(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $stmt = $pdo->query("
            SELECT bd.id, bd.domain, bd.reason, bd.is_active, bd.created_at,
                   u.email AS created_by_email
            FROM   blocked_domains bd
            LEFT JOIN users u ON u.id = bd.created_by_user_id
            ORDER  BY bd.is_active DESC, bd.created_at DESC
        ");
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/domains', [
            'pageTitle' => 'Admin: Blocked Domains — f29.us Dynamic QR',
            'domains'   => $domains,
            'flash'     => $flash,
        ]);
    }

    // ── Add blocked domain ────────────────────────────────────────────────────

    public function addDomain(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId   = (int) AuthService::userId();
        $rawDomain = trim($_POST['domain'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');

        if ($rawDomain === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Domain is required.'];
            redirect('/admin/moderation/domains');
        }

        // Accept bare domains or URLs — strip any scheme the admin may have included
        $forParsing = preg_match('#^https?://#i', $rawDomain) ? $rawDomain : 'https://' . $rawDomain;
        $host       = DomainBlocklistService::extractHost($forParsing);

        if ($host === null) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Please enter a valid domain (e.g. example.com).'];
            redirect('/admin/moderation/domains');
        }

        $normalized = DomainBlocklistService::normalizeDomain($host);

        if (!DomainBlocklistService::isValidDomain($normalized)) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Please enter a valid domain (e.g. example.com).'];
            redirect('/admin/moderation/domains');
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $pdo->prepare("
                INSERT INTO blocked_domains
                    (domain, reason, is_active, created_by_user_id, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?, ?)
            ")->execute([
                $normalized,
                $reason !== '' ? $reason : null,
                $adminId,
                $now,
                $now,
            ]);

            $domainId = (int) $pdo->lastInsertId();

            AuditLogService::log($adminId, 'blocked_domain', $domainId, 'domain_blocked', [
                'domain' => $normalized,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            $_SESSION['flash'] = ['type' => 'success',
                'text' => 'Domain "' . $normalized . '" has been added to the blocklist.'];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['flash'] = ['type' => 'error',
                    'text' => 'That domain is already in the blocklist.'];
            } else {
                throw $e;
            }
        }

        redirect('/admin/moderation/domains');
    }

    // ── Toggle blocked domain active/inactive ─────────────────────────────────

    public function toggleDomain(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId  = (int) AuthService::userId();
        $domainId = (int) ($params['id'] ?? 0);

        if ($domainId <= 0) {
            $this->notFound();
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare(
            "SELECT id, domain, is_active FROM blocked_domains WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$domainId]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$domain) {
            $this->notFound();
        }

        $newActive = $domain['is_active'] ? 0 : 1;
        $now       = gmdate('Y-m-d H:i:s');

        $pdo->prepare(
            "UPDATE blocked_domains SET is_active = ?, updated_at = ? WHERE id = ?"
        )->execute([$newActive, $now, $domainId]);

        AuditLogService::log($adminId, 'blocked_domain', $domainId, 'domain_toggled', [
            'domain'     => $domain['domain'],
            'old_active' => (bool) $domain['is_active'],
            'new_active' => (bool) $newActive,
        ]);

        $label = $newActive ? 'activated' : 'deactivated';
        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Domain "' . $domain['domain'] . '" has been ' . $label . '.'];
        redirect('/admin/moderation/domains');
    }

    // ── Block destination domain from a link's detail page ──────────────────

    /**
     * Add (or reactivate) a `blocked_domains` row for the host of this short
     * link's current destination. Does NOT auto-disable this link or any other
     * link to the domain — moderation actions are explicit on purpose. Future
     * QR creation / destination edits against the domain are rejected by the
     * existing `DomainBlocklistService::isBlockedUrl` check.
     */
    public function blockDomainFromLink(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId     = (int) AuthService::userId();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        $host = DomainBlocklistService::extractHost((string) $link['current_target_url']);
        if ($host === null) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Could not extract a domain from this link\'s destination URL.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $normalized = DomainBlocklistService::normalizeDomain($host);
        if (!DomainBlocklistService::isValidDomain($normalized)) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Destination domain "' . $normalized . '" is not a valid hostname.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $reason = trim($_POST['reason'] ?? '');
        $reason = $reason === '' ? null : mb_substr($reason, 0, 1000);

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare("SELECT id, is_active FROM blocked_domains WHERE domain = ? LIMIT 1");
        $stmt->execute([$normalized]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((int) $existing['is_active'] === 1) {
                $_SESSION['flash'] = ['type' => 'info',
                    'text' => 'Domain "' . $normalized . '" is already on the active blocklist.'];
                redirect('/admin/moderation/links/' . $shortLinkId);
            }
            // Reactivate the previously-inactive row, do not duplicate.
            $pdo->prepare("
                UPDATE blocked_domains
                SET is_active = 1,
                    reason = COALESCE(?, reason),
                    updated_at = ?
                WHERE id = ?
            ")->execute([$reason, $now, (int) $existing['id']]);
            $domainId = (int) $existing['id'];
            $reactivated = true;
        } else {
            $pdo->prepare("
                INSERT INTO blocked_domains
                    (domain, reason, is_active, created_by_user_id, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?, ?)
            ")->execute([$normalized, $reason, $adminId, $now, $now]);
            $domainId = (int) $pdo->lastInsertId();
            $reactivated = false;
        }

        AuditLogService::log($adminId, 'blocked_domain', $domainId, 'blocked_domain_added_from_link', [
            'domain'              => $normalized,
            'reason'              => $reason,
            'source_short_link_id'=> $shortLinkId,
            'source_slug'         => $link['slug'],
            'reactivated'         => $reactivated,
        ]);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => $reactivated
                ? 'Domain "' . $normalized . '" reactivated on the blocklist.'
                : 'Domain "' . $normalized . '" added to the blocklist.'];
        redirect('/admin/moderation/links/' . $shortLinkId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function loadShortLink(int $id): array
    {
        if ($id <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare("
            SELECT
                sl.id, sl.slug, sl.current_target_url, sl.status,
                sl.disabled_reason, sl.disabled_by_user_id, sl.disabled_at,
                sl.moderation_note, sl.created_at, sl.updated_at,
                qr.id    AS qr_id,
                qr.name  AS qr_name,
                u.id     AS owner_id,
                u.email  AS owner_email,
                du.email AS disabled_by_email
            FROM  short_links sl
            LEFT JOIN qr_codes qr ON qr.short_link_id = sl.id
            LEFT JOIN users    u  ON u.id  = sl.user_id
            LEFT JOIN users    du ON du.id = sl.disabled_by_user_id
            WHERE sl.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->notFound();
        }

        return $row;
    }

    private function forbidden(string $message = 'Access denied.'): never
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
