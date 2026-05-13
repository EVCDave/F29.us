<?php
declare(strict_types=1);

class SubscriptionRequestController
{
    // ── Request list ──────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $pdo    = Database::get();
        $status = trim($_GET['status'] ?? 'pending');

        if (!in_array($status, ['pending', 'approved', 'denied', 'canceled', 'all'], true)) {
            $status = 'pending';
        }

        $base = "
            SELECT scr.*,
                   u.email            AS user_email,
                   u.id               AS user_id,
                   cp.display_name    AS current_plan_name,
                   rp.display_name    AS requested_plan_name,
                   rp.internal_name   AS requested_plan_internal
            FROM   subscription_change_requests scr
            JOIN   users u  ON u.id  = scr.user_id
            LEFT   JOIN plans cp ON cp.id = scr.current_plan_id
            JOIN   plans rp ON rp.id = scr.requested_plan_id
        ";

        if ($status === 'all') {
            $stmt = $pdo->query(
                $base . " ORDER BY CASE WHEN scr.status = 'pending' THEN 0 ELSE 1 END,
                          scr.requested_at DESC LIMIT 200"
            );
        } else {
            $stmt = $pdo->prepare($base . " WHERE scr.status = ? ORDER BY scr.requested_at DESC LIMIT 200");
            $stmt->execute([$status]);
        }

        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/subscription_requests', [
            'pageTitle' => 'Admin: Subscription Requests — f29.us Dynamic QR',
            'requests'  => $requests,
            'status'    => $status,
            'flash'     => $flash,
        ]);
    }

    // ── Request detail ────────────────────────────────────────────────────────

    public function detail(array $params = []): void
    {
        $this->requireAdmin();
        $requestId = (int) ($params['id'] ?? 0);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->renderDetail($requestId, $flash);
    }

    // ── Approve request ───────────────────────────────────────────────────────

    public function approve(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $requestId   = (int) ($params['id'] ?? 0);
        $adminUserId = (int) AuthService::userId();
        $pdo         = Database::get();

        $request = $this->loadRequest($requestId);

        if ($request['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Request is already ' . $request['status'] . ' — no changes made.'];
            redirect('/admin/subscription-requests/' . $requestId);
        }

        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
        $stmt->execute([$request['requested_plan_id']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan || !$plan['is_active'] || $plan['is_legacy']) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Cannot approve: the requested plan is no longer active or has been retired. Deny this request instead.'];
            redirect('/admin/subscription-requests/' . $requestId);
        }

        $targetUserId = (int) $request['user_id'];
        $now          = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, plan_id FROM user_subscriptions WHERE user_id = ? AND status = 'active'"
            );
            $stmt->execute([$targetUserId]);
            $oldSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldSubs as $old) {
                $pdo->prepare("
                    UPDATE user_subscriptions
                    SET    status = 'canceled', canceled_at = ?, ends_at = ?, updated_at = ?
                    WHERE  id = ?
                ")->execute([$now, $now, $now, $old['id']]);
            }

            $pdo->prepare("
                INSERT INTO user_subscriptions
                    (user_id, plan_id, status, billing_cycle, started_at, created_at, updated_at)
                VALUES (?, ?, 'active', 'manual', ?, ?, ?)
            ")->execute([$targetUserId, $request['requested_plan_id'], $now, $now, $now]);

            $newSubId = (int) $pdo->lastInsertId();

            $pdo->prepare("
                UPDATE subscription_change_requests
                SET    status = 'approved', reviewed_at = ?, reviewed_by_user_id = ?
                WHERE  id = ?
            ")->execute([$now, $adminUserId, $requestId]);

            AuditLogService::log($adminUserId, 'subscription_change_request', $requestId, 'request_approved', [
                'target_user_id'    => $targetUserId,
                'requested_plan_id' => (int) $request['requested_plan_id'],
                'plan_internal'     => $plan['internal_name'],
                'new_sub_id'        => $newSubId,
                'prev_sub_ids'      => array_column($oldSubs, 'id'),
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        EntitlementService::clearCache($targetUserId);
        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Approved. User moved to ' . $plan['display_name'] . '.'];
        redirect('/admin/subscription-requests/' . $requestId);
    }

    // ── Deny request ──────────────────────────────────────────────────────────

    public function deny(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $requestId   = (int) ($params['id'] ?? 0);
        $adminUserId = (int) AuthService::userId();
        $note        = mb_substr(trim($_POST['note'] ?? ''), 0, 1000);

        $request = $this->loadRequest($requestId);

        if ($request['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Request is already ' . $request['status'] . ' — no changes made.'];
            redirect('/admin/subscription-requests/' . $requestId);
        }

        $now = gmdate('Y-m-d H:i:s');
        Database::get()->prepare("
            UPDATE subscription_change_requests
            SET    status = 'denied', reviewed_at = ?, reviewed_by_user_id = ?,
                   note   = COALESCE(NULLIF(?, ''), note)
            WHERE  id = ?
        ")->execute([$now, $adminUserId, $note, $requestId]);

        AuditLogService::log($adminUserId, 'subscription_change_request', $requestId, 'request_denied', [
            'target_user_id'    => (int) $request['user_id'],
            'requested_plan_id' => (int) $request['requested_plan_id'],
        ]);

        $_SESSION['flash'] = ['type' => 'info', 'text' => 'Request denied.'];
        redirect('/admin/subscription-requests/' . $requestId);
    }

    // ── Admin cancel request ──────────────────────────────────────────────────

    public function adminCancel(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $requestId   = (int) ($params['id'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $request = $this->loadRequest($requestId);

        if ($request['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Request is already ' . $request['status'] . ' — no changes made.'];
            redirect('/admin/subscription-requests/' . $requestId);
        }

        $now = gmdate('Y-m-d H:i:s');
        Database::get()->prepare("
            UPDATE subscription_change_requests
            SET    status = 'canceled', reviewed_at = ?, reviewed_by_user_id = ?
            WHERE  id = ?
        ")->execute([$now, $adminUserId, $requestId]);

        AuditLogService::log($adminUserId, 'subscription_change_request', $requestId, 'request_admin_canceled', [
            'target_user_id'    => (int) $request['user_id'],
            'requested_plan_id' => (int) $request['requested_plan_id'],
        ]);

        $_SESSION['flash'] = ['type' => 'info', 'text' => 'Request canceled.'];
        redirect('/admin/subscription-requests/' . $requestId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function loadRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare("
            SELECT scr.*,
                   u.email            AS user_email,
                   u.role             AS user_role,
                   u.status           AS user_status,
                   cp.display_name    AS current_plan_name,
                   cp.internal_name   AS current_plan_internal,
                   rp.display_name    AS requested_plan_name,
                   rp.internal_name   AS requested_plan_internal,
                   rp.is_active       AS requested_plan_is_active,
                   rp.is_legacy       AS requested_plan_is_legacy,
                   rp.is_public       AS requested_plan_is_public,
                   ra.email           AS reviewer_email
            FROM   subscription_change_requests scr
            JOIN   users u  ON u.id  = scr.user_id
            LEFT   JOIN plans cp ON cp.id = scr.current_plan_id
            JOIN   plans rp ON rp.id = scr.requested_plan_id
            LEFT   JOIN users ra ON ra.id = scr.reviewed_by_user_id
            WHERE  scr.id = ?
            LIMIT  1
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $this->notFound();
        }

        return $request;
    }

    private function renderDetail(int $requestId, ?array $flash): void
    {
        $request = $this->loadRequest($requestId);

        View::render('admin/subscription_request_detail', [
            'pageTitle' => 'Admin: Request #' . $requestId . ' — f29.us Dynamic QR',
            'request'   => $request,
            'flash'     => $flash,
        ]);
    }

    private function forbidden(string $message = 'Access denied.'): never
    {
        http_response_code(403);
        View::render('errors/forbidden', ['pageTitle' => '403 — Access Denied', 'message' => $message]);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
        exit;
    }
}
