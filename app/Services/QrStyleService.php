<?php
declare(strict_types=1);

class QrStyleService
{
    private const ALLOWED_LOGO_MIMES = ['image/png', 'image/jpeg', 'image/webp'];
    private const ALLOWED_LOGO_EXTS  = ['png', 'jpg', 'jpeg', 'webp'];

    public const MODULE_STYLES = ['square', 'gapped_square', 'circle'];

    public static function defaultStyle(): array
    {
        return [
            'foreground_color'       => '#000000',
            'background_color'       => '#FFFFFF',
            'background_transparent' => false,
            'module_style'           => 'square',
            'error_correction_level' => 'M',
            'logo_enabled'           => false,
            'logo_path'              => null,
            'logo_original_filename' => null,
            'logo_mime_type'         => null,
            'logo_size_bytes'        => null,
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
            'foreground_color'       => $row['foreground_color']        ?? '#000000',
            'background_color'       => $row['background_color']        ?? '#FFFFFF',
            'background_transparent' => (bool) ($row['background_transparent'] ?? false),
            'module_style'           => self::normalizeModuleStyle($row['module_style'] ?? 'square'),
            'error_correction_level' => $row['error_correction_level']  ?? 'M',
            'logo_enabled'           => (bool) ($row['logo_enabled']    ?? false),
            'logo_path'              => $row['logo_path']               ?? null,
            'logo_original_filename' => $row['logo_original_filename']  ?? null,
            'logo_mime_type'         => $row['logo_mime_type']          ?? null,
            'logo_size_bytes'        => $row['logo_size_bytes'] !== null ? (int) $row['logo_size_bytes'] : null,
            'is_custom'              => true,
        ];
    }

    /**
     * Normalize a module style string. Falls back to 'square' for invalid values.
     */
    public static function normalizeModuleStyle(string $style): string
    {
        return in_array($style, self::MODULE_STYLES, true) ? $style : 'square';
    }

    /**
     * Validate a module style choice.
     * Returns an error string if invalid, null if valid.
     */
    public static function validateModuleStyle(string $style): ?string
    {
        if (!in_array($style, self::MODULE_STYLES, true)) {
            return 'Module style must be one of: ' . implode(', ', self::MODULE_STYLES) . '.';
        }
        return null;
    }

