<?php
declare(strict_types=1);

class AdminController
{
    // ── Admin home ────────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $pdo             = Database::get();
        $totalUsers      = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalQr         = (int) $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
        $totalPlans      = (int) $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
        $pendingRequests = (int) $pdo->query(
            "SELECT COUNT(*) FROM subscription_change_requests WHERE status = 'pending'"
        )->fetchColumn();
        $activeSubs      = (int) $pdo->query(
            "SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active'"
        )->fetchColumn();

        $cutoff24h = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE created_at >= ?");
        $stmt->execute([$cutoff24h]);
        $recentAuditCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= ? AND success_flag = 0"
        );
        $stmt->execute([$cutoff24h]);
        $failedLogins24h = (int) $stmt->fetchColumn();

        $newContactMessages = (int) $pdo->query(
            "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"
        )->fetchColumn();

        View::render('admin/index', [
            'pageTitle'          => 'Admin — f29.us Dynamic QR',
            'totalUsers'         => $totalUsers,
            'totalQr'            => $totalQr,
            'totalPlans'         => $totalPlans,
            'pendingRequests'    => $pendingRequests,
            'activeSubs'         => $activeSubs,
            'recentAuditCount'   => $recentAuditCount,
            'failedLogins24h'    => $failedLogins24h,
            'newContactMessages' => $newContactMessages,
        ]);
    }

    // ── User list / search ────────────────────────────────────────────────────

    public function users(array $params = []): void
    {
        $this->requireAdmin();

        $search = trim($_GET['q'] ?? '');
        $pdo    = Database::get();

        $sql = "
            SELECT
                u.id, u.email, u.role, u.status, u.created_at,
                (
                    SELECT p.display_name
                    FROM   user_subscriptions us
                    JOIN   plans p ON p.id = us.plan_id
                    WHERE  us.user_id = u.id AND us.status = 'active'
                    ORDER  BY us.started_at DESC, us.id DESC
                    LIMIT  1
                ) AS plan_name
            FROM  users u
        ";

        if ($search !== '') {
            $stmt = $pdo->prepare($sql . " WHERE u.email LIKE ? ORDER BY u.id DESC LIMIT 100");
            $stmt->execute(['%' . $search . '%']);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY u.id DESC LIMIT 100");
            $stmt->execute();
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/users', [
            'pageTitle' => 'Admin: Users — f29.us Dynamic QR',
            'users'     => $users,
            'search'    => $search,
        ]);
    }

    // ── User detail ───────────────────────────────────────────────────────────

    public function userDetail(array $params = []): void
    {
        $this->requireAdmin();
        $userId = (int) ($params['id'] ?? 0);

        $this->renderUserDetail($userId, [], []);
    }

    // ── Change subscription ───────────────────────────────────────────────────

    public function updateSubscription(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $targetUserId = (int) ($params['id'] ?? 0);
        $adminUserId  = (int) AuthService::userId();

        $this->loadUser($targetUserId); // 404 if not found

        $planId        = (int) ($_POST['plan_id'] ?? 0);
        $billingCycle  = trim($_POST['billing_cycle'] ?? 'manual');
        $grandfathered = !empty($_POST['grandfathered']);
        $notes         = mb_substr(trim($_POST['notes'] ?? ''), 0, 1000);

        $validCycles = ['monthly', 'yearly', 'manual', 'free'];
        $errors      = [];

        if ($planId <= 0) {
            $errors[] = 'Plan is required.';
        }

        if (!in_array($billingCycle, $validCycles, true)) {
            $errors[] = 'Invalid billing cycle.';
        }

        $plan = null;
        if ($planId > 0) {
            $stmt = Database::get()->prepare(
                "SELECT id, display_name, internal_name FROM plans WHERE id = ? AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([$planId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                $errors[] = 'Selected plan is invalid or inactive.';
            }
        }

        if (!empty($errors)) {
            $this->renderUserDetail($targetUserId, $errors, [], $_POST);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            // Cancel any currently-active subscriptions for this user
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

            $grandAt = $grandfathered ? $now : null;

            $pdo->prepare("
                INSERT INTO user_subscriptions
                    (user_id, plan_id, status, billing_cycle, started_at,
                     grandfathered_at, notes, created_at, updated_at)
                VALUES (?, ?, 'active', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $targetUserId, $planId, $billingCycle,
                $now, $grandAt, $notes !== '' ? $notes : null, $now, $now,
            ]);

            $newSubId = (int) $pdo->lastInsertId();

            AuditLogService::log($adminUserId, 'user_subscription', $newSubId, 'admin_plan_changed', [
                'target_user_id' => $targetUserId,
                'old_sub_ids'    => array_column($oldSubs, 'id'),
                'old_plan_ids'   => array_column($oldSubs, 'plan_id'),
                'new_plan_id'    => $planId,
                'new_plan'       => $plan['internal_name'],
                'billing_cycle'  => $billingCycle,
                'grandfathered'  => $grandfathered,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        EntitlementService::clearCache($targetUserId);
        redirect('/admin/users/' . $targetUserId);
    }

    // ── Add / update override ─────────────────────────────────────────────────

    public function addOverride(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $targetUserId = (int) ($params['id'] ?? 0);
        $adminUserId  = (int) AuthService::userId();

        $this->loadUser($targetUserId); // 404 if not found

        $featureKey   = trim($_POST['feature_key']   ?? '');
        $valueType    = trim($_POST['value_type']     ?? '');
        $featureValue = trim($_POST['feature_value']  ?? '');
        $expiresAt    = trim($_POST['expires_at']     ?? '');
        $note         = mb_substr(trim($_POST['note'] ?? ''), 0, 255);

        $validTypes = ['int', 'bool', 'string'];
        $errors     = [];

        if ($featureKey === '') {
            $errors[] = 'Feature key is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $featureKey)) {
            $errors[] = 'Feature key must start with a lowercase letter and contain only lowercase letters, digits, and underscores.';
        }

        if (!in_array($valueType, $validTypes, true)) {
            $errors[] = 'Value type must be int, bool, or string.';
        }

        if ($featureValue === '') {
            $errors[] = 'Feature value is required.';
        } elseif ($valueType === 'int' && !preg_match('/^-?\d+$/', $featureValue)) {
            $errors[] = 'Feature value must be an integer for type "int" (e.g. 5, 100).';
        } elseif ($valueType === 'bool' && !in_array($featureValue, ['true', 'false'], true)) {
            $errors[] = 'Feature value must be "true" or "false" for type "bool".';
        }

        $parsedExpiry = null;
        if ($expiresAt !== '') {
            $ts = strtotime($expiresAt);
            if ($ts === false || $ts <= 0) {
                $errors[] = 'Expires at is not a valid date/time.';
            } else {
                $parsedExpiry = date('Y-m-d H:i:s', $ts);
            }
        }

        if (!empty($errors)) {
            $this->renderUserDetail($targetUserId, [], $errors, [], $_POST);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO user_feature_overrides
                (user_id, feature_key, feature_value, value_type, expires_at, note, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                feature_value = VALUES(feature_value),
                value_type    = VALUES(value_type),
                expires_at    = VALUES(expires_at),
                note          = VALUES(note),
                updated_at    = VALUES(updated_at)
        ")->execute([
            $targetUserId, $featureKey, $featureValue, $valueType,
            $parsedExpiry, $note !== '' ? $note : null, $now, $now,
        ]);

        // lastInsertId() returns 0 on a duplicate-key UPDATE — fetch the real id
        $stmt = $pdo->prepare(
            "SELECT id FROM user_feature_overrides WHERE user_id = ? AND feature_key = ? LIMIT 1"
        );
        $stmt->execute([$targetUserId, $featureKey]);
        $overrideId = (int) $stmt->fetchColumn();

        AuditLogService::log($adminUserId, 'user_feature_override', $overrideId, 'admin_override_set', [
            'target_user_id' => $targetUserId,
            'feature_key'    => $featureKey,
            'value_type'     => $valueType,
            'feature_value'  => $featureValue,
            'expires_at'     => $parsedExpiry,
        ]);

        EntitlementService::clearCache($targetUserId);
        redirect('/admin/users/' . $targetUserId);
    }

    // ── Delete override ───────────────────────────────────────────────────────

    public function deleteOverride(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $targetUserId = (int) ($params['id'] ?? 0);
        $overrideId   = (int) ($params['overrideId'] ?? 0);
        $adminUserId  = (int) AuthService::userId();

        $this->loadUser($targetUserId); // 404 if user not found

        // Verify the override belongs to this user before deleting
        $stmt = Database::get()->prepare("
            SELECT id, feature_key, feature_value, value_type
            FROM   user_feature_overrides
            WHERE  id = ? AND user_id = ?
            LIMIT  1
        ");
        $stmt->execute([$overrideId, $targetUserId]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$override) {
            $this->notFound();
        }

        Database::get()->prepare(
            "DELETE FROM user_feature_overrides WHERE id = ?"
        )->execute([$overrideId]);

        AuditLogService::log($adminUserId, 'user_feature_override', $overrideId, 'admin_override_deleted', [
            'target_user_id' => $targetUserId,
            'feature_key'    => $override['feature_key'],
            'value_type'     => $override['value_type'],
            'feature_value'  => $override['feature_value'],
        ]);

        EntitlementService::clearCache($targetUserId);
        redirect('/admin/users/' . $targetUserId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function loadUser(int $userId): array
    {
        if ($userId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare(
            "SELECT id, email, role, status, created_at, last_login_at,
                    first_name, last_name, display_name, company_name, phone, timezone,
                    email_verified_at, email_verification_required, password_changed_at
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->notFound();
        }

        return $user;
    }

    private function renderUserDetail(
        int   $userId,
        array $errors,
        array $overrideErrors,
        array $oldSub      = [],
        array $oldOverride = []
    ): void {
        $user = $this->loadUser($userId);
        $pdo  = Database::get();

        $stmt = $pdo->prepare("
            SELECT us.id, us.status, us.billing_cycle, us.started_at, us.ends_at,
                   us.canceled_at, us.grandfathered_at, us.notes,
                   us.billing_provider, us.billing_status,
                   us.provider_subscription_id, us.current_period_end,
                   us.cancel_at_period_end,
                   p.display_name, p.internal_name
            FROM   user_subscriptions us
            JOIN   plans p ON p.id = us.plan_id
            WHERE  us.user_id = ?
            ORDER  BY us.id DESC
            LIMIT  10
        ");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT * FROM user_feature_overrides WHERE user_id = ? ORDER BY feature_key ASC"
        );
        $stmt->execute([$userId]);
        $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT id, display_name, internal_name FROM plans WHERE is_active = 1 ORDER BY id ASC"
        );
        $stmt->execute();
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Always re-resolve entitlements fresh so the display reflects current DB state
        EntitlementService::clearCache($userId);
        $entitlements   = EntitlementService::getAllForUser($userId);
        $overriddenKeys = array_column($overrides, 'feature_key');

        View::render('admin/user_detail', [
            'pageTitle'      => 'Admin: User #' . $userId . ' — f29.us Dynamic QR',
            'user'           => $user,
            'subscriptions'  => $subscriptions,
            'overrides'      => $overrides,
            'overriddenKeys' => $overriddenKeys,
            'entitlements'   => $entitlements,
            'plans'          => $plans,
            'errors'         => $errors,
            'overrideErrors' => $overrideErrors,
            'oldSub'         => $oldSub,
            'oldOverride'    => $oldOverride,
        ]);
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
