<?php
declare(strict_types=1);

class PolicyController
{
    public function terms(array $params = []): void
    {
        View::render('policy/terms', [
            'pageTitle' => 'Terms of Service — f29.us Dynamic QR',
        ], 'main');
    }

    public function privacy(array $params = []): void
    {
        View::render('policy/privacy', [
            'pageTitle' => 'Privacy Policy — f29.us Dynamic QR',
        ], 'main');
    }

    public function acceptableUse(array $params = []): void
    {
        View::render('policy/acceptable_use', [
            'pageTitle' => 'Acceptable Use Policy — f29.us Dynamic QR',
        ], 'main');
    }

    public function abuse(array $params = []): void
    {
        View::render('policy/abuse', [
            'pageTitle'  => 'Report Abuse — f29.us Dynamic QR',
            'abuseEmail' => $_ENV['ABUSE_EMAIL'] ?? 'abuse@f29.us',
        ], 'main');
    }

    public function contact(array $params = []): void
    {
        View::render('policy/contact', [
            'pageTitle'    => 'Contact — f29.us Dynamic QR',
            'supportEmail' => $_ENV['SUPPORT_EMAIL']  ?? 'support@f29.us',
            'abuseEmail'   => $_ENV['ABUSE_EMAIL']    ?? 'abuse@f29.us',
            'privacyEmail' => $_ENV['PRIVACY_EMAIL']  ?? 'privacy@f29.us',
        ], 'main');
    }

    public function help(array $params = []): void
    {
        View::render('help', [
            'pageTitle' => 'Help — f29.us Dynamic QR',
        ], 'main');
    }
}
