<?php
declare(strict_types=1);

// Set before bootstrap so errors during startup are also caught.
$debug = false;
set_exception_handler(static function (Throwable $e) use (&$debug): void {
    error_log(
        'Uncaught: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine()
        . PHP_EOL . $e->getTraceAsString()
    );

    if (headers_sent()) {
        if ($debug) {
            echo "\n<!-- " . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . ' -->';
        }
        return;
    }

    http_response_code(500);

    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo (string) $e;
        return;
    }

    if (class_exists('View') && defined('APP_PATH')) {
        try {
            View::render('errors/server_error', [], 'main');
            return;
        } catch (Throwable) {
            // fall through to raw HTML fallback
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Server Error</title></head>'
       . '<body><h1>Server Error</h1><p>Something went wrong. Please try again later.</p>'
       . '</body></html>';
});

require __DIR__ . '/../bootstrap.php';

// Controllers
require APP_PATH . '/Controllers/HomeController.php';
require APP_PATH . '/Controllers/DashboardController.php';
require APP_PATH . '/Controllers/AuthController.php';
require APP_PATH . '/Controllers/QrController.php';
require APP_PATH . '/Controllers/StaticQrController.php';
require APP_PATH . '/Controllers/RedirectController.php';
require APP_PATH . '/Controllers/AdminController.php';
require APP_PATH . '/Controllers/PlanController.php';
require APP_PATH . '/Controllers/PricingController.php';
require APP_PATH . '/Controllers/AccountController.php';
require APP_PATH . '/Controllers/AccountSettingsController.php';
require APP_PATH . '/Controllers/SubscriptionRequestController.php';
require APP_PATH . '/Controllers/AuditLogController.php';
require APP_PATH . '/Controllers/SubscriptionHistoryController.php';
require APP_PATH . '/Controllers/OpsController.php';
require APP_PATH . '/Controllers/ModerationController.php';
require APP_PATH . '/Controllers/PolicyController.php';
require APP_PATH . '/Controllers/StripeWebhookController.php';
require APP_PATH . '/Controllers/EmailVerificationController.php';
require APP_PATH . '/Controllers/PasswordResetController.php';
require APP_PATH . '/Controllers/AccountSecurityController.php';

$router = new Router();

// ── Public routes ────────────────────────────────────────────────────────────
$router->get('/',                [HomeController::class,   'index']);
$router->get('/pricing',         [PricingController::class, 'index']);
$router->get('/terms',           [PolicyController::class, 'terms']);
$router->get('/privacy',         [PolicyController::class, 'privacy']);
$router->get('/acceptable-use',  [PolicyController::class, 'acceptableUse']);
$router->get('/abuse',           [PolicyController::class, 'abuse']);
$router->get('/contact',         [PolicyController::class, 'contact']);
$router->get('/help',            [PolicyController::class, 'help']);
$router->get('/login',     [AuthController::class, 'loginPage']);
$router->post('/login',    [AuthController::class, 'loginSubmit']);
$router->get('/register',  [AuthController::class, 'registerPage']);
$router->post('/register', [AuthController::class, 'registerSubmit']);
$router->get('/verify-email',    [EmailVerificationController::class, 'verify']);
$router->get('/forgot-password', [PasswordResetController::class, 'forgotPage']);
$router->post('/forgot-password',[PasswordResetController::class, 'forgotSubmit']);
$router->get('/reset-password',  [PasswordResetController::class, 'resetPage']);
$router->post('/reset-password', [PasswordResetController::class, 'resetSubmit']);

// ── Authenticated: dashboard ─────────────────────────────────────────────────
$router->get('/dashboard', [DashboardController::class, 'index']);

// ── Authenticated: QR management ─────────────────────────────────────────────
// Exact routes must be registered before pattern routes so /qr/create
// is never accidentally captured by /qr/{id}.
$router->get('/qr',              [QrController::class, 'index']);
$router->get('/qr/create',       [QrController::class, 'createPage']);
$router->post('/qr',             [QrController::class, 'createSubmit']);

// Static QR generator (stateless — no DB writes). Exact-match routes are
// resolved before /qr/{id} so /qr/static cannot be captured as an id.
$router->get('/qr/static',                 [StaticQrController::class, 'form']);
$router->post('/qr/static/preview',        [StaticQrController::class, 'preview']);
$router->post('/qr/static/download/png',   [StaticQrController::class, 'downloadPng']);
$router->post('/qr/static/download/svg',   [StaticQrController::class, 'downloadSvg']);

$router->get('/qr/{id}',                [QrController::class, 'detail']);
$router->get('/qr/{id}/edit',           [QrController::class, 'editPage']);
$router->post('/qr/{id}/update',        [QrController::class, 'update']);
$router->post('/qr/{id}/pause',         [QrController::class, 'pause']);
$router->post('/qr/{id}/resume',        [QrController::class, 'resume']);
$router->post('/qr/{id}/archive',       [QrController::class, 'archive']);
$router->post('/qr/{id}/restore',       [QrController::class, 'restore']);
$router->post('/qr/{id}/destination-history/{historyId}/restore', [QrController::class, 'restoreDestination']);
$router->get('/qr/{id}/download/png',   [QrController::class, 'downloadPng']);
$router->get('/qr/{id}/download/svg',   [QrController::class, 'downloadSvg']);
$router->get('/qr/{id}/analytics/export', [QrController::class, 'exportAnalytics']);
$router->get('/qr/{id}/analytics',        [QrController::class, 'analytics']);
$router->get('/qr/{id}/style',              [QrController::class, 'stylePage']);
$router->post('/qr/{id}/style',             [QrController::class, 'styleSubmit']);
$router->post('/qr/{id}/style/reset',       [QrController::class, 'styleReset']);
$router->post('/qr/{id}/style/logo',        [QrController::class, 'styleLogoSubmit']);
$router->post('/qr/{id}/style/logo/remove', [QrController::class, 'styleLogoRemove']);

// ── Email verification ────────────────────────────────────────────────────────
$router->get('/account/verify-email',         [EmailVerificationController::class, 'verifyPage']);
$router->post('/account/verify-email/resend', [EmailVerificationController::class, 'resend']);

// ── Account settings (authenticated) ─────────────────────────────────────────
$router->get('/account',                    [AccountSettingsController::class, 'redirect']);
$router->get('/account/settings',           [AccountSettingsController::class, 'settingsPage']);
$router->post('/account/settings/profile',  [AccountSettingsController::class, 'updateProfile']);
$router->post('/account/settings/email',    [AccountSettingsController::class, 'updateEmail']);
$router->post('/account/settings/password', [AccountSettingsController::class, 'updatePassword']);
$router->get('/account/security',           [AccountSecurityController::class, 'securityPage']);

// ── Account subscription (authenticated) ──────────────────────────────────────
$router->get('/account/subscription',                [AccountController::class, 'subscriptionPage']);
$router->post('/account/subscription/change',        [AccountController::class, 'changeSubscription']);
$router->post('/account/subscription/checkout',      [AccountController::class, 'checkout']);
$router->post('/account/subscription/request-cancel',  [AccountController::class, 'cancelRequest']);
$router->post('/account/subscription/cancel-stripe',   [AccountController::class, 'cancelStripeSubscription']);

// ── Logout ───────────────────────────────────────────────────────────────────
$router->post('/logout', [AuthController::class, 'logout']);

// ── Admin (auth + admin role enforced inside controller) ──────────────────────
// Exact routes registered before pattern routes to prevent /admin/users
// from being swallowed by /admin/users/{id}.
$router->get('/admin',              [AdminController::class, 'index']);
$router->get('/admin/users',        [AdminController::class, 'users']);
$router->get('/admin/users/{id}',   [AdminController::class, 'userDetail']);
$router->post('/admin/users/{id}/subscription',                  [AdminController::class, 'updateSubscription']);
$router->post('/admin/users/{id}/overrides',                     [AdminController::class, 'addOverride']);
$router->post('/admin/users/{id}/overrides/{overrideId}/delete', [AdminController::class, 'deleteOverride']);

// Exact plan routes registered before /admin/plans/{id} to avoid pattern capture.
$router->get('/admin/plans',                [PlanController::class, 'plans']);
$router->get('/admin/plans/create',         [PlanController::class, 'createPlanPage']);
$router->post('/admin/plans',               [PlanController::class, 'createPlanSubmit']);
$router->get('/admin/plans/{id}',           [PlanController::class, 'planDetail']);
$router->get('/admin/plans/{id}/edit',      [PlanController::class, 'editPlanPage']);
$router->post('/admin/plans/{id}/update',   [PlanController::class, 'updatePlan']);
$router->post('/admin/plans/{id}/features',                              [PlanController::class, 'addFeature']);
$router->post('/admin/plans/{id}/features/{featureId}/update',           [PlanController::class, 'updateFeature']);
$router->post('/admin/plans/{id}/features/{featureId}/delete',           [PlanController::class, 'deleteFeature']);
$router->get('/admin/plans/{id}/clone',   [PlanController::class, 'clonePlanPage']);
$router->post('/admin/plans/{id}/clone',  [PlanController::class, 'clonePlanSubmit']);
$router->post('/admin/plans/{id}/retire', [PlanController::class, 'retirePlan']);
$router->post('/admin/plans/{id}/billing-prices',                    [PlanController::class, 'addBillingPrice']);
$router->post('/admin/plans/{id}/billing-prices/{priceId}/toggle',   [PlanController::class, 'toggleBillingPrice']);

// Exact route registered before pattern route.
$router->get('/admin/subscription-requests',              [SubscriptionRequestController::class, 'index']);
$router->get('/admin/subscription-requests/{id}',         [SubscriptionRequestController::class, 'detail']);
$router->post('/admin/subscription-requests/{id}/approve',[SubscriptionRequestController::class, 'approve']);
$router->post('/admin/subscription-requests/{id}/deny',   [SubscriptionRequestController::class, 'deny']);
$router->post('/admin/subscription-requests/{id}/cancel', [SubscriptionRequestController::class, 'adminCancel']);

// ── Admin: audit logs ─────────────────────────────────────────────────────────
// Exact route registered before pattern route.
$router->get('/admin/audit-logs',        [AuditLogController::class, 'index']);
$router->get('/admin/audit-logs/{id}',   [AuditLogController::class, 'detail']);

// ── Admin: subscription history ───────────────────────────────────────────────
$router->get('/admin/subscriptions', [SubscriptionHistoryController::class, 'index']);

// ── Admin: operations ─────────────────────────────────────────────────────────
$router->get('/admin/ops',                    [OpsController::class, 'index']);
$router->post('/admin/ops/send-test-email',   [OpsController::class, 'sendTestEmail']);

// ── Admin: moderation ─────────────────────────────────────────────────────────
// Exact routes before patterns to prevent /admin/moderation/links from
// being captured by /admin/moderation/links/{id}.
$router->get('/admin/moderation/links',                        [ModerationController::class, 'links']);
$router->get('/admin/moderation/links/{id}',                   [ModerationController::class, 'linkDetail']);
$router->post('/admin/moderation/links/{id}/disable',          [ModerationController::class, 'disable']);
$router->post('/admin/moderation/links/{id}/restore',          [ModerationController::class, 'restore']);
$router->get('/admin/moderation/domains',                      [ModerationController::class, 'domains']);
$router->post('/admin/moderation/domains',                     [ModerationController::class, 'addDomain']);
$router->post('/admin/moderation/domains/{id}/toggle',         [ModerationController::class, 'toggleDomain']);

// ── Stripe webhooks (no CSRF — signature-verified by Stripe) ─────────────────
$router->post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// ── Public slug catch-all (must be last) ─────────────────────────────────────
// All named routes above take precedence via exact-match and ordered pattern
// matching. This only fires for paths that matched nothing else.
$router->get('/{slug}', [RedirectController::class, 'handle']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
