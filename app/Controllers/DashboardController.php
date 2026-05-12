<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        AuthService::requireAuth();

        View::render('dashboard', [
            'pageTitle' => 'Dashboard — f29.us Dynamic QR',
            'user'      => AuthService::currentUser(),
        ]);
    }
}
