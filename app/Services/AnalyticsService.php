<?php
declare(strict_types=1);

class AnalyticsService
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns [from_datetime, to_datetime] for use in BETWEEN clauses. */
    private static function bounds(string $fromDate, string $toDate): array
    {
        return [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'];
    }

    // ── Counts ────────────────────────────────────────────────────────────────

    /**
     * Total scan count in range.
     * $includeBots = false (default) counts only human scans.
     */
    public static function getTotalScans(
        int    $shortLinkId,
        string $fromDate,
        string $toDate,
        bool   $includeBots = false
    ): int {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $sql = "
            SELECT COUNT(*)
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
        ";
        if (!$includeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $from, $to]);
        return (int) $stmt->fetchColumn();
    }

    /** Bot scan count in range. */
    public static function getBotCount(int $shortLinkId, string $fromDate, string $toDate): int
    {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $stmt = Database::get()->prepare("
            SELECT COUNT(*)
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
              AND  bot_flag = 1
        ");
        $stmt->execute([$shortLinkId, $from, $to]);
        return (int) $stmt->fetchColumn();
    }

    // ── Daily breakdown ───────────────────────────────────────────────────────

    /**
     * Daily counts across the full date range, newest first.
     * Zero-count days are included so the table/chart is complete.
     */
    public static function getDailyCounts(
        int    $shortLinkId,
        string $fromDate,
        string $toDate,
        bool   $includeBots = false
    ): array {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $sql = "
            SELECT DATE(scanned_at) AS scan_date, COUNT(*) AS total
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
        ";
        if (!$includeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $sql .= ' GROUP BY DATE(scanned_at)';
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['scan_date']] = (int) $r['total'];
        }

        // Fill every calendar day in the range, even zero-count days
        $result = [];
        $cursor  = strtotime($fromDate);
        $end     = strtotime($toDate);
        while ($cursor <= $end) {
            $d        = date('Y-m-d', $cursor);
            $result[] = ['scan_date' => $d, 'total' => $byDate[$d] ?? 0];
            $cursor   = strtotime('+1 day', $cursor);
        }

        return array_reverse($result); // newest first
    }

    // ── Breakdowns ────────────────────────────────────────────────────────────

    /** Device type breakdown. Bots excluded unless $includeBots is true. */
    public static function getDeviceBreakdown(
        int    $shortLinkId,
        string $fromDate,
        string $toDate,
        bool   $includeBots = false
    ): array {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $sql = "
            SELECT device_type, COUNT(*) AS total
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
        ";
        if (!$includeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $sql .= ' GROUP BY device_type ORDER BY total DESC';
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Top referers (up to 10). Bots excluded unless $includeBots is true.
     * Null/empty referers are reported as 'Direct / Unknown'.
     */
    public static function getTopReferers(
        int    $shortLinkId,
        string $fromDate,
        string $toDate,
        bool   $includeBots = false
    ): array {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $sql = "
            SELECT
                COALESCE(NULLIF(referer, ''), 'Direct / Unknown') AS referer,
                COUNT(*) AS total
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
        ";
        if (!$includeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $sql .= "
            GROUP  BY COALESCE(NULLIF(referer, ''), 'Direct / Unknown')
            ORDER  BY total DESC
            LIMIT  10
        ";
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Raw scan event rows for CSV export, newest first.
     * Columns: scanned_at, device_type, bot_flag, referer,
     *          user_agent, country_code, region, city
     */
    public static function getExportRows(
        int    $shortLinkId,
        string $fromDate,
        string $toDate,
        bool   $includeBots = false
    ): array {
        [$from, $to] = self::bounds($fromDate, $toDate);
        $sql = "
            SELECT scanned_at, device_type, bot_flag, referer,
                   user_agent, country_code, region, city
            FROM   scan_events
            WHERE  short_link_id = ?
              AND  scanned_at BETWEEN ? AND ?
        ";
        if (!$includeBots) {
            $sql .= ' AND bot_flag = 0';
        }
        $sql .= ' ORDER BY scanned_at DESC';
        $stmt = Database::get()->prepare($sql);
        $stmt->execute([$shortLinkId, $from, $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
