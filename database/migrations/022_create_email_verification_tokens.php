<?php
declare(strict_types=1);

return [
    'name' => '022_create_email_verification_tokens',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE email_verification_tokens (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id    BIGINT UNSIGNED NOT NULL,
                email      VARCHAR(255)    NOT NULL,
                token_hash CHAR(64)        NOT NULL,
                purpose    ENUM('registration','email_change') NOT NULL,
                new_email  VARCHAR(255)    NULL,
                expires_at DATETIME        NOT NULL,
                used_at    DATETIME        NULL,
                created_at DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_token_hash (token_hash),
                KEY idx_user_id (user_id),
                KEY idx_email   (email),
                KEY idx_purpose (purpose),
                KEY idx_expires (expires_at),
                CONSTRAINT fk_evt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
