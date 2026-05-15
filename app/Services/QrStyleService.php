<?php
declare(strict_types=1);

class QrStyleService
{
    public static function defaultStyle(): array
    {
        return [
            'foreground_color'       => '#000000',
            'background_color'       => '#FFFFFF',
            'error_correction_level' => 'M',
            'logo_enabled'           => false,
            'logo_path'              => null,
            'is_custom'              => false,
        ];
    }

    /**
     * Returns the effective style for a QR code.
     * Falls back to defaultStyle() if no row exists.
     */
    public static function getForQr(int $qrId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM qr_code_styles WHERE qr_code_id = ? LIMIT 1"
        );
        $stmt->execute([$qrId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return self::defaultStyle();
        }

        return [
            'foreground_color'       => $row['foreground_color']       ?? '#000000',
            'background_color'       => $row['background_color']       ?? '#FFFFFF',
            'error_correction_level' => $row['error_correction_level'] ?? 'M',
            'logo_enabled'           => (bool) ($row['logo_enabled']   ?? false),
            'logo_path'              => $row['logo_path']              ?? null,
            'is_custom'              => true,
        ];
    }

    /**
     * Upsert foreground/background colors.
     * Custom colors always use error_correction_level = Q for improved resilience.
     */
    public static function saveColors(int $qrId, string $foreground, string $background): void
    {
        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO qr_code_styles
                (qr_code_id, foreground_color, background_color, error_correction_level,
                 logo_enabled, created_at, updated_at)
            VALUES (?, ?, ?, 'Q', 0, ?, ?)
            ON DUPLICATE KEY UPDATE
                foreground_color       = VALUES(foreground_color),
                background_color       = VALUES(background_color),
                error_correction_level = 'Q',
                updated_at             = VALUES(updated_at)
        ")->execute([$qrId, $foreground, $background, $now, $now]);
    }

    /**
     * Remove the custom style row, reverting the QR to black-on-white defaults.
     */
    public static function reset(int $qrId): void
    {
        Database::get()->prepare(
            "DELETE FROM qr_code_styles WHERE qr_code_id = ?"
        )->execute([$qrId]);
    }

    /**
     * Validate foreground and background colors.
     * Returns an array of error strings; empty means valid.
     */
    public static function validateColors(string $foreground, string $background): array
    {
        $errors = [];

        $fg = self::normalizeHexColor($foreground);
        if ($fg === null) {
            $errors[] = 'Foreground color must be a valid hex color, such as #000000.';
        }

        $bg = self::normalizeHexColor($background);
        if ($bg === null) {
            $errors[] = 'Background color must be a valid hex color, such as #FFFFFF.';
        }

        if ($fg !== null && $bg !== null) {
            if ($fg === $bg) {
                $errors[] = 'Foreground and background colors must be different.';
            } elseif (self::contrastRatio($fg, $bg) < 3.0) {
                $errors[] = 'The selected colors do not have enough contrast for reliable QR scanning.';
            }
        }

        return $errors;
    }

    /**
     * Normalize a hex color string to uppercase #RRGGBB.
     * Returns null if the value is not a valid 6-digit hex color.
     */
    public static function normalizeHexColor(string $color): ?string
    {
        $color = trim($color);
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return null;
        }
        return strtoupper($color);
    }

    /**
     * Compute WCAG contrast ratio between two hex colors.
     * Returns a value >= 1.0; black/white is 21.0.
     */
    public static function contrastRatio(string $foreground, string $background): float
    {
        $l1 = self::relativeLuminance($foreground);
        $l2 = self::relativeLuminance($background);

        if ($l1 < $l2) {
            [$l1, $l2] = [$l2, $l1];
        }

        return ($l1 + 0.05) / ($l2 + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        $r = (int) hexdec(substr($hex, 1, 2));
        $g = (int) hexdec(substr($hex, 3, 2));
        $b = (int) hexdec(substr($hex, 5, 2));

        $linearize = static function (int $c): float {
            $srgb = $c / 255.0;
            return $srgb <= 0.04045
                ? $srgb / 12.92
                : (($srgb + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linearize($r) + 0.7152 * $linearize($g) + 0.0722 * $linearize($b);
    }
}
