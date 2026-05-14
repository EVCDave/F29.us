<?php
declare(strict_types=1);

return [
    'name' => '017_create_blocked_domains',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS blocked_domains (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                domain              VARCHAR(255)    NOT NULL,
                reason              VARCHAR(255)    NULL,
                is_active           TINYINT(1)      NOT NULL DEFAULT 1,
                created_by_user_id  BIGINT UNSIGNED NULL,
                created_at          DATETIME        NOT NULL,
                updated_at          DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_blocked_domain (domain),
                INDEX idx_blocked_domains_active (is_active),
                CONSTRAINT fk_blocked_domain_creator
                    FOREIGN KEY (created_by_user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
