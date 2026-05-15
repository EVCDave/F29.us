<?php
declare(strict_types=1);

return [
    'name' => '025_create_qr_code_styles',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_code_styles (
                id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                qr_code_id              BIGINT UNSIGNED NOT NULL,
                foreground_color        CHAR(7)         NULL,
                background_color        CHAR(7)         NULL,
                error_correction_level  ENUM('L','M','Q','H') NOT NULL DEFAULT 'M',
                logo_path               VARCHAR(255)    NULL,
                logo_original_filename  VARCHAR(255)    NULL,
                logo_mime_type          VARCHAR(100)    NULL,
                logo_size_bytes         INT UNSIGNED    NULL,
                logo_enabled            TINYINT(1)      NOT NULL DEFAULT 0,
                created_at              DATETIME        NOT NULL,
                updated_at              DATETIME        NOT NULL,

                UNIQUE KEY uq_qr_code_styles_qr_code_id (qr_code_id),
                CONSTRAINT fk_qr_code_styles_qr_code
                    FOREIGN KEY (qr_code_id) REFERENCES qr_codes (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
