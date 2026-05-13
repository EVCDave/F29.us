<?php
declare(strict_types=1);

return [
    'name' => '010_create_login_attempts_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email_normalized  VARCHAR(255)    NULL,
                ip_hash           CHAR(64)        NULL,
                attempted_at      DATETIME        NOT NULL,
                success_flag      TINYINT(1)      NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_login_attempts_email (email_normalized),
                KEY idx_login_attempts_ip    (ip_hash),
                KEY idx_login_attempts_at    (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
