<?php
declare(strict_types=1);

return [
    'name' => '003_create_plan_features_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plan_features (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                plan_id       BIGINT UNSIGNED NOT NULL,
                feature_key   VARCHAR(100)    NOT NULL,
                feature_value VARCHAR(255)    NOT NULL,
                value_type    ENUM('int','bool','string') NOT NULL,
                created_at    DATETIME        NOT NULL,
                updated_at    DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_plan_features (plan_id, feature_key),
                CONSTRAINT fk_plan_features_plan_id
                    FOREIGN KEY (plan_id) REFERENCES plans (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
