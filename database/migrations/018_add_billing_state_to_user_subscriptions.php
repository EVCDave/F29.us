<?php
declare(strict_types=1);

return [
    'name' => '018_add_billing_state_to_user_subscriptions',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE user_subscriptions
                ADD COLUMN billing_provider         VARCHAR(50)  NULL
                    AFTER billing_cycle,
                ADD COLUMN provider_customer_id     VARCHAR(255) NULL
                    AFTER billing_provider,
                ADD COLUMN provider_subscription_id VARCHAR(255) NULL
                    AFTER provider_customer_id,
                ADD COLUMN billing_status           ENUM(
                    'not_applicable','manual','trialing','active',
                    'past_due','canceled','unpaid','incomplete'
                ) NOT NULL DEFAULT 'not_applicable'
                    AFTER provider_subscription_id,
                ADD COLUMN current_period_start     DATETIME NULL
                    AFTER billing_status,
                ADD COLUMN current_period_end       DATETIME NULL
                    AFTER current_period_start,
                ADD COLUMN trial_ends_at            DATETIME NULL
                    AFTER current_period_end,
                ADD COLUMN cancel_at_period_end     TINYINT(1) NOT NULL DEFAULT 0
                    AFTER trial_ends_at,
                ADD INDEX idx_us_billing_provider   (billing_provider),
                ADD INDEX idx_us_provider_customer  (provider_customer_id),
                ADD INDEX idx_us_provider_sub       (provider_subscription_id),
                ADD INDEX idx_us_billing_status     (billing_status)
        ");
    },
];
