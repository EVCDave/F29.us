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
 */
class QrCodeService
{
    public static function generatePng(string $content, int $size = 300): string
    {
        self::requireLibrary();

        $result = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->data($content)
            ->size($size)
            ->margin(10)
            ->build();

        return $result->getString();
    }

    public static function generateSvg(string $content, int $size = 300): string
    {
        self::requireLibrary();

        $result = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\SvgWriter())
            ->data($content)
            ->size($size)
            ->margin(10)
            ->build();

        return $result->getString();
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
