<?php
declare(strict_types=1);

return [
    'name' => '008_create_scan_events_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS scan_events (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                short_link_id BIGINT UNSIGNED NOT NULL,
                scanned_at    DATETIME        NOT NULL,
                ip_hash       CHAR(64)        NULL,
                user_agent    TEXT            NULL,
                referer       TEXT            NULL,
                country_code  CHAR(2)         NULL,
                region        VARCHAR(100)    NULL,
                city          VARCHAR(100)    NULL,
                device_type   VARCHAR(50)     NULL,
                bot_flag      TINYINT(1)      NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_scan_events_short_link_id (short_link_id),
                KEY idx_scan_events_scanned_at (scanned_at),
                CONSTRAINT fk_scan_events_short_link_id
                    FOREIGN KEY (short_link_id) REFERENCES short_links (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
