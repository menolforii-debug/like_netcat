<?php

final class ObjectRepo
{
    public function listForInfoblock($infoblockId, bool $includeDeleted = false): array
    {
        $where = 'infoblock_id = :infoblock_id';
        if (!$includeDeleted) {
            $where .= ' AND is_deleted = 0';
        }

        return DB::fetchAll(
            'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE ' . $where . '
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

    public function create(array $data): int
    {
        $status = isset($data['status']) ? (string) $data['status'] : 'draft';
        $now = $this->now();
        $publishedAt = $status === 'published' ? $now : null;

        $stmt = DB::pdo()->prepare(
            'INSERT INTO objects (site_id, section_id, infoblock_id, component_id, data_json, status, published_at, created_at, updated_at, is_deleted, deleted_at)
            VALUES (:site_id, :section_id, :infoblock_id, :component_id, :data_json, :status, :published_at, :created_at, :updated_at, 0, NULL)'
        );
        $stmt->execute([
            'site_id' => $data['site_id'],
            'section_id' => $data['section_id'],
            'infoblock_id' => $data['infoblock_id'],
            'component_id' => $data['component_id'],
            'data_json' => json_encode($data['data'] ?? [], JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'published_at' => $publishedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        core()->events()->emit('object.created', [
            'id' => $id,
            'data' => $data,
        ]);

        return $id;
    }

    public function update($id, array $data): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET data_json = :data_json, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'data_json' => json_encode($data['data'] ?? [], JSON_UNESCAPED_UNICODE),
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        core()->events()->emit('object.updated', [
            'id' => $id,
            'data' => $data,
        ]);
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

        core()->events()->emit('object.published', ['id' => $id]);
    }

    public function unpublish($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET status = :status, published_at = NULL, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'draft',
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        core()->events()->emit('object.unpublished', ['id' => $id]);
    }

    public function softDelete($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET is_deleted = 1, deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'deleted_at' => $this->now(),
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        core()->events()->emit('object.deleted', ['id' => $id]);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
