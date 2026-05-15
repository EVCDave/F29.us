<?php
declare(strict_types=1);

class QrQuotaService
{
    // Statuses that count toward a user's active QR quota.
    // 'archived' is intentionally excluded: users can retire QR codes without consuming quota.
    private const COUNTABLE = ['active', 'paused', 'disabled'];

    public static function countCountableForUser(int $userId): int
    {
        $placeholders = implode(',', array_fill(0, count(self::COUNTABLE), '?'));
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*)
             FROM   qr_codes    AS qr
             JOIN   short_links AS sl ON sl.id = qr.short_link_id
             WHERE  qr.user_id = ?
             AND    sl.status IN ({$placeholders})"
        );
        $stmt->execute([$userId, ...self::COUNTABLE]);
        return (int) $stmt->fetchColumn();
    }

    public static function maxForUser(int $userId): int
    {
        return (int) EntitlementService::getValue($userId, 'max_qr_codes', 0);
    }

    public static function canCreateForUser(int $userId): bool
    {
        return self::countCountableForUser($userId) < self::maxForUser($userId);
    }

    public static function canRestoreForUser(int $userId): bool
    {
        return self::countCountableForUser($userId) < self::maxForUser($userId);
    }
}
