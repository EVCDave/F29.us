<?php
declare(strict_types=1);

class QrController
{
    public function index(): void
    {
        View::render('qr/index', [
            'pageTitle' => 'My QR Codes — f29.us Dynamic QR',
        ]);
    }

    public function create(): void
    {
        View::render('qr/create', [
            'pageTitle' => 'Create QR Code — f29.us Dynamic QR',
        ]);
    }
}
