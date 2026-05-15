<?php
declare(strict_types=1);

return [
    'name' => '027_add_stripe_customer_id_to_users',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
                ADD COLUMN stripe_customer_id VARCHAR(255) NULL AFTER timezone,
                ADD INDEX idx_users_stripe_customer_id (stripe_customer_id)
        ");
    },
];
