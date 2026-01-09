<?php

final class ObjectRepo
{
    private ?string $lastSelectQuery = null;

    public function listForInfoblock($infoblockId, bool $includeDeleted = false, ?string $status = null): array
    {
        $where = 'infoblock_id = :infoblock_id';
        if (!$includeDeleted) {
            $where .= ' AND is_deleted = 0';
        }
        if ($status !== null && $status !== '') {
            $where .= ' AND status = :status';
        }

        $params = ['infoblock_id' => $infoblockId];
        if ($status !== null && $status !== '') {
            $params['status'] = $status;
        }

        $sql = 'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE ' . $where . '
            ORDER BY id ASC';
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll(
            $sql,
            $params
        );
    }

    public function listForInfoblockWithOverride($infoblockId, bool $includeDeleted, ?string $status, array $override): array
    {
        $where = [];
        $params = [];
        $ignoreSub = !empty($override['ignore_sub']);
        if ($ignoreSub && !empty($override['component_id'])) {
            $where[] = 'component_id = :component_id';
            $params['component_id'] = (int) $override['component_id'];
        } else {
            $where[] = 'infoblock_id = :infoblock_id';
            $params['infoblock_id'] = $infoblockId;
        }
        if (!$includeDeleted) {
            $where[] = 'is_deleted = 0';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
        }

        if (!empty($override['where'])) {
            foreach ((array) $override['where'] as $condition) {
                if (is_string($condition) && trim($condition) !== '') {
                    $where[] = $condition;
                }
            }
        }

        $order = '';
        if (!empty($override['order']) && is_string($override['order'])) {
            $order = ' ORDER BY ' . $override['order'];
        }

        $limit = '';
        if (isset($override['limit']) && is_numeric($override['limit'])) {
            $limitValue = (int) $override['limit'];
            if ($limitValue > 0) {
                $limit = ' LIMIT ' . $limitValue;
            }
        }

        if ($status !== null && $status !== '') {
            $params['status'] = $status;
        }
        if (!empty($override['params']) && is_array($override['params'])) {
            $params = array_merge($params, $override['params']);
        }

        $sql = 'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE ' . implode(' AND ', $where) . $order . $limit;
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll($sql, $params);
    }

    public function listBySql(string $sql, array $params = []): array
    {
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll($sql, $params);
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

    public function restore($id): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE objects SET is_deleted = 0, deleted_at = NULL, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        core()->events()->emit('object.restored', ['id' => $id]);
    }

    public function purge($id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM objects WHERE id = :id');
        $stmt->execute(['id' => $id]);

        core()->events()->emit('object.purged', ['id' => $id]);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }

    private function interpolateQuery(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }

        return preg_replace_callback('/(:[a-zA-Z0-9_]+)/', function (array $matches) use ($params): string {
            $key = substr($matches[0], 1);
            if (!array_key_exists($key, $params)) {
                return $matches[0];
            }

            return $this->formatQueryValue($params[$key]);
        }, $sql) ?? $sql;
    }

    private function formatQueryValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return DB::pdo()->quote((string) $value);
    }

    public function getLastSelectQuery(): ?string
    {
        return $this->lastSelectQuery;
    }
}
