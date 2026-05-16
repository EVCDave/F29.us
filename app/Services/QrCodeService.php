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
 * An optional $style array controls colors, error correction level, and
 * module shape (square, gapped_square, circle). Pass the return value of
 * QrStyleService::getForQr() or null for defaults.
 *
 * When module_style is "square" (default), the endroid writers are used
 * directly so output is byte-identical to prior behavior. When module_style
 * is "gapped_square" or "circle", a custom renderer walks the endroid Matrix
 * and emits per-module shapes. Finder-pattern modules (top-left, top-right,
 * bottom-left 7x7 blocks) always render as full squares for scan reliability.
 */
class QrCodeService
{
    private const FINDER_SIZE = 7;
    private const MODULE_SHAPE_SCALE = 0.8;

    public static function generatePng(string $content, int $size = 300, ?array $style = null): string
    {
        self::requireLibrary();

        if (self::needsCustomRenderer($style)) {
            return self::renderCustomPng($content, $size, $style);
        }

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

        if (self::needsCustomRenderer($style)) {
            return self::renderCustomSvg($content, $size, $style);
        }

        $builder = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\SvgWriter())
            ->data($content)
            ->size($size)
            ->margin(10);

        self::applyStyle($builder, $style, $size);

        return $builder->build()->getString();
    }

    private static function needsCustomRenderer(?array $style): bool
    {
        $moduleStyle = $style['module_style'] ?? 'square';
        return $moduleStyle === 'gapped_square' || $moduleStyle === 'circle';
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

    // ── Custom module rendering ──────────────────────────────────────────────

    /**
     * Build the matrix the same way endroid would, so module coordinates and
     * pixel positions line up exactly with the standard renderer output.
     */
    private static function buildMatrix(string $content, int $size, ?array $style): \Endroid\QrCode\Matrix\Matrix
    {
        $ecl = $style['error_correction_level'] ?? 'M';

        $result = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->data($content)
            ->size($size)
            ->margin(10)
            ->errorCorrectionLevel(self::mapEcl($ecl))
            ->build();

        return $result->getMatrix();
    }

    private static function renderCustomSvg(string $content, int $size, array $style): string
    {
        $matrix     = self::buildMatrix($content, $size, $style);
        $blockCount = $matrix->getBlockCount();
        $blockSize  = $matrix->getBlockSize();
        $marginLeft = $matrix->getMarginLeft();
        $outerSize  = (int) round($matrix->getOuterSize());

        $fg          = $style['foreground_color']       ?? '#000000';
        $bg          = $style['background_color']       ?? '#FFFFFF';
        $transparent = (bool) ($style['background_transparent'] ?? false);
        $moduleStyle = $style['module_style'] ?? 'square';

        $fgAttr = htmlspecialchars($fg, ENT_QUOTES, 'UTF-8');

        $parts = [];
        $parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $parts[] = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" '
                 . 'width="' . $outerSize . '" height="' . $outerSize . '" '
                 . 'viewBox="0 0 ' . $outerSize . ' ' . $outerSize . '">';

        if (!$transparent) {
            $parts[] = '<rect x="0" y="0" width="' . $outerSize . '" height="' . $outerSize
                     . '" fill="' . htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') . '"/>';
        }

        $inset    = $blockSize * ((1.0 - self::MODULE_SHAPE_SCALE) / 2.0);
        $drawSize = $blockSize * self::MODULE_SHAPE_SCALE;
        $radius   = $drawSize / 2.0;

        for ($y = 0; $y < $blockCount; $y++) {
            for ($x = 0; $x < $blockCount; $x++) {
                // Endroid getBlockValue expects row, column; our loop variables are x/y.
                if ($matrix->getBlockValue($y, $x) === 0) {
                    continue;
                }

                $px = $marginLeft + $x * $blockSize;
                $py = $marginLeft + $y * $blockSize;

                if (self::isFinderPattern($x, $y, $blockCount)) {
                    $parts[] = '<rect x="' . self::fmt($px) . '" y="' . self::fmt($py)
                             . '" width="' . self::fmt($blockSize) . '" height="' . self::fmt($blockSize)
                             . '" fill="' . $fgAttr . '"/>';
                    continue;
                }

                if ($moduleStyle === 'circle') {
                    $cx = $px + $blockSize / 2.0;
                    $cy = $py + $blockSize / 2.0;
                    $parts[] = '<circle cx="' . self::fmt($cx) . '" cy="' . self::fmt($cy)
                             . '" r="' . self::fmt($radius) . '" fill="' . $fgAttr . '"/>';
                } else { // gapped_square
                    $parts[] = '<rect x="' . self::fmt($px + $inset) . '" y="' . self::fmt($py + $inset)
                             . '" width="' . self::fmt($drawSize) . '" height="' . self::fmt($drawSize)
                             . '" fill="' . $fgAttr . '"/>';
                }
            }
        }

        // Logo overlay (after modules so it sits on top)
        if (($style['logo_enabled'] ?? false) && isset($style['logo_path'])) {
            $logoPath = QrStyleService::logoFilePath($style['logo_path']);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $logoWidth   = max(1, (int) round($outerSize * $logoPercent / 100));
                $logoX       = ($outerSize - $logoWidth) / 2.0;
                $mime        = $style['logo_mime_type'] ?? 'image/png';
                $logoBytes   = @file_get_contents($logoPath);
                if ($logoBytes !== false) {
                    $b64 = base64_encode($logoBytes);
                    $parts[] = '<image x="' . self::fmt($logoX) . '" y="' . self::fmt($logoX)
                             . '" width="' . $logoWidth . '" height="' . $logoWidth
                             . '" preserveAspectRatio="xMidYMid meet" '
                             . 'href="data:' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8')
                             . ';base64,' . $b64 . '"/>';
                }
            }
        }

        $parts[] = '</svg>';
        return implode('', $parts);
    }

    private static function renderCustomPng(string $content, int $size, array $style): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('PHP GD extension is required for PNG output.');
        }

        $matrix     = self::buildMatrix($content, $size, $style);
        $blockCount = $matrix->getBlockCount();
        $blockSize  = $matrix->getBlockSize();
        $marginLeft = $matrix->getMarginLeft();
        $outerSize  = (int) round($matrix->getOuterSize());

        $fg          = $style['foreground_color']       ?? '#000000';
        $bg          = $style['background_color']       ?? '#FFFFFF';
        $transparent = (bool) ($style['background_transparent'] ?? false);
        $moduleStyle = $style['module_style'] ?? 'square';

        $im = imagecreatetruecolor($outerSize, $outerSize);

        // Enable alpha
        imagealphablending($im, false);
        imagesavealpha($im, true);

        if ($transparent) {
            $bgColor = imagecolorallocatealpha($im, 255, 255, 255, 127);
        } else {
            $bgColor = imagecolorallocate(
                $im,
                (int) hexdec(substr($bg, 1, 2)),
                (int) hexdec(substr($bg, 3, 2)),
                (int) hexdec(substr($bg, 5, 2))
            );
        }
        imagefilledrectangle($im, 0, 0, $outerSize - 1, $outerSize - 1, $bgColor);

        // Re-enable alpha blending so foreground shapes blend over background
        imagealphablending($im, true);

        $fgColor = imagecolorallocate(
            $im,
            (int) hexdec(substr($fg, 1, 2)),
            (int) hexdec(substr($fg, 3, 2)),
            (int) hexdec(substr($fg, 5, 2))
        );

        $inset    = $blockSize * ((1.0 - self::MODULE_SHAPE_SCALE) / 2.0);
        $drawSize = $blockSize * self::MODULE_SHAPE_SCALE;

        for ($y = 0; $y < $blockCount; $y++) {
            for ($x = 0; $x < $blockCount; $x++) {
                // Endroid getBlockValue expects row, column; our loop variables are x/y.
                if ($matrix->getBlockValue($y, $x) === 0) {
                    continue;
                }

                $px = $marginLeft + $x * $blockSize;
                $py = $marginLeft + $y * $blockSize;

                if (self::isFinderPattern($x, $y, $blockCount)) {
                    imagefilledrectangle(
                        $im,
                        (int) round($px),
                        (int) round($py),
                        (int) round($px + $blockSize) - 1,
                        (int) round($py + $blockSize) - 1,
                        $fgColor
                    );
                    continue;
                }

                if ($moduleStyle === 'circle') {
                    $cx = (int) round($px + $blockSize / 2.0);
                    $cy = (int) round($py + $blockSize / 2.0);
                    $d  = max(1, (int) round($drawSize));
                    imagefilledellipse($im, $cx, $cy, $d, $d, $fgColor);
                } else { // gapped_square
                    $x1 = (int) round($px + $inset);
                    $y1 = (int) round($py + $inset);
                    $x2 = (int) round($px + $inset + $drawSize) - 1;
                    $y2 = (int) round($py + $inset + $drawSize) - 1;
                    if ($x2 < $x1) { $x2 = $x1; }
                    if ($y2 < $y1) { $y2 = $y1; }
                    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $fgColor);
                }
            }
        }

        // Logo overlay (after modules so it sits on top)
        if (($style['logo_enabled'] ?? false) && isset($style['logo_path'])) {
            $logoPath = QrStyleService::logoFilePath($style['logo_path']);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $logoWidth   = max(1, (int) round($outerSize * $logoPercent / 100));
                $logoSrc     = self::loadImage($logoPath);
                if ($logoSrc !== null) {
                    $logoX = (int) round(($outerSize - $logoWidth) / 2.0);
                    imagecopyresampled(
                        $im, $logoSrc,
                        $logoX, $logoX, 0, 0,
                        $logoWidth, $logoWidth,
                        imagesx($logoSrc), imagesy($logoSrc)
                    );
                    imagedestroy($logoSrc);
                }
            }
        }

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    private static function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        switch ($info[2]) {
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($path);
                break;
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_WEBP:
                $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
                break;
            default:
                return null;
        }
        return $img === false ? null : $img;
    }

    /**
     * Returns true for module coordinates that fall inside any of the three
     * finder-pattern 7x7 blocks. Those modules must render as full squares
     * for scan reliability.
     */
    private static function isFinderPattern(int $x, int $y, int $blockCount): bool
    {
        $end = $blockCount - self::FINDER_SIZE;
        // top-left
        if ($x < self::FINDER_SIZE && $y < self::FINDER_SIZE) return true;
        // top-right
        if ($x >= $end && $y < self::FINDER_SIZE)             return true;
        // bottom-left
        if ($x < self::FINDER_SIZE && $y >= $end)             return true;
        return false;
    }

    /** Format a float for SVG output, trimming trailing zeros for compactness. */
    private static function fmt(float $v): string
    {
        $s = number_format($v, 3, '.', '');
        return rtrim(rtrim($s, '0'), '.');
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
