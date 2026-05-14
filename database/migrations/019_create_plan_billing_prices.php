<?php
declare(strict_types=1);

return [
    'name' => '019_create_plan_billing_prices',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plan_billing_prices (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                plan_id           BIGINT UNSIGNED NOT NULL,
                provider          VARCHAR(50)     NOT NULL,
                provider_price_id VARCHAR(255)    NOT NULL,
                billing_cycle     ENUM('monthly','yearly') NOT NULL,
                currency_code     CHAR(3)         NOT NULL DEFAULT 'USD',
                amount_cents      INT UNSIGNED    NULL,
                is_active         TINYINT(1)      NOT NULL DEFAULT 1,
                created_at        DATETIME        NOT NULL,
                updated_at        DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_provider_price (provider, provider_price_id),
                INDEX idx_pbp_plan_provider_cycle (plan_id, provider, billing_cycle),
                CONSTRAINT fk_pbp_plan
                    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
