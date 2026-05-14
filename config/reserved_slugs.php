<?php
declare(strict_types=1);

// Slugs that are reserved for application routes and cannot be used
// as user-created short-link slugs. SlugService::isReserved() enforces
// this list during custom slug validation and auto-generation.
return [
    'login',
    'logout',
    'register',
    'dashboard',
    'pricing',
    'plans',
    'admin',
    'api',
    'qr',
    'account',
    'analytics',
    'settings',
    'terms',
    'privacy',
    'acceptable-use',
    'abuse',
    'contact',
    'verify-email',
    'forgot-password',
    'reset-password',
    'assets',
    'public',
    'static',
    'media',
    'robots.txt',
    'sitemap.xml',
    'favicon.ico',
];
