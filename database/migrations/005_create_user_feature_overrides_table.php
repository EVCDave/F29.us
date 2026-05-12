<?php
declare(strict_types=1);

return [
    'name' => '005_create_user_feature_overrides_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_feature_overrides (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id       BIGINT UNSIGNED NOT NULL,
                feature_key   VARCHAR(100)    NOT NULL,
                feature_value VARCHAR(255)    NOT NULL,
                value_type    ENUM('int','bool','string') NOT NULL,
                expires_at    DATETIME        NULL,
                note          VARCHAR(255)    NULL,
                created_at    DATETIME        NOT NULL,
                updated_at    DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_feature_overrides (user_id, feature_key),
                CONSTRAINT fk_user_feature_overrides_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
