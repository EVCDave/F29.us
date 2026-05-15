<?php
declare(strict_types=1);

return [
    'name' => '024_add_password_changed_at_to_users',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash
        ");
    },
];
