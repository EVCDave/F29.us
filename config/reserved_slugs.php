<?php
declare(strict_types=1);

// Slugs that are reserved for application routes and cannot be used
// as user-created short-link slugs. Validation logic (not yet built)
// must check this list before accepting a slug.
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
    'analytics',
    'settings',
    'assets',
    'public',
    'static',
    'media',
    'robots.txt',
    'sitemap.xml',
    'favicon.ico',
];
