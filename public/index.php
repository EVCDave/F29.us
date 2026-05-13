<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// Controllers
require APP_PATH . '/Controllers/HomeController.php';
require APP_PATH . '/Controllers/DashboardController.php';
require APP_PATH . '/Controllers/AuthController.php';
require APP_PATH . '/Controllers/QrController.php';
require APP_PATH . '/Controllers/RedirectController.php';

$router = new Router();

// ── Public routes ────────────────────────────────────────────────────────────
$router->get('/',          [HomeController::class, 'index']);
$router->get('/login',     [AuthController::class, 'loginPage']);
$router->post('/login',    [AuthController::class, 'loginSubmit']);
$router->get('/register',  [AuthController::class, 'registerPage']);
$router->post('/register', [AuthController::class, 'registerSubmit']);

// ── Authenticated: dashboard ─────────────────────────────────────────────────
$router->get('/dashboard', [DashboardController::class, 'index']);

// ── Authenticated: QR management ─────────────────────────────────────────────
// Exact routes must be registered before pattern routes so /qr/create
// is never accidentally captured by /qr/{id}.
$router->get('/qr',              [QrController::class, 'index']);
$router->get('/qr/create',       [QrController::class, 'createPage']);
$router->post('/qr',             [QrController::class, 'createSubmit']);

$router->get('/qr/{id}',                [QrController::class, 'detail']);
$router->get('/qr/{id}/edit',           [QrController::class, 'editPage']);
$router->post('/qr/{id}/update',        [QrController::class, 'updateDestination']);
$router->post('/qr/{id}/pause',         [QrController::class, 'pause']);
$router->post('/qr/{id}/resume',        [QrController::class, 'resume']);
$router->get('/qr/{id}/download/png',   [QrController::class, 'downloadPng']);
$router->get('/qr/{id}/download/svg',   [QrController::class, 'downloadSvg']);
$router->get('/qr/{id}/analytics',      [QrController::class, 'analytics']);

// ── Logout ───────────────────────────────────────────────────────────────────
$router->post('/logout', [AuthController::class, 'logout']);

// ── Public slug catch-all (must be last) ─────────────────────────────────────
// All named routes above take precedence via exact-match and ordered pattern
// matching. This only fires for paths that matched nothing else.
$router->get('/{slug}', [RedirectController::class, 'handle']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
