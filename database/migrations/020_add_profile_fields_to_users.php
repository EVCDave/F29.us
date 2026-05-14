<?php
declare(strict_types=1);

return [
    'name' => '020_add_profile_fields_to_users',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
                ADD COLUMN first_name   VARCHAR(100) NULL AFTER email,
                ADD COLUMN last_name    VARCHAR(100) NULL AFTER first_name,
                ADD COLUMN display_name VARCHAR(150) NULL AFTER last_name,
                ADD COLUMN company_name VARCHAR(150) NULL AFTER display_name,
                ADD COLUMN phone        VARCHAR(50)  NULL AFTER company_name,
                ADD COLUMN timezone     VARCHAR(100) NULL AFTER phone
        ");
    },
];
