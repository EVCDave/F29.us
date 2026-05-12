<?php
declare(strict_types=1);

return [
    'name' => '002_create_plans_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plans (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                internal_name       VARCHAR(100)    NOT NULL,
                display_name        VARCHAR(100)    NOT NULL,
                description         TEXT            NULL,
                monthly_price_cents INT UNSIGNED    NULL,
                yearly_price_cents  INT UNSIGNED    NULL,
                currency_code       CHAR(3)         NOT NULL DEFAULT 'USD',
                is_public           TINYINT(1)      NOT NULL DEFAULT 1,
                is_active           TINYINT(1)      NOT NULL DEFAULT 1,
                is_legacy           TINYINT(1)      NOT NULL DEFAULT 0,
                sort_order          INT             NOT NULL DEFAULT 0,
                created_at          DATETIME        NOT NULL,
                updated_at          DATETIME        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_plans_internal_name (internal_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
