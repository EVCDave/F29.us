<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// Controllers
require APP_PATH . '/Controllers/HomeController.php';
require APP_PATH . '/Controllers/DashboardController.php';
require APP_PATH . '/Controllers/AuthController.php';
require APP_PATH . '/Controllers/QrController.php';

$router = new Router();

// ── Public routes ────────────────────────────────────────────────────────────
$router->get('/',          [HomeController::class,      'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/login',     [AuthController::class,      'loginPage']);
$router->get('/register',  [AuthController::class,      'registerPage']);
$router->get('/qr',        [QrController::class,        'index']);
$router->get('/qr/create', [QrController::class,        'create']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);
