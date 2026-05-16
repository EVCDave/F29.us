<?php
declare(strict_types=1);

return [
    'name' => 'PlanFeaturesSeeder',
    'run'  => function (PDO $pdo): void {
        // Index plan IDs by internal_name
        $stmt = $pdo->query("SELECT id, internal_name FROM plans");
        $planIds = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $planIds[$row['internal_name']] = (int) $row['id'];
        }

        foreach (['free_v1', 'starter_v1', 'pro_v1', 'team_v1'] as $name) {
            if (!isset($planIds[$name])) {
                throw new RuntimeException("Plan '{$name}' not found. Run PlansSeeder first.");
            }
        }

        // [plan_internal_name, feature_key, feature_value, value_type]
        $features = [
            // ── Free ────────────────────────────────────────────────────────
            ['free_v1', 'max_qr_codes',              '3',     'int'],
            ['free_v1', 'analytics_retention_days',  '30',    'int'],
            ['free_v1', 'can_create_qr',             'true',  'bool'],
            ['free_v1', 'can_edit_destination',      'true',  'bool'],
            ['free_v1', 'can_export_png',            'true',  'bool'],
            ['free_v1', 'can_export_svg',            'false', 'bool'],
            ['free_v1', 'can_pause_links',           'true',  'bool'],
            ['free_v1', 'can_use_custom_slug',       'false', 'bool'],
            ['free_v1', 'can_customize_qr_colors',   'false', 'bool'],
            ['free_v1', 'can_customize_qr_module_style', 'false', 'bool'],
            ['free_v1', 'can_upload_qr_logo',        'false', 'bool'],
            ['free_v1', 'qr_logo_max_size_kb',       '0',     'int'],
            ['free_v1', 'qr_logo_max_percent',       '0',     'int'],

            // ── Starter ──────────────────────────────────────────────────────
            ['starter_v1', 'max_qr_codes',             '20',   'int'],
            ['starter_v1', 'analytics_retention_days', '90',   'int'],
            ['starter_v1', 'can_create_qr',            'true', 'bool'],
            ['starter_v1', 'can_edit_destination',     'true', 'bool'],
            ['starter_v1', 'can_export_png',           'true', 'bool'],
            ['starter_v1', 'can_export_svg',           'true', 'bool'],
            ['starter_v1', 'can_pause_links',          'true', 'bool'],
            ['starter_v1', 'can_use_custom_slug',      'true', 'bool'],
            ['starter_v1', 'custom_slug_min_length',   '4',   'int'],
            ['starter_v1', 'custom_slug_max_length',   '32',  'int'],
            ['starter_v1', 'can_customize_qr_colors',  'true',  'bool'],
            ['starter_v1', 'can_customize_qr_module_style', 'true', 'bool'],
            ['starter_v1', 'can_upload_qr_logo',       'false', 'bool'],
            ['starter_v1', 'qr_logo_max_size_kb',      '0',     'int'],
            ['starter_v1', 'qr_logo_max_percent',      '0',     'int'],

            // ── Pro ──────────────────────────────────────────────────────────
            ['pro_v1', 'max_qr_codes',              '250',  'int'],
            ['pro_v1', 'analytics_retention_days',  '365',  'int'],
            ['pro_v1', 'can_create_qr',             'true', 'bool'],
            ['pro_v1', 'can_edit_destination',      'true', 'bool'],
            ['pro_v1', 'can_export_png',            'true', 'bool'],
            ['pro_v1', 'can_export_svg',            'true', 'bool'],
            ['pro_v1', 'can_pause_links',           'true', 'bool'],
            ['pro_v1', 'can_use_custom_slug',       'true', 'bool'],
            ['pro_v1', 'custom_slug_min_length',    '4',   'int'],
            ['pro_v1', 'custom_slug_max_length',    '32',  'int'],
            ['pro_v1', 'can_use_branded_qr_styles', 'true', 'bool'],
            ['pro_v1', 'can_export_analytics',      'true', 'bool'],
            ['pro_v1', 'can_customize_qr_colors',   'true',  'bool'],
            ['pro_v1', 'can_customize_qr_module_style', 'true', 'bool'],
            ['pro_v1', 'can_upload_qr_logo',        'true',  'bool'],
            ['pro_v1', 'qr_logo_max_size_kb',       '512',   'int'],
            ['pro_v1', 'qr_logo_max_percent',       '20',    'int'],

            // ── Team ─────────────────────────────────────────────────────────
            ['team_v1', 'max_qr_codes',              '1000', 'int'],
            ['team_v1', 'analytics_retention_days',  '365',  'int'],
            ['team_v1', 'can_create_qr',             'true', 'bool'],
            ['team_v1', 'can_edit_destination',      'true', 'bool'],
            ['team_v1', 'can_export_png',            'true', 'bool'],
            ['team_v1', 'can_export_svg',            'true', 'bool'],
            ['team_v1', 'can_pause_links',           'true', 'bool'],
            ['team_v1', 'can_use_custom_slug',       'true', 'bool'],
            ['team_v1', 'custom_slug_min_length',    '4',   'int'],
            ['team_v1', 'custom_slug_max_length',    '32',  'int'],
            ['team_v1', 'can_use_branded_qr_styles', 'true', 'bool'],
            ['team_v1', 'can_export_analytics',      'true', 'bool'],
            ['team_v1', 'max_team_members',          '10',  'int'],
            ['team_v1', 'can_customize_qr_colors',   'true',  'bool'],
            ['team_v1', 'can_customize_qr_module_style', 'true', 'bool'],
            ['team_v1', 'can_upload_qr_logo',        'true',  'bool'],
            ['team_v1', 'qr_logo_max_size_kb',       '1024',   'int'],
            ['team_v1', 'qr_logo_max_percent',       '25',    'int'],
        ];

        $sql = "
            INSERT INTO plan_features
                (plan_id, feature_key, feature_value, value_type, created_at, updated_at)
            VALUES
                (:plan_id, :feature_key, :feature_value, :value_type, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                feature_value = VALUES(feature_value),
                value_type    = VALUES(value_type),
                updated_at    = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($features as [$planName, $key, $value, $type]) {
            $stmt->execute([
                'plan_id'       => $planIds[$planName],
                'feature_key'   => $key,
                'feature_value' => $value,
                'value_type'    => $type,
            ]);
        }
    },
];
