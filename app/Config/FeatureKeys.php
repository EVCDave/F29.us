<?php
declare(strict_types=1);

/**
 * Central registry of known built-in feature keys and their required value types.
 * Used to guard against accidental type mismatches when admins add or update features.
 */
class FeatureKeys
{
    private const BUILTIN = [
        'max_qr_codes'              => 'int',
        'analytics_retention_days'  => 'int',
        'can_create_qr'             => 'bool',
        'can_edit_destination'      => 'bool',
        'can_export_png'            => 'bool',
        'can_export_svg'            => 'bool',
        'can_pause_links'           => 'bool',
        'can_use_custom_slug'       => 'bool',
        'custom_slug_min_length'    => 'int',
        'custom_slug_max_length'    => 'int',
        'can_use_branded_qr_styles' => 'bool',
        'can_export_analytics'      => 'bool',
        'max_team_members'          => 'int',
        'can_customize_qr_colors'   => 'bool',
        'can_upload_qr_logo'        => 'bool',
        'qr_logo_max_size_kb'       => 'int',
        'qr_logo_max_percent'       => 'int',
    ];

    public static function isBuiltin(string $key): bool
    {
        return isset(self::BUILTIN[$key]);
    }

    public static function expectedType(string $key): ?string
    {
        return self::BUILTIN[$key] ?? null;
    }

    /**
     * Returns an error string if a known key is given the wrong value type, null otherwise.
     * Unknown keys always pass (return null) — no constraint on custom keys.
     */
    public static function validateType(string $key, string $valueType): ?string
    {
        $expected = self::BUILTIN[$key] ?? null;
        if ($expected === null || $expected === $valueType) {
            return null;
        }
        return sprintf(
            'Built-in feature key "%s" requires value type "%s" (got "%s").',
            $key, $expected, $valueType
        );
    }
}
