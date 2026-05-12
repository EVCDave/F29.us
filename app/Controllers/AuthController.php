<?php
declare(strict_types=1);

class AuthController
{
    public function loginPage(): void
    {
        View::render('auth/login', [
            'pageTitle' => 'Login — f29.us Dynamic QR',
        ]);
    }

    public function registerPage(): void
    {
        View::render('auth/register', [
            'pageTitle' => 'Register — f29.us Dynamic QR',
        ]);
    }
}