    /**
     * Upsert foreground/background colors, transparent-background flag, and module style.
     *
     * ECL policy:
     *   logo active                              → H
     *   any custom color / transparent / shape   → Q
     *   all defaults and no logo                 → row is deleted (equivalent to no style row, ECL M)
     */
    public static function saveColors(
        int    $qrId,
        string $foreground,
        string $background,
        bool   $transparent = false,
        string $moduleStyle = 'square'
    ): void {
        $moduleStyle = self::normalizeModuleStyle($moduleStyle);

        $hasCustomStyle =
            $foreground   !== '#000000'
            || $background !== '#FFFFFF'
            || $transparent
            || $moduleStyle !== 'square';

        $existing  = self::getForQr($qrId);
        $logoActive = (bool) ($existing['logo_enabled'] ?? false);

        // No logo and all-defaults → drop the row so this QR is "no style" again.
        if (!$logoActive && !$hasCustomStyle) {
            Database::get()->prepare(
                "DELETE FROM qr_code_styles WHERE qr_code_id = ?"
            )->execute([$qrId]);
            return;
        }

        $newEcl = $logoActive ? 'H' : ($hasCustomStyle ? 'Q' : 'M');

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO qr_code_styles
                (qr_code_id, foreground_color, background_color, background_transparent,
                 module_style, error_correction_level, logo_enabled, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
            ON DUPLICATE KEY UPDATE
                foreground_color       = VALUES(foreground_color),
                background_color       = VALUES(background_color),
                background_transparent = VALUES(background_transparent),
                module_style           = VALUES(module_style),
                error_correction_level = IF(logo_enabled = 1, 'H', VALUES(error_correction_level)),
                updated_at             = VALUES(updated_at)
        ")->execute([$qrId, $foreground, $background, (int) $transparent, $moduleStyle, $newEcl, $now, $now]);
    }

    /**
     * Delete the custom style row entirely, including any uploaded logo file.
     */
    public static function reset(int $qrId): void
    {
        $existing = self::getForQr($qrId);

        Database::get()->prepare(
            "DELETE FROM qr_code_styles WHERE qr_code_id = ?"
        )->execute([$qrId]);

        if ($existing['logo_path'] !== null) {
            $filePath = self::logoFilePath($existing['logo_path']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // ── Logo ──────────────────────────────────────────────────────────────────

    /**
     * Returns the logo storage directory, creating it if necessary.
     */
    public static function logoStorageDir(): string
    {
        $dir = STORAGE_PATH . '/qr-logos';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create logo storage directory.');
        }
        return $dir;
    }

    /**
     * Returns the full filesystem path for a stored logo filename.
     * Does not create the directory.
     */
    public static function logoFilePath(string $filename): string
    {
        return STORAGE_PATH . '/qr-logos/' . basename($filename);
    }

    /**
     * Validate a logo upload against entitlement limits and allowed file types.
     * Returns an array of error strings; empty means valid.
     */
    public static function validateLogoUpload(array $file, int $userId): array
    {
        $maxKb = (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0);
        if ($maxKb <= 0) {
            return ['Logo upload is not available on your current plan.'];
        }

        $errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errCode === UPLOAD_ERR_NO_FILE) {
            return ['Please choose a logo image to upload.'];
        }

        // Surface specific upload-state errors before falling back to the
        // "size === 0 means no upload" heuristic — otherwise an oversized
        // upload (UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE), which arrives
        // with size = 0, would be misreported as "please choose a file".
        if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
            return ['Logo image is too large for your current plan.'];
        }

        if ($errCode !== UPLOAD_ERR_OK) {
            return ['Upload failed. Please try again.'];
        }

        if (($file['size'] ?? 0) === 0) {
            return ['Please choose a logo image to upload.'];
        }

        if ((int) $file['size'] > $maxKb * 1024) {
            return ['Logo image is too large for your current plan.'];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_LOGO_EXTS, true)) {
            return ['Logo must be a PNG, JPG, or WEBP image.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_LOGO_MIMES, true)) {
            return ['Logo must be a PNG, JPG, or WEBP image.'];
        }

        if (@getimagesize($file['tmp_name']) === false) {
            return ['The uploaded file does not appear to be a valid image.'];
        }

        return [];
    }

    /**
     * Move the uploaded logo into storage and upsert the style row.
     * Deletes the previous logo file if one existed. Sets ECL=H.
     * Returns metadata for audit logging.
     */
    public static function saveLogo(int $qrId, array $file): array
    {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $dir      = self::logoStorageDir();
        $filename = 'qr-' . $qrId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $dir . '/' . $filename;

        $existing    = self::getForQr($qrId);
        $oldLogoPath = ($existing['logo_path'] !== null)
            ? self::logoFilePath($existing['logo_path'])
            : null;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Failed to save uploaded logo file.');
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            INSERT INTO qr_code_styles
                (qr_code_id, foreground_color, background_color, error_correction_level,
                 logo_path, logo_original_filename, logo_mime_type, logo_size_bytes,
                 logo_enabled, created_at, updated_at)
            VALUES (?, ?, ?, 'H', ?, ?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                logo_path              = VALUES(logo_path),
                logo_original_filename = VALUES(logo_original_filename),
                logo_mime_type         = VALUES(logo_mime_type),
                logo_size_bytes        = VALUES(logo_size_bytes),
                logo_enabled           = 1,
                error_correction_level = 'H',
                updated_at             = VALUES(updated_at)
        ")->execute([
            $qrId,
            $existing['foreground_color'] ?? '#000000',
            $existing['background_color'] ?? '#FFFFFF',
            $filename,
            $file['name'],
            $mimeType,
            (int) $file['size'],
            $now,
            $now,
        ]);

        if ($oldLogoPath !== null && file_exists($oldLogoPath)) {
            @unlink($oldLogoPath);
        }

        return [
            'original_filename' => $file['name'],
            'mime_type'         => $mimeType,
            'size_bytes'        => (int) $file['size'],
        ];
    }

    /**
     * Remove the logo from a QR style row and delete the logo file.
     * Adjusts ECL to Q (custom colors remain) or M (no custom colors).
     */
    public static function removeLogo(int $qrId): void
    {
        $existing = self::getForQr($qrId);

        if (!$existing['is_custom'] || !$existing['logo_enabled']) {
            return;
        }

        $hasCustomStyle = $existing['foreground_color'] !== '#000000'
                       || $existing['background_color']  !== '#FFFFFF'
                       || ($existing['background_transparent'] ?? false)
                       || (($existing['module_style'] ?? 'square') !== 'square');
        $newEcl = $hasCustomStyle ? 'Q' : 'M';

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            UPDATE qr_code_styles
            SET logo_path              = NULL,
                logo_original_filename = NULL,
                logo_mime_type         = NULL,
                logo_size_bytes        = NULL,
                logo_enabled           = 0,
                error_correction_level = ?,
                updated_at             = ?
            WHERE qr_code_id = ?
        ")->execute([$newEcl, $now, $qrId]);

        if ($existing['logo_path'] !== null) {
            $filePath = self::logoFilePath($existing['logo_path']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Returns the active ECL string from a style array: 'H', 'Q', or 'M'.
     */
    public static function currentErrorCorrectionForStyle(array $style): string
    {
        return $style['error_correction_level'] ?? 'M';
    }

    // ── Color validation ──────────────────────────────────────────────────────

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
