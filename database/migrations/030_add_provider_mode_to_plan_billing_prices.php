<?php
declare(strict_types=1);

return [
    'name' => '030_add_provider_mode_to_plan_billing_prices',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE plan_billing_prices
            ADD COLUMN provider_mode ENUM('test','live') NOT NULL DEFAULT 'test'
                AFTER provider
        ");
        $pdo->exec("
            CREATE INDEX idx_pbp_provider_mode
                ON plan_billing_prices (provider, provider_mode, is_active)
        ");
    },
];
