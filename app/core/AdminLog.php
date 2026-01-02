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

    public static function list(array $filters, int $limit = 100): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = (string) $filters['entity_type'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = (string) $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        $limit = $limit > 0 ? $limit : 100;
        $sql = 'SELECT id, created_at, user_id, action, entity_type, entity_id, data_json, ip, user_agent
            FROM admin_log';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;

        return DB::fetchAll($sql, $params);
    }
}
