<?php
declare(strict_types=1);

return [
    'name' => '006_create_short_links_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS short_links (
                id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id            BIGINT UNSIGNED NOT NULL,
                slug               VARCHAR(64)     NOT NULL,
                current_target_url TEXT            NOT NULL,
                status             ENUM('active','paused','disabled') NOT NULL DEFAULT 'active',
                created_at         DATETIME        NOT NULL,
                updated_at         DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_short_links_slug (slug),
                KEY idx_short_links_user_id (user_id),
                CONSTRAINT fk_short_links_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
