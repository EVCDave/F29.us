<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        View::render('dashboard', [
            'pageTitle' => 'Dashboard — f29.us Dynamic QR',
        ]);
    }
}
