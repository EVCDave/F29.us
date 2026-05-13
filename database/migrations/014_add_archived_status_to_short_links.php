<?php
declare(strict_types=1);

return [
    'name' => '014_add_archived_status_to_short_links',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE short_links
            MODIFY COLUMN status ENUM('active','paused','disabled','archived')
                NOT NULL DEFAULT 'active'
        ");
    },
];
