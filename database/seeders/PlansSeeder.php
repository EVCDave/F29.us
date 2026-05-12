<?php
declare(strict_types=1);

return [
    'name' => 'PlansSeeder',
    'run'  => function (PDO $pdo): void {
        $plans = [
            [
                'internal_name'       => 'free_v1',
                'display_name'        => 'Free',
                'description'         => 'Get started for free with basic QR code features.',
                'monthly_price_cents' => 0,
                'yearly_price_cents'  => 0,
                'is_public'           => 1,
                'sort_order'          => 1,
            ],
            [
                'internal_name'       => 'starter_v1',
                'display_name'        => 'Starter',
                'description'         => 'More QR codes, longer analytics retention, and custom slugs.',
                'monthly_price_cents' => null,
                'yearly_price_cents'  => null,
                'is_public'           => 1,
                'sort_order'          => 2,
            ],
            [
                'internal_name'       => 'pro_v1',
                'display_name'        => 'Pro',
                'description'         => 'Advanced features for professionals, including branded QR styles and analytics export.',
                'monthly_price_cents' => null,
                'yearly_price_cents'  => null,
                'is_public'           => 1,
                'sort_order'          => 3,
            ],
            [
                'internal_name'       => 'team_v1',
                'display_name'        => 'Team',
                'description'         => 'Everything in Pro, plus team collaboration.',
                'monthly_price_cents' => null,
                'yearly_price_cents'  => null,
                'is_public'           => 1,
                'sort_order'          => 4,
            ],
        ];

        $sql = "
            INSERT INTO plans
                (internal_name, display_name, description, monthly_price_cents, yearly_price_cents,
                 currency_code, is_public, is_active, is_legacy, sort_order, created_at, updated_at)
            VALUES
                (:internal_name, :display_name, :description, :monthly_price_cents, :yearly_price_cents,
                 'USD', :is_public, 1, 0, :sort_order, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                display_name        = VALUES(display_name),
                description         = VALUES(description),
                monthly_price_cents = VALUES(monthly_price_cents),
                yearly_price_cents  = VALUES(yearly_price_cents),
                is_public           = VALUES(is_public),
                sort_order          = VALUES(sort_order),
                updated_at          = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($plans as $plan) {
            $stmt->execute($plan);
        }
    },
];
