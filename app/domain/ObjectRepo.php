<?php

final class ObjectRepo
{
    private $events;
    private $validator;

    public function __construct(EventBus $events)
    {
        $this->events = $events;
        $this->validator = new FieldValidator();
    }

    public function insert(array $component, $sectionId, $infoblockId, array $data): int
    {
        $sanitized = $this->validateAndPrepareData($component, $data);

        $payload = [
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $component['id'] ?? null,
            'data' => $sanitized,
        ];
        $this->events->emit('object.before_insert', $payload);

        $now = $this->now();
        $stmt = DB::pdo()->prepare(
            'INSERT INTO objects (section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at)
            VALUES (:section_id, :infoblock_id, :component_id, :data_json, :created_at, :updated_at, 0, NULL)'
        );
        $stmt->execute([
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $component['id'] ?? null,
            'data_json' => json_encode($sanitized, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        $payload['id'] = $id;
        $this->events->emit('object.after_insert', $payload);

        return $id;
    }

    public function update(array $component, $id, array $data): void
    {
        $sanitized = $this->validateAndPrepareData($component, $data);

        $payload = [
            'id' => $id,
            'component_id' => $component['id'] ?? null,
            'data' => $sanitized,
        ];
        $this->events->emit('object.before_update', $payload);

        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET data_json = :data_json, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'data_json' => json_encode($sanitized, JSON_UNESCAPED_UNICODE),
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        $this->events->emit('object.after_update', $payload);
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

    public function findById($id): ?array
    {
        return DB::fetchOne(
            'SELECT id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at
            FROM objects WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function softDelete($id): void
    {
        $this->events->emit('object.before_delete', ['id' => $id]);

        $now = $this->now();
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
            'updated_at' => $this->now(),
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

    private function validateAndPrepareData(array $component, array $data): array
    {
        try {
            $sanitized = $this->validator->validate($component, $data);
            $this->events->emit('object.validated', [
                'component_id' => $component['id'] ?? null,
                'data' => $sanitized,
            ]);

            return $sanitized;
        } catch (Throwable $e) {
            $this->events->emit('object.validation_failed', [
                'component_id' => $component['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
