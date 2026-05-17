<?php
declare(strict_types=1);

return [
    'name' => '032_create_remember_tokens_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id       BIGINT UNSIGNED NOT NULL,
                selector      CHAR(32)        NOT NULL,
                token_hash    CHAR(64)        NOT NULL,
                expires_at    DATETIME        NOT NULL,
                last_used_at  DATETIME        NULL,
                created_at    DATETIME        NOT NULL,
                user_agent    VARCHAR(255)    NULL,
                ip_hash       CHAR(64)        NULL,

                UNIQUE KEY uq_remember_tokens_selector   (selector),
                KEY        idx_remember_tokens_user_id   (user_id),
                KEY        idx_remember_tokens_expires_at (expires_at),

                CONSTRAINT fk_remember_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
