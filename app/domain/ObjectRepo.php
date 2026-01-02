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

    public function insert(array $component, $sectionId, $infoblockId, array $data, $status = 'published'): int
    {
        $sanitized = $this->validateAndPrepareData($component, $data);

        $siteId = $this->resolveSiteId($sectionId);
        $payload = [
            'site_id' => $siteId,
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $component['id'] ?? null,
            'data' => $sanitized,
        ];
        $this->events->emit('object.before_insert', $payload);

        $now = $this->now();
        $publishedAt = $status === 'published' ? $now : null;
        $stmt = DB::pdo()->prepare(
            'INSERT INTO objects (site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at)
            VALUES (:site_id, :section_id, :infoblock_id, :component_id, :data_json, :created_at, :updated_at, 0, NULL, :status, :published_at)'
        );
        $stmt->execute([
            'site_id' => $siteId,
            'section_id' => $sectionId,
            'infoblock_id' => $infoblockId,
            'component_id' => $component['id'] ?? null,
            'data_json' => json_encode($sanitized, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
            'status' => $status,
            'published_at' => $publishedAt,
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

    public function publish($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET status = :status, published_at = :published_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'published',
            'published_at' => $this->now(),
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    public function unpublish($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'draft',
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    public function archive($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'archived',
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    public function listForInfoblock($infoblockId): array
    {
        return DB::fetchAll(
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE infoblock_id = :infoblock_id AND is_deleted = 0 AND status = :status
            ORDER BY id ASC',
            [
                'infoblock_id' => $infoblockId,
                'status' => 'published',
            ]
        );
    }

    public function listForInfoblockEdit($infoblockId): array
    {
        return DB::fetchAll(
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE infoblock_id = :infoblock_id AND is_deleted = 0 AND status IN ("draft", "published")
            ORDER BY id ASC',
            ['infoblock_id' => $infoblockId]
        );
    }

    public function findById($id): ?array
    {
        return DB::fetchOne(
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
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
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
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
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
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

    private function resolveSiteId($sectionId): int
    {
        $row = DB::fetchOne('SELECT site_id FROM sections WHERE id = :id LIMIT 1', ['id' => $sectionId]);
        if ($row && isset($row['site_id'])) {
            return (int) $row['site_id'];
        }

        return 0;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
