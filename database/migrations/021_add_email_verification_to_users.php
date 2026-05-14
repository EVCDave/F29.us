<?php
declare(strict_types=1);

return [
    'name' => '021_add_email_verification_to_users',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
                ADD COLUMN email_verified_at           DATETIME   NULL        AFTER email,
                ADD COLUMN email_verification_required TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at
        ");

        // All pre-existing users are treated as verified — they pre-date the requirement.
        $pdo->exec("
            UPDATE users
            SET email_verified_at           = NOW(),
                email_verification_required = 0
            WHERE email_verified_at IS NULL
        ");
    },
];
