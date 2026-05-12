<?php
declare(strict_types=1);

return [
    'name' => '001_create_users_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email         VARCHAR(255)    NOT NULL,
                password_hash VARCHAR(255)    NOT NULL,
                status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
                created_at    DATETIME        NOT NULL,
                updated_at    DATETIME        NOT NULL,
                last_login_at DATETIME        NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
