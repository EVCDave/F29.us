<?php
declare(strict_types=1);

/**
 * Static QR code generator.
 *
 * Static QR codes encode the payload directly into the rendered image.
 * They are never saved, tracked, or scanned by f29 — no database rows are
 * created for them. Every action here is stateless except for reading the
 * user's plan entitlements.
 */
class StaticQrController
{
    /** Fixed PNG download size allow-list, identical to dynamic QR downloads. */
    private const PNG_DOWNLOAD_SIZES = [512, 1024, 2048, 4096];

    public function form(): void
    {
        AuthService::requireAuth();
        $this->renderForm([]);
    }

    public function preview(): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        $input        = $this->captureInput();
        $result       = StaticQrPayloadService::build($input);
        $styleBuilt   = $this->buildStyleForUser($userId, $input);
        $style        = $styleBuilt['style'];
        $styleErrors  = $styleBuilt['errors'];

        $errors = array_merge($result['errors'], $styleErrors);

        // Only render the preview if BOTH payload and style validate cleanly.
        $previewSvg = null;
        if ($result['ok'] && empty($styleErrors)) {
            try {
                $previewSvg = base64_encode(
                    QrCodeService::generateSvg($result['payload'], 300, $style)
                );
            } catch (Throwable $e) {
                error_log('[Static QR Preview] Render failed: ' . $e->getMessage());
                $errors[] = 'Could not render preview. Please try shorter content.';
            }
        }

