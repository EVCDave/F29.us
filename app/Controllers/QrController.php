<?php
declare(strict_types=1);

class QrController
{
    public function index(): void
    {
        AuthService::requireAuth();

        View::render('qr/index', [
            'pageTitle' => 'My QR Codes — f29.us Dynamic QR',
            'user'      => AuthService::currentUser(),
        ]);
    }

    public function create(): void
    {
        AuthService::requireAuth();

        View::render('qr/create', [
            'pageTitle' => 'Create QR Code — f29.us Dynamic QR',
            'user'      => AuthService::currentUser(),
        ]);
    }
}
