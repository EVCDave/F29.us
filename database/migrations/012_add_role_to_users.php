<?php
declare(strict_types=1);

return [
    'name' => '012_add_role_to_users',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
                ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user'
                    AFTER status
        ");
    },
];
