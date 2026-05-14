<?php
declare(strict_types=1);

class PlanController
{
    // ── Plan list ─────────────────────────────────────────────────────────────

    public function plans(array $params = []): void
    {
        $this->requireAdmin();

        $stmt = Database::get()->query("
            SELECT
                p.id, p.internal_name, p.display_name, p.is_public, p.is_active, p.is_legacy,
                p.sort_order, p.created_at,
                COUNT(pf.id) AS feature_count
            FROM  plans p
            LEFT  JOIN plan_features pf ON pf.plan_id = p.id
            GROUP BY p.id
            ORDER BY p.sort_order ASC, p.id ASC
        ");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/plans', [
            'pageTitle' => 'Admin: Plans — f29.us Dynamic QR',
            'plans'     => $plans,
        ]);
    }

    // ── Create plan ───────────────────────────────────────────────────────────

    public function createPlanPage(array $params = []): void
    {
        $this->requireAdmin();

        View::render('admin/plan_create', [
            'pageTitle' => 'Admin: Create Plan — f29.us Dynamic QR',
            'errors'    => [],
            'old'       => [],
        ]);
    }

    public function createPlanSubmit(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $adminUserId = (int) AuthService::userId();
        $input       = $this->parsePlanInput($_POST);
        $errors      = $this->validatePlanInput($input, null);

        if (!empty($errors)) {
            View::render('admin/plan_create', [
                'pageTitle' => 'Admin: Create Plan — f29.us Dynamic QR',
                'errors'    => $errors,
                'old'       => $_POST,
            ]);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO plans
                (internal_name, display_name, description,
                 monthly_price_cents, yearly_price_cents, currency_code,
                 is_public, is_active, is_legacy, sort_order,
                 created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $input['internal_name'],
            $input['display_name'],
            $input['description'],
            $input['monthly_price_cents'],
            $input['yearly_price_cents'],
            $input['currency_code'],
            $input['is_public'],
            $input['is_active'],
            $input['is_legacy'],
            $input['sort_order'],
            $now, $now,
        ]);

        $planId = (int) $pdo->lastInsertId();

        AuditLogService::log($adminUserId, 'plan', $planId, 'plan_created', [
            'internal_name' => $input['internal_name'],
            'display_name'  => $input['display_name'],
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Plan detail ───────────────────────────────────────────────────────────

    public function planDetail(array $params = []): void
    {
        $this->requireAdmin();
        $planId        = (int) ($params['id'] ?? 0);
        $editFeatureId = isset($_GET['edit_feature']) ? (int) $_GET['edit_feature'] : null;

        $this->renderPlanDetail($planId, [], [], $editFeatureId, []);
    }

    // ── Edit plan metadata ────────────────────────────────────────────────────

    public function editPlanPage(array $params = []): void
    {
        $this->requireAdmin();
        $planId = (int) ($params['id'] ?? 0);
        $plan   = $this->loadPlan($planId);
        [$subTotal, $subActive] = $this->loadSubCounts($planId);

        View::render('admin/plan_edit', [
            'pageTitle' => 'Admin: Edit Plan — f29.us Dynamic QR',
            'plan'      => $plan,
            'subTotal'  => $subTotal,
            'subActive' => $subActive,
            'errors'    => [],
            'old'       => [],
        ]);
    }

    public function updatePlan(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id'] ?? 0);
        $plan        = $this->loadPlan($planId);
        $adminUserId = (int) AuthService::userId();

        $input  = $this->parsePlanInput($_POST);
        $errors = $this->validatePlanInput($input, $planId);

        if (!empty($errors)) {
            [$subTotal, $subActive] = $this->loadSubCounts($planId);
            View::render('admin/plan_edit', [
                'pageTitle' => 'Admin: Edit Plan — f29.us Dynamic QR',
                'plan'      => $plan,
                'subTotal'  => $subTotal,
                'subActive' => $subActive,
                'errors'    => $errors,
                'old'       => $_POST,
            ]);
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        Database::get()->prepare("
            UPDATE plans
            SET    display_name         = ?,
                   description          = ?,
                   monthly_price_cents  = ?,
                   yearly_price_cents   = ?,
                   currency_code        = ?,
                   is_public            = ?,
                   is_active            = ?,
                   is_legacy            = ?,
                   sort_order           = ?,
                   updated_at           = ?
            WHERE  id = ?
        ")->execute([
            $input['display_name'],
            $input['description'],
            $input['monthly_price_cents'],
            $input['yearly_price_cents'],
            $input['currency_code'],
            $input['is_public'],
            $input['is_active'],
            $input['is_legacy'],
            $input['sort_order'],
            $now,
            $planId,
        ]);

        $diff = [];
        $trackFields = ['display_name', 'description', 'monthly_price_cents', 'yearly_price_cents',
                        'currency_code', 'is_public', 'is_active', 'is_legacy', 'sort_order'];
        foreach ($trackFields as $field) {
            $old = $plan[$field];
            $new = $input[$field];
            if ((string) $old !== (string) $new) {
                $diff[$field] = ['old' => $old, 'new' => $new];
            }
        }

        AuditLogService::log($adminUserId, 'plan', $planId, 'plan_updated', [
            'internal_name' => $plan['internal_name'],
            'diff'          => $diff,
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Clone plan ────────────────────────────────────────────────────────────

    public function clonePlanPage(array $params = []): void
    {
        $this->requireAdmin();
        $planId = (int) ($params['id'] ?? 0);
        $plan   = $this->loadPlan($planId);

        View::render('admin/plan_clone', [
            'pageTitle' => 'Admin: Clone Plan — f29.us Dynamic QR',
            'plan'      => $plan,
            'errors'    => [],
            'old'       => [
                'internal_name' => $this->suggestCloneName($plan['internal_name']),
                'display_name'  => $plan['display_name'],
                'is_public'     => 0,
                'is_active'     => 1,
                'is_legacy'     => 0,
            ],
        ]);
    }

    public function clonePlanSubmit(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $sourcePlanId = (int) ($params['id'] ?? 0);
        $sourcePlan   = $this->loadPlan($sourcePlanId);
        $adminUserId  = (int) AuthService::userId();

        $newInternalName = trim($_POST['internal_name'] ?? '');
        $newDisplayName  = trim($_POST['display_name']  ?? '');
        $isPublic        = !empty($_POST['is_public'])  ? 1 : 0;
        $isActive        = !empty($_POST['is_active'])  ? 1 : 0;
        $isLegacy        = !empty($_POST['is_legacy'])  ? 1 : 0;

        $errors = [];

        if ($newInternalName === '') {
            $errors[] = 'Internal name is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $newInternalName)) {
            $errors[] = 'Internal name must start with a lowercase letter and contain only lowercase letters, digits, and underscores.';
        } elseif ($newInternalName === $sourcePlan['internal_name']) {
            $errors[] = 'Internal name must be different from the source plan.';
        } else {
            $stmt = Database::get()->prepare("SELECT id FROM plans WHERE internal_name = ? LIMIT 1");
            $stmt->execute([$newInternalName]);
            if ($stmt->fetchColumn() !== false) {
                $errors[] = 'A plan with that internal name already exists.';
            }
        }

        if ($newDisplayName === '') {
            $errors[] = 'Display name is required.';
        }

        if (!empty($errors)) {
            View::render('admin/plan_clone', [
                'pageTitle' => 'Admin: Clone Plan — f29.us Dynamic QR',
                'plan'      => $sourcePlan,
                'errors'    => $errors,
                'old'       => $_POST,
            ]);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO plans
                    (internal_name, display_name, description,
                     monthly_price_cents, yearly_price_cents, currency_code,
                     is_public, is_active, is_legacy, sort_order,
                     created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $newInternalName,
                $newDisplayName,
                $sourcePlan['description'],
                $sourcePlan['monthly_price_cents'],
                $sourcePlan['yearly_price_cents'],
                $sourcePlan['currency_code'],
                $isPublic,
                $isActive,
                $isLegacy,
                $sourcePlan['sort_order'],
                $now, $now,
            ]);

            $newPlanId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "SELECT feature_key, feature_value, value_type FROM plan_features WHERE plan_id = ?"
            );
            $stmt->execute([$sourcePlanId]);
            $features = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $featureStmt = $pdo->prepare("
                INSERT INTO plan_features
                    (plan_id, feature_key, feature_value, value_type, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($features as $f) {
                $featureStmt->execute([
                    $newPlanId, $f['feature_key'], $f['feature_value'], $f['value_type'], $now, $now,
                ]);
            }

            AuditLogService::log($adminUserId, 'plan', $newPlanId, 'plan_cloned', [
                'source_plan_id'       => $sourcePlanId,
                'source_internal_name' => $sourcePlan['internal_name'],
                'new_internal_name'    => $newInternalName,
                'features_copied'      => count($features),
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        redirect('/admin/plans/' . $newPlanId);
    }

    // ── Retire plan ───────────────────────────────────────────────────────────

    public function retirePlan(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id'] ?? 0);
        $plan        = $this->loadPlan($planId);
        $adminUserId = (int) AuthService::userId();

        $now = gmdate('Y-m-d H:i:s');
        Database::get()->prepare("
            UPDATE plans SET is_public = 0, is_legacy = 1, is_active = 1, updated_at = ?
            WHERE  id = ?
        ")->execute([$now, $planId]);

        AuditLogService::log($adminUserId, 'plan', $planId, 'plan_retired', [
            'internal_name' => $plan['internal_name'],
            'was_public'    => (int) $plan['is_public'],
            'was_legacy'    => (int) $plan['is_legacy'],
            'was_active'    => (int) $plan['is_active'],
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Add plan feature ──────────────────────────────────────────────────────

    public function addFeature(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $this->loadPlan($planId); // 404 if not found

        $featureKey   = trim($_POST['feature_key']   ?? '');
        $valueType    = trim($_POST['value_type']     ?? '');
        $featureValue = trim($_POST['feature_value']  ?? '');

        $validTypes = ['int', 'bool', 'string'];
        $errors     = [];

        if ($featureKey === '') {
            $errors[] = 'Feature key is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $featureKey)) {
            $errors[] = 'Feature key must start with a lowercase letter and contain only lowercase letters, digits, and underscores.';
        }

        if (!in_array($valueType, $validTypes, true)) {
            $errors[] = 'Value type must be int, bool, or string.';
        } elseif ($featureKey !== '') {
            $typeError = FeatureKeys::validateType($featureKey, $valueType);
            if ($typeError !== null) {
                $errors[] = $typeError;
            }
        }

        if ($featureValue === '') {
            $errors[] = 'Feature value is required.';
        } elseif ($valueType === 'int' && !preg_match('/^-?\d+$/', $featureValue)) {
            $errors[] = 'Feature value must be an integer for type "int".';
        } elseif ($valueType === 'bool' && !in_array($featureValue, ['true', 'false'], true)) {
            $errors[] = 'Feature value must be "true" or "false" for type "bool".';
        }

        if (!empty($errors)) {
            $this->renderPlanDetail($planId, $errors, [], null, $_POST);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO plan_features
                (plan_id, feature_key, feature_value, value_type, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$planId, $featureKey, $featureValue, $valueType, $now, $now]);

        $featureId = (int) $pdo->lastInsertId();

        AuditLogService::log($adminUserId, 'plan_feature', $featureId, 'feature_added', [
            'plan_id'       => $planId,
            'feature_key'   => $featureKey,
            'value_type'    => $valueType,
            'feature_value' => $featureValue,
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Update plan feature ───────────────────────────────────────────────────

    public function updateFeature(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id']        ?? 0);
        $featureId   = (int) ($params['featureId'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $this->loadPlan($planId); // 404 if plan not found

        $feature = $this->loadFeature($featureId, $planId);

        $valueType    = trim($_POST['value_type']    ?? '');
        $featureValue = trim($_POST['feature_value'] ?? '');

        $validTypes = ['int', 'bool', 'string'];
        $errors     = [];

        if (!in_array($valueType, $validTypes, true)) {
            $errors[] = 'Value type must be int, bool, or string.';
        } else {
            $typeError = FeatureKeys::validateType($feature['feature_key'], $valueType);
            if ($typeError !== null) {
                $errors[] = $typeError;
            }
        }

        if ($featureValue === '') {
            $errors[] = 'Feature value is required.';
        } elseif ($valueType === 'int' && !preg_match('/^-?\d+$/', $featureValue)) {
            $errors[] = 'Feature value must be an integer for type "int".';
        } elseif ($valueType === 'bool' && !in_array($featureValue, ['true', 'false'], true)) {
            $errors[] = 'Feature value must be "true" or "false" for type "bool".';
        }

        if (!empty($errors)) {
            $this->renderPlanDetail($planId, [], $errors, $featureId, []);
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        Database::get()->prepare("
            UPDATE plan_features
            SET    feature_value = ?, value_type = ?, updated_at = ?
            WHERE  id = ?
        ")->execute([$featureValue, $valueType, $now, $featureId]);

        AuditLogService::log($adminUserId, 'plan_feature', $featureId, 'feature_updated', [
            'plan_id'           => $planId,
            'feature_key'       => $feature['feature_key'],
            'old_value_type'    => $feature['value_type'],
            'old_feature_value' => $feature['feature_value'],
            'new_value_type'    => $valueType,
            'new_feature_value' => $featureValue,
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Delete plan feature ───────────────────────────────────────────────────

    public function deleteFeature(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id']        ?? 0);
        $featureId   = (int) ($params['featureId'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $this->loadPlan($planId);
        $feature = $this->loadFeature($featureId, $planId);

        Database::get()->prepare(
            "DELETE FROM plan_features WHERE id = ?"
        )->execute([$featureId]);

        AuditLogService::log($adminUserId, 'plan_feature', $featureId, 'feature_deleted', [
            'plan_id'       => $planId,
            'feature_key'   => $feature['feature_key'],
            'value_type'    => $feature['value_type'],
            'feature_value' => $feature['feature_value'],
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Add billing price mapping ─────────────────────────────────────────────

    public function addBillingPrice(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $this->loadPlan($planId);

        $provider       = trim($_POST['provider']          ?? '');
        $priceId        = trim($_POST['provider_price_id'] ?? '');
        $billingCycle   = trim($_POST['billing_cycle']     ?? '');
        $currencyCode   = strtoupper(trim($_POST['currency_code'] ?? 'USD'));
        $amountRaw      = trim($_POST['amount_cents']      ?? '');

        $validCycles = ['monthly', 'yearly'];
        $errors      = [];

        if ($provider === '') {
            $errors[] = 'Provider is required (e.g. stripe).';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $provider)) {
            $errors[] = 'Provider must start with a lowercase letter and contain only lowercase letters, digits, and underscores.';
        }

        if ($priceId === '') {
            $errors[] = 'Provider price ID is required.';
        }

        if (!in_array($billingCycle, $validCycles, true)) {
            $errors[] = 'Billing cycle must be monthly or yearly.';
        }

        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            $errors[] = 'Currency code must be exactly 3 uppercase letters (e.g. USD).';
        }

        $amountCents = null;
        if ($amountRaw !== '') {
            if (!preg_match('/^\d+$/', $amountRaw)) {
                $errors[] = 'Amount must be a non-negative integer (cents), or leave blank.';
            } else {
                $amountCents = (int) $amountRaw;
            }
        }

        if (!empty($errors)) {
            $this->renderPlanDetail($planId, [], [], null, [], $errors, $_POST);
            return;
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $pdo->prepare("
                INSERT INTO plan_billing_prices
                    (plan_id, provider, provider_price_id, billing_cycle,
                     currency_code, amount_cents, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
            ")->execute([
                $planId, $provider, $priceId, $billingCycle,
                $currencyCode, $amountCents, $now, $now,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'A price mapping for that provider and price ID already exists.';
                $this->renderPlanDetail($planId, [], [], null, [], $errors, $_POST);
                return;
            }
            throw $e;
        }

        $newId = (int) $pdo->lastInsertId();

        AuditLogService::log($adminUserId, 'plan_billing_price', $newId, 'billing_price_added', [
            'plan_id'           => $planId,
            'provider'          => $provider,
            'provider_price_id' => $priceId,
            'billing_cycle'     => $billingCycle,
            'currency_code'     => $currencyCode,
            'amount_cents'      => $amountCents,
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Toggle billing price active state ─────────────────────────────────────

    public function toggleBillingPrice(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $planId      = (int) ($params['id']      ?? 0);
        $priceId     = (int) ($params['priceId'] ?? 0);
        $adminUserId = (int) AuthService::userId();

        $this->loadPlan($planId);

        if ($priceId <= 0) {
            $this->notFound();
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare(
            "SELECT * FROM plan_billing_prices WHERE id = ? AND plan_id = ? LIMIT 1"
        );
        $stmt->execute([$priceId, $planId]);
        $price = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$price) {
            $this->notFound();
        }

        $newActive = $price['is_active'] ? 0 : 1;
        $now       = gmdate('Y-m-d H:i:s');

        $pdo->prepare(
            "UPDATE plan_billing_prices SET is_active = ?, updated_at = ? WHERE id = ?"
        )->execute([$newActive, $now, $priceId]);

        AuditLogService::log($adminUserId, 'plan_billing_price', $priceId, 'billing_price_toggled', [
            'plan_id'           => $planId,
            'provider'          => $price['provider'],
            'provider_price_id' => $price['provider_price_id'],
            'billing_cycle'     => $price['billing_cycle'],
            'old_is_active'     => (int) $price['is_active'],
            'new_is_active'     => $newActive,
        ]);

        redirect('/admin/plans/' . $planId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function loadPlan(int $planId): array
    {
        if ($planId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare(
            "SELECT * FROM plans WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            $this->notFound();
        }

        return $plan;
    }

    private function loadFeature(int $featureId, int $planId): array
    {
        if ($featureId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare(
            "SELECT * FROM plan_features WHERE id = ? AND plan_id = ? LIMIT 1"
        );
        $stmt->execute([$featureId, $planId]);
        $feature = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$feature) {
            $this->notFound();
        }

        return $feature;
    }

    private function loadSubCounts(int $planId): array
    {
        $stmt = Database::get()->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count
            FROM user_subscriptions
            WHERE plan_id = ?
        ");
        $stmt->execute([$planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [(int) ($row['total'] ?? 0), (int) ($row['active_count'] ?? 0)];
    }

    private function parsePlanInput(array $post): array
    {
        $monthlyRaw = trim($post['monthly_price_cents'] ?? '');
        $yearlyRaw  = trim($post['yearly_price_cents']  ?? '');

        return [
            'internal_name'       => trim($post['internal_name']   ?? ''),
            'display_name'        => trim($post['display_name']     ?? ''),
            'description'         => trim($post['description']      ?? ''),
            'monthly_price_cents' => $monthlyRaw !== '' ? $monthlyRaw : null,
            'yearly_price_cents'  => $yearlyRaw  !== '' ? $yearlyRaw  : null,
            'currency_code'       => strtoupper(trim($post['currency_code'] ?? 'USD')),
            'is_public'           => !empty($post['is_public'])  ? 1 : 0,
            'is_active'           => !empty($post['is_active'])  ? 1 : 0,
            'is_legacy'           => !empty($post['is_legacy'])  ? 1 : 0,
            'sort_order'          => trim($post['sort_order'] ?? '0'),
        ];
    }

    private function validatePlanInput(array $input, ?int $currentPlanId): array
    {
        $errors = [];

        if ($input['internal_name'] === '') {
            $errors[] = 'Internal name is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $input['internal_name'])) {
            $errors[] = 'Internal name must start with a lowercase letter and contain only lowercase letters, digits, and underscores.';
        } else {
            if ($currentPlanId === null) {
                $stmt = Database::get()->prepare(
                    "SELECT id FROM plans WHERE internal_name = ? LIMIT 1"
                );
                $stmt->execute([$input['internal_name']]);
                if ($stmt->fetchColumn() !== false) {
                    $errors[] = 'A plan with that internal name already exists.';
                }
            }
        }

        if ($input['display_name'] === '') {
            $errors[] = 'Display name is required.';
        }

        $monthly = $input['monthly_price_cents'];
        if ($monthly !== null && !preg_match('/^\d+$/', (string) $monthly)) {
            $errors[] = 'Monthly price must be a non-negative integer (cents).';
        }

        $yearly = $input['yearly_price_cents'];
        if ($yearly !== null && !preg_match('/^\d+$/', (string) $yearly)) {
            $errors[] = 'Yearly price must be a non-negative integer (cents).';
        }

        if (!preg_match('/^[A-Z]{3}$/', $input['currency_code'])) {
            $errors[] = 'Currency code must be exactly 3 uppercase letters (e.g. USD).';
        }

        if (!preg_match('/^-?\d+$/', (string) $input['sort_order'])) {
            $errors[] = 'Sort order must be an integer.';
        }

        return $errors;
    }

    private function suggestCloneName(string $internalName): string
    {
        if (preg_match('/^(.+)_v(\d+)$/', $internalName, $m)) {
            return $m[1] . '_v' . ((int) $m[2] + 1);
        }
        return $internalName . '_v2';
    }

    private function renderPlanDetail(
        int    $planId,
        array  $addErrors,
        array  $updateErrors,
        ?int   $updateFeatureId,
        array  $oldAdd,
        array  $billingPriceErrors = [],
        array  $oldBillingPrice    = []
    ): void {
        $plan = $this->loadPlan($planId);
        $pdo  = Database::get();

        $stmt = $pdo->prepare(
            "SELECT * FROM plan_features WHERE plan_id = ? ORDER BY feature_key ASC"
        );
        $stmt->execute([$planId]);
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT * FROM plan_billing_prices WHERE plan_id = ? ORDER BY provider ASC, billing_cycle ASC"
        );
        $stmt->execute([$planId]);
        $billingPrices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        [$subTotal, $subActive] = $this->loadSubCounts($planId);

        View::render('admin/plan_detail', [
            'pageTitle'          => 'Admin: Plan — ' . $plan['display_name'] . ' — f29.us Dynamic QR',
            'plan'               => $plan,
            'features'           => $features,
            'subTotal'           => $subTotal,
            'subActive'          => $subActive,
            'addErrors'          => $addErrors,
            'updateErrors'       => $updateErrors,
            'updateFeatureId'    => $updateFeatureId,
            'oldAdd'             => $oldAdd,
            'billingPrices'      => $billingPrices,
            'billingPriceErrors' => $billingPriceErrors,
            'oldBillingPrice'    => $oldBillingPrice,
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
