<?php

final class AdminLog
{
    public static function log($userId, string $action, string $entityType, $entityId = null, array $data = []): void
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO admin_log (created_at, user_id, action, entity_type, entity_id, data_json, ip, user_agent)
            VALUES (:created_at, :user_id, :action, :entity_type, :entity_id, :data_json, :ip, :user_agent)'
        );

        $stmt->execute([
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}
