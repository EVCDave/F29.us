<?php
declare(strict_types=1);

return [
    'name' => '034_create_contact_messages_table',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_messages (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id             BIGINT UNSIGNED NULL,

                name                VARCHAR(200)    NOT NULL,
                email               VARCHAR(255)    NOT NULL,
                category            VARCHAR(50)     NOT NULL,
                subject             VARCHAR(200)    NOT NULL,
                message             TEXT            NOT NULL,

                status              ENUM('new','reviewed','closed') NOT NULL DEFAULT 'new',

                ip_hash             CHAR(64)        NULL,
                user_agent          VARCHAR(1000)   NULL,

                handled_at          DATETIME        NULL,
                handled_by_user_id  BIGINT UNSIGNED NULL,
                admin_note          TEXT            NULL,

                created_at          DATETIME        NOT NULL,

                KEY idx_contact_messages_user_id           (user_id),
                KEY idx_contact_messages_status_created    (status, created_at),
                KEY idx_contact_messages_category_created  (category, created_at),
                KEY idx_contact_messages_email_created     (email, created_at),
                KEY idx_contact_messages_ip_created        (ip_hash, created_at),

                CONSTRAINT fk_contact_messages_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_contact_messages_handled_by
                    FOREIGN KEY (handled_by_user_id) REFERENCES users (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
