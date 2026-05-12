<?php
declare(strict_types=1);

return [
    'name' => '004_create_user_subscriptions_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_subscriptions (
                id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id              BIGINT UNSIGNED NOT NULL,
                plan_id              BIGINT UNSIGNED NOT NULL,
                status               ENUM('active','canceled','expired','suspended') NOT NULL DEFAULT 'active',
                billing_cycle        ENUM('monthly','yearly','manual','free') NOT NULL DEFAULT 'free',
                started_at           DATETIME        NOT NULL,
                ends_at              DATETIME        NULL,
                canceled_at          DATETIME        NULL,
                grandfathered_at     DATETIME        NULL,
                price_cents_override INT UNSIGNED    NULL,
                notes                TEXT            NULL,
                created_at           DATETIME        NOT NULL,
                updated_at           DATETIME        NOT NULL,
                PRIMARY KEY (id),
                KEY idx_user_subscriptions_user_id (user_id),
                KEY idx_user_subscriptions_plan_id (plan_id),
                CONSTRAINT fk_user_subscriptions_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_user_subscriptions_plan_id
                    FOREIGN KEY (plan_id) REFERENCES plans (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
