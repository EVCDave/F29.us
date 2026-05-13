<?php
declare(strict_types=1);

return [
    'name' => '015_create_destination_history',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS destination_history (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                short_link_id       BIGINT UNSIGNED NOT NULL,
                changed_by_user_id  BIGINT UNSIGNED NULL,
                old_target_url      TEXT            NULL,
                new_target_url      TEXT            NOT NULL,
                change_source       ENUM('user_edit','restore','system') NOT NULL DEFAULT 'user_edit',
                created_at          DATETIME        NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_dest_hist_short_link (short_link_id),
                INDEX idx_dest_hist_user       (changed_by_user_id),
                INDEX idx_dest_hist_created    (created_at),
                CONSTRAINT fk_dest_hist_short_link
                    FOREIGN KEY (short_link_id)      REFERENCES short_links(id) ON DELETE CASCADE,
                CONSTRAINT fk_dest_hist_user
                    FOREIGN KEY (changed_by_user_id) REFERENCES users(id)       ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