        $this->renderForm([
            'input'       => $input,
            'result'      => $result,
            'style'       => $style,
            'previewSvg'  => $previewSvg,
            'errors'      => $errors,
        ]);
    }

    public function downloadPng(): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        if (!EntitlementService::isEnabled($userId, 'can_export_png')) {
            $this->forbidden('PNG export is not available on your plan.');
        }

        $input       = $this->captureInput();
        $result      = StaticQrPayloadService::build($input);
        $styleBuilt  = $this->buildStyleForUser($userId, $input);
        $style       = $styleBuilt['style'];
        $styleErrors = $styleBuilt['errors'];

        if (!$result['ok'] || !empty($styleErrors)) {
            // Re-render the form with errors rather than 403, so the user can fix the inputs.
            $this->renderForm([
                'input'  => $input,
                'result' => $result,
                'style'  => $style,
                'errors' => array_merge($result['errors'], $styleErrors),
            ]);
            return;
        }

        $size = $this->validatePngSize($_POST['size'] ?? null, $userId);

        if ($size >= 2048) {
            @ini_set('memory_limit', '256M');
        }

        try {
            $png = QrCodeService::generatePng($result['payload'], $size, $style);
        } catch (Throwable $e) {
            error_log('[Static QR PNG Download] Failed at size ' . $size . 'px: ' . $e->getMessage());
            $this->forbidden('Could not generate PNG at the requested size. Please try a smaller size or contact support.');
        }

        $filename = $this->safeFilename($result['type'], $size, 'png');

        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    }

    public function downloadSvg(): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        if (!EntitlementService::isEnabled($userId, 'can_export_svg')) {
            $this->forbidden('SVG export is not available on your plan.');
        }

        $input       = $this->captureInput();
        $result      = StaticQrPayloadService::build($input);
        $styleBuilt  = $this->buildStyleForUser($userId, $input);
        $style       = $styleBuilt['style'];
        $styleErrors = $styleBuilt['errors'];

        if (!$result['ok'] || !empty($styleErrors)) {
            $this->renderForm([
                'input'  => $input,
                'result' => $result,
                'style'  => $style,
                'errors' => array_merge($result['errors'], $styleErrors),
            ]);
            return;
        }

        try {
            $svg = QrCodeService::generateSvg($result['payload'], 300, $style);
        } catch (Throwable $e) {
            error_log('[Static QR SVG Download] Failed: ' . $e->getMessage());
            $this->forbidden('Could not generate SVG. Please contact support.');
        }

        $filename = $this->safeFilename($result['type'], null, 'svg');

        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($svg));
        echo $svg;
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Pull only the fields we expect from POST/GET. Trims caller-supplied
     * scalars. Hidden checkbox is normalized to a bool-like value.
     */
    private function captureInput(): array
    {
        $src = $_POST;
        // /qr/static (GET) just renders the empty form — no input expected there.
        if (empty($src) && isset($_GET['type'])) {
            $src = $_GET;
        }

        $get = static fn(string $k): string => is_scalar($src[$k] ?? null) ? trim((string) $src[$k]) : '';

        return [
            'type'           => $get('type') !== '' ? $get('type') : 'text',
            'content'        => $get('content'),
            'ssid'           => $get('ssid'),
            'password'       => (string) ($src['password'] ?? ''),
            'security'       => $get('security') !== '' ? $get('security') : 'WPA',
            'hidden'         => !empty($src['hidden']),
            'email_to'       => $get('email_to'),
            'email_subject'  => $get('email_subject'),
            'email_body'     => (string) ($src['email_body'] ?? ''),
            'first_name'     => $get('first_name'),
            'last_name'      => $get('last_name'),
            'display_name'   => $get('display_name'),
            'company'        => $get('company'),
            'title'          => $get('title'),
            'phone'          => $get('phone'),
            'email'          => $get('email'),
            'website'        => $get('website'),
            // style fields
            'foreground_color'       => $get('foreground_color'),
            'background_color'       => $get('background_color'),
            'background_transparent' => !empty($src['background_transparent']),
            'module_style'           => $get('module_style') !== '' ? $get('module_style') : 'square',
            'size'                   => $get('size'),
        ];
    }

    /**
     * Build a style array compatible with QrCodeService::generatePng/Svg.
     *
     * Returns ['style' => array, 'errors' => string[]].
     *
     * Entitled users get full validation against the same rules as dynamic QR
     * styling (hex format, color contrast, allowed module styles); invalid
     * submissions surface as visible errors instead of silently rendering
     * default output.
     *
     * Unentitled users have submitted values silently coerced back to the safe
     * defaults so hidden-form tampering can never produce styled output.
     */
    private function buildStyleForUser(int $userId, array $input): array
    {
        $style  = QrStyleService::defaultStyle();
        $errors = [];

        $canColors = EntitlementService::isEnabled($userId, 'can_customize_qr_colors');
        $canModule = EntitlementService::isEnabled($userId, 'can_customize_qr_module_style');

        $fg          = '#000000';
        $bg          = '#FFFFFF';
        $transparent = false;
        $moduleStyle = 'square';

        if ($canColors) {
            $submittedFgRaw = (string) ($input['foreground_color'] ?? '#000000');
            $submittedBgRaw = (string) ($input['background_color'] ?? '#FFFFFF');
            $submittedFg    = QrStyleService::normalizeHexColor($submittedFgRaw);
            $submittedBg    = QrStyleService::normalizeHexColor($submittedBgRaw);

            // Only run contrast/equality checks when both colors parse, so the
            // user sees one format error at a time rather than a flood.
            if ($submittedFg !== null && $submittedBg !== null) {
                $colorErrors = QrStyleService::validateColors($submittedFg, $submittedBg);
                if (empty($colorErrors)) {
                    $fg = $submittedFg;
                    $bg = $submittedBg;
                } else {
                    $errors = array_merge($errors, $colorErrors);
                }
            } else {
                if ($submittedFg === null) {
                    $errors[] = 'Foreground color must be a valid hex color, such as #000000.';
                }
                if ($submittedBg === null) {
                    $errors[] = 'Background color must be a valid hex color, such as #FFFFFF.';
                }
            }

            $transparent = !empty($input['background_transparent']);
        }
        // Unentitled: drop whatever was submitted for colors/transparent, no error.

        if ($canModule) {
            $submittedModule = (string) ($input['module_style'] ?? 'square');
            $moduleErr       = QrStyleService::validateModuleStyle($submittedModule);
            if ($moduleErr === null) {
                $moduleStyle = $submittedModule;
            } else {
                $errors[] = $moduleErr;
            }
        }
        // Unentitled: silently coerced to 'square'.

        $isCustom = $fg !== '#000000' || $bg !== '#FFFFFF' || $transparent || $moduleStyle !== 'square';

        $style['foreground_color']       = $fg;
        $style['background_color']       = $bg;
        $style['background_transparent'] = $transparent;
        $style['module_style']           = $moduleStyle;
        $style['error_correction_level'] = $isCustom ? 'Q' : 'M';
        $style['logo_enabled']           = false;
        $style['is_custom']              = $isCustom;

        return [
            'style'  => $style,
            'errors' => $errors,
        ];
    }

    /**
     * Returns PNG sizes (px) this user may select. Always returns at least [512].
     */
    private function allowedPngDownloadSizesForUser(int $userId): array
    {
        $max = (int) EntitlementService::getValue($userId, 'max_qr_download_size_px', 512);
        $sizes = array_values(array_filter(
            self::PNG_DOWNLOAD_SIZES,
            static fn(int $size): bool => $size <= $max
        ));
        return $sizes === [] ? [512] : $sizes;
    }

    private function validatePngSize(mixed $sizeParam, int $userId): int
    {
        if ($sizeParam === null || $sizeParam === '') {
            return 512;
        }
        if (!is_string($sizeParam) || !ctype_digit($sizeParam)) {
            $this->forbidden('Invalid PNG download size.');
        }
        $size = (int) $sizeParam;
        if (!in_array($size, self::PNG_DOWNLOAD_SIZES, true)) {
            $this->forbidden('Invalid PNG download size.');
        }
        if (!in_array($size, $this->allowedPngDownloadSizesForUser($userId), true)) {
            $this->forbidden('Your plan does not allow that PNG download size.');
        }
        return $size;
    }

    private function safeFilename(string $type, ?int $size, string $ext): string
    {
        $type = preg_replace('/[^a-z]/', '', strtolower($type));
        if ($type === '') {
            $type = 'qr';
        }
        $stamp = gmdate('Ymd-His');
        $base  = 'f29-static-qr-' . $type . '-' . $stamp;
        if ($size !== null) {
            $base .= '-' . $size . 'px';
        }
        return $base . '.' . $ext;
    }

    /**
     * Render the static QR form page. Accepts optional state from preview/download
     * paths so submitted values stay populated and errors/preview can render inline.
     */
    private function renderForm(array $state): void
    {
        $userId = (int) AuthService::userId();

        $input      = $state['input']      ?? $this->emptyInput();
        $result     = $state['result']     ?? null;
        $errors     = $state['errors']     ?? [];
        $previewSvg = $state['previewSvg'] ?? null;
        $style      = $state['style']      ?? QrStyleService::defaultStyle();

        $canColors    = EntitlementService::isEnabled($userId, 'can_customize_qr_colors');
        $canModule    = EntitlementService::isEnabled($userId, 'can_customize_qr_module_style');
        $canExportPng = EntitlementService::isEnabled($userId, 'can_export_png');
        $canExportSvg = EntitlementService::isEnabled($userId, 'can_export_svg');

        $pngSizes      = $this->allowedPngDownloadSizesForUser($userId);
        $defaultSize   = end($pngSizes) ?: 512;
        $selectedSize  = is_numeric($input['size'] ?? null) && in_array((int) $input['size'], $pngSizes, true)
            ? (int) $input['size']
            : $defaultSize;

        View::render('qr/static', [
            'pageTitle'        => 'Static QR Generator — f29.us Dynamic QR',
            'input'            => $input,
            'result'           => $result,
            'errors'           => $errors,
            'previewSvg'       => $previewSvg,
            'style'            => $style,
            'canColors'        => $canColors,
            'canModuleStyle'   => $canModule,
            'canExportPng'     => $canExportPng,
            'canExportSvg'     => $canExportSvg,
            'pngDownloadSizes' => $pngSizes,
            'pngDefaultSize'   => $defaultSize,
            'pngSelectedSize'  => $selectedSize,
            'allowedTypes'     => StaticQrPayloadService::TYPES,
        ]);
    }

    private function emptyInput(): array
    {
        return [
            'type' => 'text', 'content' => '',
            'ssid' => '', 'password' => '', 'security' => 'WPA', 'hidden' => false,
            'email_to' => '', 'email_subject' => '', 'email_body' => '',
            'first_name' => '', 'last_name' => '', 'display_name' => '',
            'company' => '', 'title' => '', 'phone' => '', 'email' => '', 'website' => '',
            'foreground_color' => '#000000', 'background_color' => '#FFFFFF',
            'background_transparent' => false, 'module_style' => 'square', 'size' => '',
        ];
    }

    private function forbidden(string $message): never
    {
        http_response_code(403);
        View::render('errors/forbidden', [
            'pageTitle' => '403 — Access Denied',
            'message'   => $message,
        ]);
        exit;
    }
}
