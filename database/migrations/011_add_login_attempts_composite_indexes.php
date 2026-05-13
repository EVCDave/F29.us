<?php
declare(strict_types=1);

return [
    'name' => '011_add_login_attempts_composite_indexes',
    'up'   => function (PDO $pdo): void {
        // Replace single-column email/ip indexes with composite indexes that
        // cover the actual throttle queries:
        //   WHERE email_normalized = ? AND success_flag = 0 AND attempted_at >= ?
        //   WHERE ip_hash          = ? AND success_flag = 0 AND attempted_at >= ?
        // The single-column attempted_at index is kept for the cleanup query
        //   DELETE WHERE attempted_at < ?
        $pdo->exec("
            ALTER TABLE login_attempts
                DROP  INDEX idx_login_attempts_email,
                DROP  INDEX idx_login_attempts_ip,
                ADD   INDEX idx_la_email_flag_at (email_normalized, success_flag, attempted_at),
                ADD   INDEX idx_la_ip_flag_at    (ip_hash,          success_flag, attempted_at)
        ");
    },
];
