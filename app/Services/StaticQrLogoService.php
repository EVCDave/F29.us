<?php
declare(strict_types=1);

/**
 * Session-backed, filesystem-backed temporary storage for static-QR logo files.
 *
 * Static QR codes are stateless and not persisted to the database. To let a
 * user upload a logo once during preview and have it survive into the PNG/SVG
 * download requests, we stash the validated file under storage/static-qr-logos/
 * with a generated filename and a short-lived session-tracked token.
 *
 * Properties:
 *   - Token expires in TOKEN_TTL_SECONDS (30 min).
 *   - Cleanup runs on every static-QR request via cleanupExpired().
 *   - Tokens are scoped to the uploading user; another user cannot reuse them.
 *   - Filenames are generated, never user-controlled.
 *   - Storage lives outside the public web root (storage/static-qr-logos).
 *   - No database rows are created.
 */
class StaticQrLogoService
{
    public const TOKEN_TTL_SECONDS = 1800;
    private const SESSION_KEY      = 'static_qr_logos';
    private const STORAGE_SUBDIR   = '/static-qr-logos';

    /**
     * Returns the storage directory, creating it if necessary.
     */
    public static function storageDir(): string
    {
        $dir = STORAGE_PATH . self::STORAGE_SUBDIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create static logo storage directory.');
        }
        return $dir;
    }

    /**
     * Validates an upload and stores it as a temporary, session-tracked file.
     * Returns ['ok'=>bool, 'token'=>?string, 'logo'=>?array, 'errors'=>string[]].
     */
    public static function storeUploadedLogo(array $file, int $userId): array
    {
        $errors = QrStyleService::validateLogoUpload($file, $userId);
        if (!empty($errors)) {
            return ['ok' => false, 'token' => null, 'logo' => null, 'errors' => $errors];
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $dir       = self::storageDir();
        $filename  = 'static-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath  = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return [
                'ok'     => false,
                'token'  => null,
                'logo'   => null,
                'errors' => ['Failed to store the uploaded logo. Please try again.'],
            ];
        }

        $logoPercent = (int) EntitlementService::getValue($userId, 'qr_logo_max_percent', 20);
        if ($logoPercent <= 0) {
            $logoPercent = 20;
        }

        $token = bin2hex(random_bytes(16));
        $now   = time();

        $logo = [
            'path'              => $destPath,
            'filename'          => $filename,
            'original_filename' => (string) ($file['name'] ?? 'logo.' . $ext),
            'mime_type'         => $mimeType,
            'size_bytes'        => (int) ($file['size'] ?? 0),
            'logo_percent'      => $logoPercent,
            'created_at'        => $now,
            'expires_at'        => $now + self::TOKEN_TTL_SECONDS,
            'user_id'           => $userId,
        ];

        self::ensureSessionBucket();
        $_SESSION[self::SESSION_KEY][$token] = $logo;

        return ['ok' => true, 'token' => $token, 'logo' => $logo, 'errors' => []];
    }

    /**
     * Look up a logo by token, scoped to the given user. Returns null when:
     *   - token is null/empty
     *   - token does not exist in this session
     *   - the entry has expired
     *   - the entry does not belong to this user
     *   - the underlying file is missing
     */
    public static function getLogoForToken(?string $token, int $userId): ?array
    {
        if ($token === null || $token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        self::ensureSessionBucket();
        $entry = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        if ((int) ($entry['user_id'] ?? 0) !== $userId) {
            return null;
        }
        if ((int) ($entry['expires_at'] ?? 0) <= time()) {
            self::deleteEntry($token);
            return null;
        }
        if (!is_string($entry['path'] ?? null) || !is_file($entry['path'])) {
            self::deleteEntry($token);
            return null;
        }
        // Defense in depth: the path we stored must live under the static logo
        // storage dir. Reject anything else so a corrupted session can't point
        // the renderer at an arbitrary file.
        if (!self::pathIsInStorageDir($entry['path'])) {
            self::deleteEntry($token);
            return null;
        }
        return $entry;
    }

    /**
     * Delete a single token's file and session entry. Safe to call with an
     * unknown token (no-op).
     */
    public static function deleteLogoToken(string $token, int $userId): void
    {
        self::ensureSessionBucket();
        $entry = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($entry)) {
            return;
        }
        if ((int) ($entry['user_id'] ?? 0) !== $userId) {
            return;
        }
        self::deleteEntry($token);
    }

    /**
     * Remove every expired entry for the current session AND best-effort sweep
     * orphaned files in the storage directory whose mtime is older than the TTL.
     * Cheap to call on every static-QR request.
     */
    public static function cleanupExpired(): void
    {
        self::ensureSessionBucket();
        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) <= $now) {
                self::deleteEntry((string) $token);
            }
        }

        // Best-effort orphan sweep: any file in the storage dir older than 2×TTL
        // is removed. Caps directory growth even when sessions die unexpectedly.
        $dir = STORAGE_PATH . self::STORAGE_SUBDIR;
        if (!is_dir($dir)) {
            return;
        }
        $cutoff = $now - (2 * self::TOKEN_TTL_SECONDS);
        foreach ((array) @scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $dir . '/' . $name;
            if (is_file($path) && (int) @filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }

    // ── internals ────────────────────────────────────────────────────────────

    private static function ensureSessionBucket(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private static function deleteEntry(string $token): void
    {
        $entry = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (is_array($entry) && is_string($entry['path'] ?? null)) {
            if (is_file($entry['path']) && self::pathIsInStorageDir($entry['path'])) {
                @unlink($entry['path']);
            }
        }
        unset($_SESSION[self::SESSION_KEY][$token]);
    }

    /**
     * True if $path resolves under storage/static-qr-logos. Uses realpath() to
     * collapse `..` traversal attempts. Returns false if either side cannot be
     * resolved.
     */
    private static function pathIsInStorageDir(string $path): bool
    {
        $dirReal  = realpath(STORAGE_PATH . self::STORAGE_SUBDIR);
        $pathReal = realpath($path);
        if ($dirReal === false || $pathReal === false) {
            return false;
        }
        $dirReal = rtrim($dirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($pathReal, $dirReal);
    }
}
