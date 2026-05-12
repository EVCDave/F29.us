<?php
declare(strict_types=1);

class HomeController
{
    public function index(): void
    {
        View::render('home', [
            'pageTitle' => 'f29.us Dynamic QR — Dynamic QR Codes',
        ]);
    }
}
