<?php
declare(strict_types=1);

return [
    'name' => '009_create_audit_logs_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id       BIGINT UNSIGNED NULL,
                entity_type   VARCHAR(50)     NOT NULL,
                entity_id     BIGINT UNSIGNED NOT NULL,
                action        VARCHAR(100)    NOT NULL,
                metadata_json JSON            NULL,
                created_at    DATETIME        NOT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_logs_user_id (user_id),
                KEY idx_audit_logs_entity (entity_type, entity_id),
                CONSTRAINT fk_audit_logs_user_id
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    },
];
