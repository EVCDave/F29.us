<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = Database::get();

// Ensure the migrations tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255)  NOT NULL,
        run_at    DATETIME      NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id ASC");
$ran  = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'migration');

$files = glob(DB_PATH . '/migrations/*.php');
sort($files);

if (empty($files)) {
    echo "No migration files found in database/migrations/.\n";
    exit(0);
}

$ranCount  = 0;
$skipCount = 0;

foreach ($files as $file) {
    $migration = require $file;

    if (!isset($migration['name'], $migration['up']) || !is_callable($migration['up'])) {
        echo "  ERROR   Invalid migration file: " . basename($file) . "\n";
        continue;
    }

    $name = $migration['name'];

    if (in_array($name, $ran, true)) {
        echo "  SKIP    {$name}\n";
        $skipCount++;
        continue;
    }

    echo "  RUN     {$name} ... ";
    try {
        $migration['up']($pdo);
        $pdo->prepare("INSERT INTO migrations (migration, run_at) VALUES (?, NOW())")
            ->execute([$name]);
        echo "done\n";
        $ranCount++;
    } catch (Throwable $e) {
        echo "FAILED\n";
        echo "          " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigrations: {$ranCount} run, {$skipCount} skipped.\n";
