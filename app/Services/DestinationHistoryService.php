<?php
declare(strict_types=1);

class DestinationHistoryService
{
    /**
     * Record the initial destination when a QR code is first created.
     * Called inside the QR creation transaction.
     */
    public static function recordInitial(PDO $pdo, int $shortLinkId, string $newUrl, int $userId): void
    {
        $pdo->prepare("
            INSERT INTO destination_history
                (short_link_id, changed_by_user_id, old_target_url, new_target_url, change_source, created_at)
            VALUES (?, ?, NULL, ?, 'system', ?)
        ")->execute([$shortLinkId, $userId, $newUrl, gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Record a destination change (user edit or restore).
     * Called inside an existing transaction.
     */
    public static function record(
        PDO    $pdo,
        int    $shortLinkId,
        int    $userId,
        string $oldUrl,
        string $newUrl,
        string $source = 'user_edit'
    ): void {
        $pdo->prepare("
            INSERT INTO destination_history
                (short_link_id, changed_by_user_id, old_target_url, new_target_url, change_source, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$shortLinkId, $userId, $oldUrl, $newUrl, $source, gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Fetch destination history for a short link, newest first.
     */
    public static function fetchForShortLink(PDO $pdo, int $shortLinkId, int $limit = 50): array
    {
        $stmt = $pdo->prepare("
            SELECT   dh.id, dh.old_target_url, dh.new_target_url,
                     dh.change_source, dh.created_at,
                     u.email AS changed_by_email
            FROM     destination_history dh
            LEFT JOIN users u ON u.id = dh.changed_by_user_id
            WHERE    dh.short_link_id = ?
            ORDER BY dh.created_at DESC, dh.id DESC
            LIMIT    ?
        ");
        $stmt->execute([$shortLinkId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single history row, verifying it belongs to the given short link.
     */
    public static function fetchRow(PDO $pdo, int $historyId, int $shortLinkId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM destination_history
            WHERE id = ? AND short_link_id = ?
            LIMIT 1
        ");
        $stmt->execute([$historyId, $shortLinkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
