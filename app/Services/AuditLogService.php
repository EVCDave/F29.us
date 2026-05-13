<?php
declare(strict_types=1);

class AuditLogService
{
    /**
     * Write a single audit log entry.
     *
     * @param int|null $userId     null for system-generated entries
     * @param string   $entityType e.g. 'short_link', 'qr_code'
     * @param int      $entityId
     * @param string   $action     e.g. 'created', 'destination_updated', 'paused'
     * @param array    $metadata   structured key/value context (old/new values, etc.)
     */
    public static function log(
        ?int   $userId,
        string $entityType,
        int    $entityId,
        string $action,
        array  $metadata = []
    ): void {
        Database::get()->prepare("
            INSERT INTO audit_logs
                (user_id, entity_type, entity_id, action, metadata_json, created_at)
            VALUES
                (?, ?, ?, ?, ?, NOW())
        ")->execute([
            $userId,
            $entityType,
            $entityId,
            $action,
            empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
