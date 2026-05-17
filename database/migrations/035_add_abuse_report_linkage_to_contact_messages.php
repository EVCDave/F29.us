<?php
declare(strict_types=1);

return [
    'name' => '035_add_abuse_report_linkage_to_contact_messages',
    'up'   => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE contact_messages
                ADD COLUMN reported_url           VARCHAR(2048)   NULL AFTER message,
                ADD COLUMN reported_domain        VARCHAR(255)    NULL AFTER reported_url,
                ADD COLUMN related_qr_code_id     BIGINT UNSIGNED NULL AFTER reported_domain,
                ADD COLUMN related_short_link_id  BIGINT UNSIGNED NULL AFTER related_qr_code_id,

                ADD KEY idx_contact_messages_related_qr          (related_qr_code_id),
                ADD KEY idx_contact_messages_related_short_link  (related_short_link_id),
                ADD KEY idx_contact_messages_reported_domain     (reported_domain),

                ADD CONSTRAINT fk_contact_messages_related_qr
                    FOREIGN KEY (related_qr_code_id) REFERENCES qr_codes (id)
                    ON DELETE SET NULL,
                ADD CONSTRAINT fk_contact_messages_related_short_link
                    FOREIGN KEY (related_short_link_id) REFERENCES short_links (id)
                    ON DELETE SET NULL
        ");
    },
];
