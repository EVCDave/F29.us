<?php
declare(strict_types=1);

return [
    'name'     => $_ENV['APP_NAME'] ?? 'f29.us Dynamic QR',
    'base_url' => $_ENV['APP_URL']  ?? 'http://localhost:8000',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'UTC',
];
