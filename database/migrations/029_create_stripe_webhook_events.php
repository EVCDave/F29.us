<?php
declare(strict_types=1);

return [
    'name' => '029_create_stripe_webhook_events',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stripe_webhook_events (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                stripe_event_id   VARCHAR(255)    NOT NULL,
                event_type        VARCHAR(100)    NOT NULL,
                processing_status ENUM('received','processed','failed','ignored')
                                      NOT NULL DEFAULT 'received',
                error_message     TEXT            NULL,
                processed_at      DATETIME        NULL,
                created_at        DATETIME        NOT NULL,

                UNIQUE KEY uq_stripe_event_id (stripe_event_id),
                INDEX idx_swe_event_type (event_type),
                INDEX idx_swe_status (processing_status),
                INDEX idx_swe_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
