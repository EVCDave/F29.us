<?php
declare(strict_types=1);

/**
 * Generates QR code images on demand.
 *
 * Requires: composer install (endroid/qr-code ^5.0)
 * PNG output additionally requires the PHP GD extension.
 *
 * The content encoded in each QR code is the managed short URL:
 *   https://f29.us/{slug}
 * not the destination URL. This keeps the QR code static even when
 * the destination changes.
 *
 * An optional $style array controls colors and error correction level.
 * Pass the return value of QrStyleService::getForQr() or null for defaults.
 */
class QrCodeService
{
    public static function generatePng(string $content, int $size = 300, ?array $style = null): string
    {
        self::requireLibrary();

        $builder = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->data($content)
            ->size($size)
            ->margin(10);

        self::applyStyle($builder, $style, $size);

        return $builder->build()->getString();
    }

    public static function generateSvg(string $content, int $size = 300, ?array $style = null): string
    {
        self::requireLibrary();

        $builder = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\SvgWriter())
            ->data($content)
            ->size($size)
            ->margin(10);

        self::applyStyle($builder, $style, $size);

        return $builder->build()->getString();
    }

    private static function applyStyle(object $builder, ?array $style, int $size): void
    {
        if ($style === null) {
            return;
        }

        $fg          = $style['foreground_color']       ?? null;
        $bg          = $style['background_color']       ?? null;
        $transparent = (bool) ($style['background_transparent'] ?? false);
        $ecl         = $style['error_correction_level'] ?? 'M';

        if ($fg !== null) {
            $builder->foregroundColor(self::parseHexColor($fg));
        }

        if ($transparent) {
            // Alpha 127 = fully transparent in GD / endroid Color scale (0=opaque, 127=transparent).
            $builder->backgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255, 127));
        } elseif ($bg !== null) {
            $builder->backgroundColor(self::parseHexColor($bg));
        }
        $builder->errorCorrectionLevel(self::mapEcl($ecl));

        if (($style['logo_enabled'] ?? false) && isset($style['logo_path'])) {
            $logoPath = QrStyleService::logoFilePath($style['logo_path']);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $logoWidth   = max(1, (int) round($size * $logoPercent / 100));
                $builder->logoPath($logoPath)->logoResizeToWidth($logoWidth);
            }
        }
    }

    private static function parseHexColor(string $hex): \Endroid\QrCode\Color\Color
    {
        return new \Endroid\QrCode\Color\Color(
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2))
        );
    }

    private static function mapEcl(string $level): \Endroid\QrCode\ErrorCorrectionLevel
    {
        return match ($level) {
            'L'     => \Endroid\QrCode\ErrorCorrectionLevel::Low,
            'Q'     => \Endroid\QrCode\ErrorCorrectionLevel::Quartile,
            'H'     => \Endroid\QrCode\ErrorCorrectionLevel::High,
            default => \Endroid\QrCode\ErrorCorrectionLevel::Medium,
        };
    }

    private static function requireLibrary(): void
    {
        if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            throw new RuntimeException(
                'QR code library not installed. Run: composer install'
            );
        }
    }
}
