<?php
declare(strict_types=1);

return [
    'name' => '016_add_moderation_fields_to_short_links',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE short_links
                ADD COLUMN disabled_reason     VARCHAR(255)    NULL AFTER status,
                ADD COLUMN disabled_by_user_id BIGINT UNSIGNED NULL AFTER disabled_reason,
                ADD COLUMN disabled_at         DATETIME        NULL AFTER disabled_by_user_id,
                ADD COLUMN moderation_note     TEXT            NULL AFTER disabled_at,
                ADD CONSTRAINT fk_sl_disabled_by
                    FOREIGN KEY (disabled_by_user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
        ");
    },
];
