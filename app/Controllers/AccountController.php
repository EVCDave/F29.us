<?php
declare(strict_types=1);

class AccountController
{
    // ── Account subscription page ─────────────────────────────────────────────

    public function subscriptionPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $pdo    = Database::get();

        $stmt = $pdo->prepare("
            SELECT us.*, p.display_name AS plan_display_name, p.internal_name AS plan_internal_name,
                   p.is_legacy AS plan_is_legacy
            FROM   user_subscriptions us
            JOIN   plans p ON p.id = us.plan_id
            WHERE  us.user_id = ? AND us.status = 'active'
            ORDER  BY us.started_at DESC, us.id DESC
            LIMIT  1
        ");
        $stmt->execute([$userId]);
        $activeSub     = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $currentPlanId = $activeSub ? (int) $activeSub['plan_id'] : null;

        $stmt = $pdo->query("
            SELECT * FROM plans
            WHERE  is_public = 1 AND is_active = 1 AND is_legacy = 0
            ORDER  BY sort_order ASC, id ASC
        ");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $features = $this->loadFeaturesByPlan(array_column($plans, 'id'), $pdo);

        $stmt = $pdo->prepare("
            SELECT scr.id, scr.status, scr.requested_at, scr.current_plan_id, scr.requested_plan_id,
                   p.display_name AS requested_plan_name, p.internal_name AS requested_plan_internal,
                   cp.display_name AS current_plan_name
            FROM   subscription_change_requests scr
            JOIN   plans p  ON p.id  = scr.requested_plan_id
            LEFT JOIN plans cp ON cp.id = scr.current_plan_id
            WHERE  scr.user_id = ? AND scr.status = 'pending'
            ORDER  BY scr.requested_at DESC
        ");
        $stmt->execute([$userId]);
        $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pendingPlanIds  = array_map('intval', array_column($pendingRequests, 'requested_plan_id'));

        // Recent resolved requests (non-pending) for history section, newest first
        $stmt = $pdo->prepare("
            SELECT scr.id, scr.status, scr.requested_at, scr.reviewed_at, scr.note,
                   rp.display_name AS requested_plan_name, rp.internal_name AS requested_plan_internal
            FROM   subscription_change_requests scr
            JOIN   plans rp ON rp.id = scr.requested_plan_id
            WHERE  scr.user_id = ? AND scr.status != 'pending'
            ORDER  BY scr.id DESC
            LIMIT  10
        ");
        $stmt->execute([$userId]);
        $requestHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $activeQrCount = QrQuotaService::countCountableForUser($userId);

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM   qr_codes    AS qr
            JOIN   short_links AS sl ON sl.id = qr.short_link_id
            WHERE  qr.user_id = ? AND sl.status = 'archived'
        ");
        $stmt->execute([$userId]);
        $archivedQrCount = (int) $stmt->fetchColumn();

        EntitlementService::clearCache($userId);
        $entitlements = EntitlementService::getAllForUser($userId);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('account/subscription', [
            'pageTitle'       => 'My Subscription — f29.us Dynamic QR',
            'activeSub'       => $activeSub,
            'currentPlanId'   => $currentPlanId,
            'plans'           => $plans,
            'features'        => $features,
            'pendingRequests' => $pendingRequests,
            'pendingPlanIds'  => $pendingPlanIds,
            'requestHistory'  => $requestHistory,
            'activeQrCount'   => $activeQrCount,
            'archivedQrCount' => $archivedQrCount,
            'entitlements'    => $entitlements,
            'flash'           => $flash,
        ]);
    }

    // ── Self-service plan change ───────────────────────────────────────────────

    public function changeSubscription(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId = (int) AuthService::userId();
        $pdo    = Database::get();
        $planId = (int) ($_POST['plan_id'] ?? 0);

        if ($planId <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Invalid plan selection.'];
            redirect('/account/subscription');
        }

        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan || !$plan['is_public'] || !$plan['is_active'] || $plan['is_legacy']) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'That plan is not currently available.'];
            redirect('/account/subscription');
        }

        $stmt = $pdo->prepare("
            SELECT id, plan_id FROM user_subscriptions
            WHERE  user_id = ? AND status = 'active'
            ORDER  BY started_at DESC, id DESC
            LIMIT  1
        ");
        $stmt->execute([$userId]);
        $activeSub     = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $currentPlanId = $activeSub ? (int) $activeSub['plan_id'] : null;

        if ($currentPlanId === $planId) {
            $_SESSION['flash'] = ['type' => 'info',
                'text' => 'You are already on the ' . $plan['display_name'] . ' plan.'];
            redirect('/account/subscription');
        }

        // Only free_v1 is self-switchable immediately. All other public plans go through
        // the request flow regardless of price fields (which are currently all null).
        $isFree = $plan['internal_name'] === 'free_v1';

        $now = gmdate('Y-m-d H:i:s');

        if ($isFree) {
            $this->switchToFree($pdo, $userId, $planId, $plan, $activeSub, $currentPlanId, $now);
        } else {
            EmailVerificationService::requireVerifiedEmail($userId);
            $this->createChangeRequest($pdo, $userId, $planId, $plan, $currentPlanId, $now);
        }
    }

    // ── Cancel a pending change request ──────────────────────────────────────

    public function cancelRequest(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId    = (int) AuthService::userId();
        $requestId = (int) ($_POST['request_id'] ?? 0);

        if ($requestId <= 0) {
            redirect('/account/subscription');
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare("
            SELECT id, requested_plan_id FROM subscription_change_requests
            WHERE  id = ? AND user_id = ? AND status = 'pending'
            LIMIT  1
        ");
        $stmt->execute([$requestId, $userId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Request not found or already processed.'];
            redirect('/account/subscription');
        }

        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare("
            UPDATE subscription_change_requests
            SET    status = 'canceled', reviewed_at = ?
            WHERE  id = ?
        ")->execute([$now, $requestId]);

        AuditLogService::log($userId, 'subscription_change_request', $requestId, 'change_request_canceled', [
            'requested_plan_id' => (int) $request['requested_plan_id'],
        ]);

        NotificationService::subscriptionRequestCanceled($requestId, false);

        $_SESSION['flash'] = ['type' => 'info',
            'text' => 'Your plan-change request was canceled. Your current subscription was not changed.'];
        redirect('/account/subscription');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function switchToFree(
        PDO    $pdo,
        int    $userId,
        int    $planId,
        array  $plan,
        ?array $activeSub,
        ?int   $currentPlanId,
        string $now
    ): void {
        $pdo->beginTransaction();
        try {
            if ($activeSub) {
                $pdo->prepare("
                    UPDATE user_subscriptions
                    SET    status = 'canceled', canceled_at = ?, ends_at = ?, updated_at = ?
                    WHERE  id = ?
                ")->execute([$now, $now, $now, $activeSub['id']]);
            }

            $pdo->prepare("
                INSERT INTO user_subscriptions
                    (user_id, plan_id, status, billing_cycle, started_at, created_at, updated_at)
                VALUES (?, ?, 'active', 'free', ?, ?, ?)
            ")->execute([$userId, $planId, $now, $now, $now]);

            $newSubId = (int) $pdo->lastInsertId();

            AuditLogService::log($userId, 'user_subscription', $newSubId, 'self_switched_to_free', [
                'plan_id'        => $planId,
                'internal_name'  => $plan['internal_name'],
                'prev_plan_id'   => $currentPlanId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        EntitlementService::clearCache($userId);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'You have been switched to the Free plan.'];
        redirect('/account/subscription');
    }

    private function createChangeRequest(
        PDO    $pdo,
        int    $userId,
        int    $planId,
        array  $plan,
        ?int   $currentPlanId,
        string $now
    ): void {
        $stmt = $pdo->prepare("
            SELECT id FROM subscription_change_requests
            WHERE  user_id = ? AND requested_plan_id = ? AND status = 'pending'
            LIMIT  1
        ");
        $stmt->execute([$userId, $planId]);
        if ($stmt->fetchColumn() !== false) {
            $_SESSION['flash'] = ['type' => 'info',
                'text' => 'You already have a pending request for the ' . $plan['display_name'] . ' plan.'];
            redirect('/account/subscription');
        }

        $pdo->prepare("
            INSERT INTO subscription_change_requests
                (user_id, current_plan_id, requested_plan_id, status, requested_at)
            VALUES (?, ?, ?, 'pending', ?)
        ")->execute([$userId, $currentPlanId, $planId, $now]);

        $requestId = (int) $pdo->lastInsertId();

        AuditLogService::log($userId, 'subscription_change_request', $requestId, 'change_request_created', [
            'current_plan_id'   => $currentPlanId,
            'requested_plan_id' => $planId,
            'requested_plan'    => $plan['internal_name'],
        ]);

        NotificationService::subscriptionRequestSubmitted($requestId);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Your request for the ' . $plan['display_name'] . ' plan has been submitted for review.'];
        redirect('/account/subscription');
    }

    private function loadFeaturesByPlan(array $planIds, PDO $pdo): array
    {
        if (empty($planIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT plan_id, feature_key, feature_value, value_type
             FROM   plan_features
             WHERE  plan_id IN ($placeholders)
             ORDER  BY plan_id, feature_key ASC"
        );
        $stmt->execute($planIds);
        $features = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $features[(int) $row['plan_id']][$row['feature_key']] = $row;
        }
        return $features;
    }
}
