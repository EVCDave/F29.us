<?php
declare(strict_types=1);

return [
    'name' => '026_add_background_transparent_to_qr_code_styles',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE qr_code_styles
            ADD COLUMN background_transparent TINYINT(1) NOT NULL DEFAULT 0
                AFTER background_color
        ");
    },
];
