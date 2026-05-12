<?php
declare(strict_types=1);

return [
    'name' => '007_create_qr_codes_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_codes (
                id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id              BIGINT UNSIGNED NOT NULL,
                short_link_id        BIGINT UNSIGNED NOT NULL,
                name                 VARCHAR(150)    NOT NULL,
                format_defaults_json JSON            NULL,
                created_at           DATETIME        NOT NULL,
                updated_at           DATETIME        NOT NULL,
                PRIMARY KEY (id),
                KEY idx_qr_codes_user_id (user_id),
                KEY idx_qr_codes_short_link_id (short_link_id),
                CONSTRAINT fk_qr_codes_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_qr_codes_short_link_id
                    FOREIGN KEY (short_link_id) REFERENCES short_links (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
