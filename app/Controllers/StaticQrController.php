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
        StaticQrLogoService::cleanupExpired();
        $this->renderForm([]);
    }

    public function preview(): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        StaticQrLogoService::cleanupExpired();

        $input        = $this->captureInput();
        $result       = StaticQrPayloadService::build($input);
        $styleBuilt   = $this->buildStyleForUser($userId, $input);
        $style        = $styleBuilt['style'];
        $styleErrors  = $styleBuilt['errors'];

        // Resolve the logo (new upload wins; otherwise reuse session token).
        $logoResolved = $this->resolveLogoForRequest($userId, $input);
        $logoErrors   = $logoResolved['errors'];
        $logoToken    = $logoResolved['token'];
        if ($logoResolved['logo'] !== null) {
            $style = $this->applyLogoToStyle($style, $logoResolved['logo']);
        }

        $errors = array_merge($result['errors'], $styleErrors, $logoErrors);

        // Only render the preview if BOTH payload and style validate cleanly.
        $previewSvg = null;
        if ($result['ok'] && empty($styleErrors) && empty($logoErrors)) {
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
            'logoToken'   => $logoToken,
            'logo'        => $logoResolved['logo'],
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
        StaticQrLogoService::cleanupExpired();

        if (!EntitlementService::isEnabled($userId, 'can_export_png')) {
            $this->forbidden('PNG export is not available on your plan.');
        }

        $input       = $this->captureInput();
        $result      = StaticQrPayloadService::build($input);
        $styleBuilt  = $this->buildStyleForUser($userId, $input);
        $style       = $styleBuilt['style'];
        $styleErrors = $styleBuilt['errors'];

        // Reuse a previously-uploaded session logo if a token was forwarded.
        // No file upload happens on the download form, so this is the only
        // logo source for downloads.
        $tokenLogo = StaticQrLogoService::getLogoForToken(
            $this->extractLogoTokenFromInput(),
            $userId
        );
        if ($tokenLogo !== null) {
            if (!EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
                // Defense in depth — entitlement was lost between preview and download.
                $tokenLogo = null;
            } else {
                $style = $this->applyLogoToStyle($style, $tokenLogo);
            }
        }

        if (!$result['ok'] || !empty($styleErrors)) {
            $this->renderForm([
                'input'     => $input,
                'result'    => $result,
                'style'     => $style,
                'errors'    => array_merge($result['errors'], $styleErrors),
                'logoToken' => $tokenLogo ? $this->extractLogoTokenFromInput() : null,
                'logo'      => $tokenLogo,
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
        StaticQrLogoService::cleanupExpired();

        if (!EntitlementService::isEnabled($userId, 'can_export_svg')) {
            $this->forbidden('SVG export is not available on your plan.');
        }

        $input       = $this->captureInput();
        $result      = StaticQrPayloadService::build($input);
        $styleBuilt  = $this->buildStyleForUser($userId, $input);
        $style       = $styleBuilt['style'];
        $styleErrors = $styleBuilt['errors'];

        $tokenLogo = StaticQrLogoService::getLogoForToken(
            $this->extractLogoTokenFromInput(),
            $userId
        );
        if ($tokenLogo !== null) {
            if (!EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
                $tokenLogo = null;
            } else {
                $style = $this->applyLogoToStyle($style, $tokenLogo);
            }
        }

        if (!$result['ok'] || !empty($styleErrors)) {
            $this->renderForm([
                'input'     => $input,
                'result'    => $result,
                'style'     => $style,
                'errors'    => array_merge($result['errors'], $styleErrors),
                'logoToken' => $tokenLogo ? $this->extractLogoTokenFromInput() : null,
                'logo'      => $tokenLogo,
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
            'static_logo_token'      => $get('static_logo_token'),
        ];
    }

    /**
     * Pull the static_logo_token off the request. Kept as a separate method so
     * the validation regex lives in one place.
     */
    private function extractLogoTokenFromInput(): ?string
    {
        $token = (string) ($_POST['static_logo_token'] ?? '');
        return preg_match('/^[a-f0-9]{32}$/', $token) ? $token : null;
    }

    /**
     * Determine which logo (if any) to apply to the current request.
     *
     * Priority:
     *   1. A newly uploaded $_FILES['static_logo'] from an entitled user.
     *      Stores the file via StaticQrLogoService, returns the new token.
     *   2. A reusable session token from a previous preview, scoped to the user.
     *   3. None.
     *
     * Returns ['token'=>?string, 'logo'=>?array, 'errors'=>string[]].
     *
     * If a file is uploaded by a user who does NOT have can_upload_qr_logo,
     * the request is hard-rejected with 403 rather than silently dropping the
     * upload — the user clearly intended to use the feature.
     */
    private function resolveLogoForRequest(int $userId, array $input): array
    {
        // Any error code other than UPLOAD_ERR_NO_FILE means the user attempted
        // to upload something. We must surface failures like UPLOAD_ERR_INI_SIZE
        // and UPLOAD_ERR_FORM_SIZE even when PHP reports size === 0, so the
        // user sees a real error instead of "no upload" being silently assumed.
        $uploadError = (int) ($_FILES['static_logo']['error'] ?? UPLOAD_ERR_NO_FILE);
        $hasUpload   = isset($_FILES['static_logo'])
                    && is_array($_FILES['static_logo'])
                    && $uploadError !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            if (!EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
                $this->forbidden('Your plan does not allow QR logo upload.');
            }

            // Replace any prior token from this preview chain so we don't pile up files.
            $previousToken = $this->extractLogoTokenFromInput();
            if ($previousToken !== null) {
                StaticQrLogoService::deleteLogoToken($previousToken, $userId);
            }

            $result = StaticQrLogoService::storeUploadedLogo($_FILES['static_logo'], $userId);
            if (!$result['ok']) {
                return ['token' => null, 'logo' => null, 'errors' => $result['errors']];
            }
            return ['token' => $result['token'], 'logo' => $result['logo'], 'errors' => []];
        }

        // No new upload — try to reuse the token (preview re-submits, etc.).
        $token = $this->extractLogoTokenFromInput();
        $logo  = StaticQrLogoService::getLogoForToken($token, $userId);
        if ($logo !== null && !EntitlementService::isEnabled($userId, 'can_upload_qr_logo')) {
            // Entitlement disappeared between requests; drop silently.
            return ['token' => null, 'logo' => null, 'errors' => []];
        }
        return [
            'token'  => $logo !== null ? $token : null,
            'logo'   => $logo,
            'errors' => [],
        ];
    }

    /**
     * Decorate a style array with a temporary logo. Always sets ECL=H, which
     * overrides any color/transparent/module-style choice (mirrors dynamic QR
     * policy: logo wins).
     */
    private function applyLogoToStyle(array $style, array $logo): array
    {
        $style['logo_enabled']           = true;
        $style['logo_absolute_path']     = $logo['path'];
        $style['logo_original_filename'] = $logo['original_filename'] ?? null;
        $style['logo_mime_type']         = $logo['mime_type'] ?? 'image/png';
        $style['logo_size_bytes']        = $logo['size_bytes'] ?? null;
        $style['logo_max_percent']       = (int) ($logo['logo_percent'] ?? 20);
        $style['error_correction_level'] = 'H';
        return $style;
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
        $canLogo      = EntitlementService::isEnabled($userId, 'can_upload_qr_logo');
        $logoMaxKb    = (int) EntitlementService::getValue($userId, 'qr_logo_max_size_kb', 0);
        $logoPercent  = (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 0);

        $pngSizes      = $this->allowedPngDownloadSizesForUser($userId);
        $defaultSize   = end($pngSizes) ?: 512;
        $selectedSize  = is_numeric($input['size'] ?? null) && in_array((int) $input['size'], $pngSizes, true)
            ? (int) $input['size']
            : $defaultSize;

        View::render('qr/static', [
            'pageTitle'        => 'Static QR Generator — F29 QR Codes System',
            'input'            => $input,
            'result'           => $result,
            'errors'           => $errors,
            'previewSvg'       => $previewSvg,
            'style'            => $style,
            'canColors'        => $canColors,
            'canModuleStyle'   => $canModule,
            'canExportPng'     => $canExportPng,
            'canExportSvg'     => $canExportSvg,
            'canLogo'          => $canLogo,
            'logoMaxKb'        => $logoMaxKb,
            'logoMaxPercent'   => $logoPercent,
            'pngDownloadSizes' => $pngSizes,
            'pngDefaultSize'   => $defaultSize,
            'pngSelectedSize'  => $selectedSize,
            'allowedTypes'     => StaticQrPayloadService::TYPES,
            'logo'             => $state['logo']      ?? null,
            'logoToken'        => $state['logoToken'] ?? null,
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
