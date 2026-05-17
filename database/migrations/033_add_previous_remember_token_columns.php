<?php
declare(strict_types=1);

return [
    'name' => '033_add_previous_remember_token_columns',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE remember_tokens
                ADD COLUMN previous_selector     CHAR(32) NULL AFTER token_hash,
                ADD COLUMN previous_token_hash   CHAR(64) NULL AFTER previous_selector,
                ADD COLUMN previous_valid_until  DATETIME NULL AFTER previous_token_hash,
                ADD UNIQUE KEY uq_remember_tokens_previous_selector (previous_selector)
        ");
    },
];
