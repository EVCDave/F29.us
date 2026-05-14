<?php
declare(strict_types=1);

return [
    'name' => '023_create_password_reset_tokens',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE password_reset_tokens (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    BIGINT UNSIGNED NOT NULL,
                email      VARCHAR(255)    NOT NULL,
                token_hash CHAR(64)        NOT NULL,
                expires_at DATETIME        NOT NULL,
                used_at    DATETIME        NULL,
                created_at DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_token_hash (token_hash),
                KEY idx_user_id (user_id),
                KEY idx_email   (email),
                KEY idx_expires (expires_at),
                CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
