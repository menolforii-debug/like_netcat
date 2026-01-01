<?php

final class ObjectRepo
{
    private $events;

    public function __construct(EventBus $events)
    {
        $this->events = $events;
    }

    public function insert($sectionId, $infoblockId, $componentId, array $data): int
    {
        $payload = [
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $componentId,
            'data' => $data,
        ];
        $this->events->emit('object.before_insert', $payload);

        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
        $stmt = DB::pdo()->prepare(
            'INSERT INTO objects (section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at)
            VALUES (:section_id, :infoblock_id, :component_id, :data_json, :created_at, :updated_at, 0, NULL)'
        );
        $stmt->execute([
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $componentId,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        $payload['id'] = $id;
        $this->events->emit('object.after_insert', $payload);

        return $id;
    }

    public function findByInfoblock($infoblockId): array
    {
        return DB::fetchAll(
            'SELECT id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at
            FROM objects
            WHERE infoblock_id = :infoblock_id AND is_deleted = 0
            ORDER BY id ASC',
            ['infoblock_id' => $infoblockId]
        );
    }

    public function softDelete($id): void
    {
        $this->events->emit('object.before_delete', ['id' => $id]);

        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET is_deleted = 1, deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'deleted_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        $this->events->emit('object.after_delete', ['id' => $id]);
    }

    public function restore($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET is_deleted = 0, deleted_at = NULL, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'updated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
            'id' => $id,
        ]);

        $this->events->emit('trash.restore', ['id' => $id]);
    }

    public function purge($id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM objects WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $this->events->emit('trash.purge', ['id' => $id]);
    }

    public function listTrash($infoblockId, $limit = 50): array
    {
        $limit = (int) $limit;

        return DB::fetchAll(
            'SELECT id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at
            FROM objects
            WHERE infoblock_id = :infoblock_id AND is_deleted = 1
            ORDER BY deleted_at DESC
            LIMIT ' . $limit,
            ['infoblock_id' => $infoblockId]
        );
    }

    public function listTrashAll($limit = 50): array
    {
        $limit = (int) $limit;

        return DB::fetchAll(
            'SELECT id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at
            FROM objects
            WHERE is_deleted = 1
            ORDER BY deleted_at DESC
            LIMIT ' . $limit
        );
    }
}
