<?php
declare(strict_types=1);

class AnalyticsService
{
    public static function getTotalScans(int $shortLinkId, int $retentionDays, bool $excludeBots = true): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM scan_events
            WHERE short_link_id = ?
              AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        if ($excludeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $retentionDays]);
        return (int) $stmt->fetchColumn();
    }

    public static function getBotCount(int $shortLinkId, int $retentionDays): int
    {
        $stmt = Database::get()->prepare("
            SELECT COUNT(*)
            FROM scan_events
            WHERE short_link_id = ?
              AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND bot_flag = 1
        ");
        $stmt->execute([$shortLinkId, $retentionDays]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Daily scan counts within the retention window, newest first.
     * Bots excluded by default.
     */
    public static function getDailyCounts(int $shortLinkId, int $retentionDays, bool $excludeBots = true): array
    {
        $sql = "
            SELECT DATE(scanned_at) AS scan_date, COUNT(*) AS total
            FROM scan_events
            WHERE short_link_id = ?
              AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        if ($excludeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $sql .= ' GROUP BY DATE(scanned_at) ORDER BY scan_date DESC';
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $retentionDays]);
        return $stmt->fetchAll();
    }

    public static function getDeviceBreakdown(int $shortLinkId, int $retentionDays): array
    {
        $stmt = Database::get()->prepare("
            SELECT device_type, COUNT(*) AS total
            FROM scan_events
            WHERE short_link_id = ?
              AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND bot_flag = 0
            GROUP BY device_type
            ORDER BY total DESC
        ");
        $stmt->execute([$shortLinkId, $retentionDays]);
        return $stmt->fetchAll();
    }

    /** Top 10 non-bot referers within the retention window. */
    public static function getTopReferers(int $shortLinkId, int $retentionDays): array
    {
        $stmt = Database::get()->prepare("
            SELECT referer, COUNT(*) AS total
            FROM scan_events
            WHERE short_link_id = ?
              AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND bot_flag = 0
              AND referer IS NOT NULL
              AND referer != ''
            GROUP BY referer
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([$shortLinkId, $retentionDays]);
        return $stmt->fetchAll();
    }
}
