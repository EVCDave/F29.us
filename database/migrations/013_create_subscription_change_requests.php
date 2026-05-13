<?php
declare(strict_types=1);

return [
    'name' => '013_create_subscription_change_requests',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE subscription_change_requests (
                id                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                user_id               BIGINT UNSIGNED  NOT NULL,
                current_plan_id       BIGINT UNSIGNED  NULL,
                requested_plan_id     BIGINT UNSIGNED  NOT NULL,
                status                ENUM('pending','approved','denied','canceled')
                                          NOT NULL DEFAULT 'pending',
                requested_at          DATETIME         NOT NULL,
                reviewed_at           DATETIME         NULL,
                reviewed_by_user_id   BIGINT UNSIGNED  NULL,
                note                  TEXT             NULL,
                PRIMARY KEY (id),
                INDEX idx_scr_user          (user_id),
                INDEX idx_scr_requested_plan(requested_plan_id),
                INDEX idx_scr_status        (status),
                INDEX idx_scr_requested_at  (requested_at),
                CONSTRAINT fk_scr_user         FOREIGN KEY (user_id)
                    REFERENCES users (id),
                CONSTRAINT fk_scr_current_plan FOREIGN KEY (current_plan_id)
                    REFERENCES plans (id),
                CONSTRAINT fk_scr_req_plan     FOREIGN KEY (requested_plan_id)
                    REFERENCES plans (id),
                CONSTRAINT fk_scr_reviewer     FOREIGN KEY (reviewed_by_user_id)
                    REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
];
