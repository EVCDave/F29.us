<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

require __DIR__ . '/bootstrap.php';

// Seeders run in order; each must complete before the next starts
$seeders = [
    DB_PATH . '/seeders/PlansSeeder.php',
    DB_PATH . '/seeders/PlanFeaturesSeeder.php',
];

$pdo = Database::get();

foreach ($seeders as $file) {
    if (!file_exists($file)) {
        echo "  MISSING  " . basename($file) . "\n";
        exit(1);
    }

    $seeder = require $file;

    if (!isset($seeder['name'], $seeder['run']) || !is_callable($seeder['run'])) {
        echo "  ERROR    Invalid seeder file: " . basename($file) . "\n";
        exit(1);
    }

    echo "  SEED    {$seeder['name']} ... ";
    try {
        $seeder['run']($pdo);
        echo "done\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        echo "          " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nSeeding complete.\n";
