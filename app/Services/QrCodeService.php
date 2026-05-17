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
 * When module_style is "square" (default), the standard endroid writer path
 * is used; PNG output is then passed through a final exact-size normalization
 * step (nearest-neighbor) to honor the caller-requested pixel size. When
 * module_style is "gapped_square" or "circle", a custom renderer walks the
 * endroid Matrix and emits per-module shapes. Finder-pattern modules
 * (top-left, top-right, bottom-left 7x7 blocks) always render as full squares
 * for scan reliability.
 */
class QrCodeService
{
    private const FINDER_SIZE = 7;
    private const MODULE_SHAPE_SCALE = 0.8;

    public static function generatePng(string $content, int $size = 300, ?array $style = null): string
    {
        self::requireLibrary();

        if (self::needsCustomRenderer($style)) {
            $png = self::renderCustomPng($content, $size, $style);
        } else {
            $builder = \Endroid\QrCode\Builder\Builder::create()
                ->writer(new \Endroid\QrCode\Writer\PngWriter())
                ->data($content)
                ->size($size)
                ->margin(10);

            self::applyStyle($builder, $style, $size);

            $png = $builder->build()->getString();
        }

        // Endroid rounds block sizes so actual output may differ from $size by a few px.
        // Force exact requested dimensions so downloads honor the user's selected size.
        return self::resamplePngToSize($png, $size);
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

        if (($style['logo_enabled'] ?? false) && self::resolveLogoPath($style) !== null) {
            $logoPath = self::resolveLogoPath($style);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $maxBox      = max(1, (int) round($size * $logoPercent / 100));
                $dims        = self::logoRenderDimensions($logoPath, $maxBox);

                // Set both width AND height so endroid renders the logo at the
                // aspect-correct size; otherwise the LogoPlacer may stretch a
                // non-square source to a square box.
                $builder->logoPath($logoPath);
                if ($dims !== null) {
                    [$logoW, $logoH] = $dims;
                    $builder->logoResizeToWidth($logoW)->logoResizeToHeight($logoH);
                } else {
                    // Source dimensions unreadable — fall back to width only.
                    $builder->logoResizeToWidth($maxBox);
                }
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
        // This function is only entered via needsCustomRenderer(), so module_style
        // is already 'gapped_square' or 'circle' — no 'square' fallback needed here.
        $moduleStyle = $style['module_style'];

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
        if (($style['logo_enabled'] ?? false) && self::resolveLogoPath($style) !== null) {
            $logoPath = self::resolveLogoPath($style);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $maxBox      = max(1, (int) round($outerSize * $logoPercent / 100));
                $dims        = self::logoRenderDimensions($logoPath, $maxBox);
                if ($dims === null) {
                    // Fallback to the (square) bounding box if source dims are unreadable.
                    $dims = [$maxBox, $maxBox];
                }
                [$logoW, $logoH] = $dims;
                $logoX = ($outerSize - $logoW) / 2.0;
                $logoY = ($outerSize - $logoH) / 2.0;

                $mime      = $style['logo_mime_type'] ?? 'image/png';
                $logoBytes = @file_get_contents($logoPath);
                if ($logoBytes !== false) {
                    $b64 = base64_encode($logoBytes);
                    $parts[] = '<image x="' . self::fmt($logoX) . '" y="' . self::fmt($logoY)
                             . '" width="' . $logoW . '" height="' . $logoH
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
        // This function is only entered via needsCustomRenderer(), so module_style
        // is already 'gapped_square' or 'circle' — no 'square' fallback needed here.
        $moduleStyle = $style['module_style'];

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
        if (($style['logo_enabled'] ?? false) && self::resolveLogoPath($style) !== null) {
            $logoPath = self::resolveLogoPath($style);
            if (file_exists($logoPath)) {
                $logoPercent = max(1.0, (float) ($style['logo_max_percent'] ?? 20));
                $maxBox      = max(1, (int) round($outerSize * $logoPercent / 100));
                $logoSrc     = self::loadImage($logoPath);
                if ($logoSrc !== null) {
                    $srcW = imagesx($logoSrc);
                    $srcH = imagesy($logoSrc);
                    // Aspect-ratio-preserving fit inside the maxBox × maxBox area.
                    $scale = min($maxBox / $srcW, $maxBox / $srcH, 1.0);
                    $dstW  = max(1, (int) round($srcW * $scale));
                    $dstH  = max(1, (int) round($srcH * $scale));
                    $logoX = (int) round(($outerSize - $dstW) / 2.0);
                    $logoY = (int) round(($outerSize - $dstH) / 2.0);
                    imagecopyresampled(
                        $im, $logoSrc,
                        $logoX, $logoY, 0, 0,
                        $dstW, $dstH,
                        $srcW, $srcH
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

    /**
     * Force a PNG to exact NxN dimensions. If the source already matches, return as-is.
     * Used to honor user-selected PNG download sizes despite endroid block-size rounding.
     *
     * Uses imagecopyresized (nearest-neighbor) rather than imagecopyresampled (bilinear),
     * because QR modules need crisp, hard edges — interpolated scaling blurs the module
     * boundaries and can hurt scan reliability.
     *
     * Memory hygiene: peeks the PNG IHDR header to detect already-correct dimensions
     * BEFORE allocating any GD image. At 4096px each truecolor RGBA canvas is ~67 MB
     * and src+dst together can exceed default 128M memory_limit, so the early skip
     * is what lets large sizes work on typical hosts.
     */
    private static function resamplePngToSize(string $pngBytes, int $size): string
    {
        // Cheap header peek first — avoids two ~67 MB GD allocations when not needed.
        [$srcW, $srcH] = self::peekPngDimensions($pngBytes);
        if ($srcW === $size && $srcH === $size) {
            return $pngBytes;
        }

        if (!function_exists('imagecreatefromstring')) {
            return $pngBytes;
        }

        $src = @imagecreatefromstring($pngBytes);
        if ($src === false) {
            throw new RuntimeException('Failed to decode rendered PNG for size normalization.');
        }
        // Re-derive in case the IHDR peek failed and returned (0, 0).
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW === $size && $srcH === $size) {
            imagedestroy($src);
            return $pngBytes;
        }

        $dst = @imagecreatetruecolor($size, $size);
        if ($dst === false) {
            imagedestroy($src);
            throw new RuntimeException('Failed to allocate ' . $size . 'x' . $size . ' canvas for PNG normalization.');
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparentFill = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size - 1, $size - 1, $transparentFill);

        if (!imagecopyresized($dst, $src, 0, 0, 0, 0, $size, $size, $srcW, $srcH)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new RuntimeException('Failed to resize PNG to ' . $size . 'x' . $size . '.');
        }

        // Free the source as soon as possible so the imagepng output buffer
        // doesn't have to share memory with two full canvases.
        imagedestroy($src);

        ob_start();
        $ok = @imagepng($dst);
        $out = (string) ob_get_clean();
        imagedestroy($dst);

        if (!$ok || $out === '') {
            throw new RuntimeException('Failed to encode resized PNG.');
        }

        return $out;
    }

    /**
     * Read width and height from a PNG byte string's IHDR chunk without
     * decoding the full image. Returns [0, 0] on any header malformation.
     *
     * PNG layout: 8-byte signature + 4-byte IHDR length + 4-byte "IHDR" + 4-byte width + 4-byte height.
     */
    private static function peekPngDimensions(string $pngBytes): array
    {
        if (strlen($pngBytes) < 24 || substr($pngBytes, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            return [0, 0];
        }
        $unpacked = @unpack('Nwidth/Nheight', substr($pngBytes, 16, 8));
        if (!is_array($unpacked) || !isset($unpacked['width'], $unpacked['height'])) {
            return [0, 0];
        }
        return [(int) $unpacked['width'], (int) $unpacked['height']];
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

    /**
     * Compute the rendered logo dimensions inside a square `$maxBoxSize` × `$maxBoxSize`
     * area, preserving the source image's aspect ratio. Never upscales beyond the
     * source's native pixel dimensions — upscaling a small logo just makes it blurry.
     *
     * Examples (max box = 256):
     *   1000×500  → 256×128
     *    500×1000 → 128×256
     *   1000×1000 → 256×256
     *    200×100  → 200×100  (smaller than the max box; left at native size)
     *
     * Returns [width, height] in pixels (both >= 1). Returns null when the file
     * cannot be read or has invalid dimensions, so callers can fall back gracefully.
     */
    private static function logoRenderDimensions(string $logoPath, int $maxBoxSize): ?array
    {
        if ($maxBoxSize < 1) {
            return null;
        }
        $info = @getimagesize($logoPath);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }
        $sourceWidth  = (int) $info[0];
        $sourceHeight = (int) $info[1];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return null;
        }

        $scale = min(
            $maxBoxSize / $sourceWidth,
            $maxBoxSize / $sourceHeight,
            1.0
        );

        return [
            max(1, (int) round($sourceWidth  * $scale)),
            max(1, (int) round($sourceHeight * $scale)),
        ];
    }

    /**
     * Resolve a usable filesystem path for the logo image, supporting both:
     *
     *   - Dynamic QR codes: $style['logo_path'] is a basename relative to
     *     STORAGE_PATH/qr-logos (the historic location, set by QrStyleService).
     *   - Static QR codes: $style['logo_absolute_path'] is a trusted absolute
     *     path under STORAGE_PATH/static-qr-logos, set by StaticQrLogoService
     *     after validating a session-tracked upload.
     *
     * Returns null if no logo path is set, the file doesn't exist, or — for
     * the static case — the supplied absolute path doesn't resolve under the
     * static logo storage directory. Realpath is used so any ".." traversal
     * collapses to a canonical path before the prefix check.
     */
    private static function resolveLogoPath(array $style): ?string
    {
        if (!empty($style['logo_absolute_path']) && is_string($style['logo_absolute_path'])) {
            $candidate = $style['logo_absolute_path'];
            $real      = realpath($candidate);
            if ($real === false || !is_file($real)) {
                return null;
            }
            $allowedRoots = [
                realpath(STORAGE_PATH . '/static-qr-logos'),
                realpath(STORAGE_PATH . '/qr-logos'),
            ];
            foreach ($allowedRoots as $root) {
                if ($root === false) continue;
                $rootSep = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (str_starts_with($real, $rootSep)) {
                    return $real;
                }
            }
            return null;
        }

        if (!empty($style['logo_path']) && is_string($style['logo_path'])) {
            $path = QrStyleService::logoFilePath($style['logo_path']);
            return is_file($path) ? $path : null;
        }

        return null;
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
