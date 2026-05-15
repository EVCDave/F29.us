<?php
declare(strict_types=1);

return [
    'name' => '028_create_stripe_checkout_sessions',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stripe_checkout_sessions (
                id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id               BIGINT UNSIGNED NOT NULL,
                plan_id               BIGINT UNSIGNED NOT NULL,
                plan_billing_price_id BIGINT UNSIGNED NOT NULL,
                stripe_session_id     VARCHAR(255)    NOT NULL,
                stripe_customer_id    VARCHAR(255)    NULL,
                status                ENUM('pending','completed','expired','canceled')
                                          NOT NULL DEFAULT 'pending',
                checkout_url          TEXT            NULL,
                created_at            DATETIME        NOT NULL,
                completed_at          DATETIME        NULL,

                UNIQUE KEY uq_stripe_session_id (stripe_session_id),
                INDEX idx_scs_user_id (user_id),
                INDEX idx_scs_status (status),
                INDEX idx_scs_created_at (created_at),

                CONSTRAINT fk_scs_user  FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_scs_plan  FOREIGN KEY (plan_id)
                    REFERENCES plans(id),
                CONSTRAINT fk_scs_price FOREIGN KEY (plan_billing_price_id)
                    REFERENCES plan_billing_prices(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
