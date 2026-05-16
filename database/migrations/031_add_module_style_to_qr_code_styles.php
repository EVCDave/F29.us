<?php
declare(strict_types=1);

return [
    'name' => '031_add_module_style_to_qr_code_styles',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE qr_code_styles
            ADD COLUMN module_style ENUM('square','gapped_square','circle') NOT NULL DEFAULT 'square'
                AFTER background_transparent
        ");
    },
];
