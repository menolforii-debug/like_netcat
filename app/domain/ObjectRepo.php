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
            ORDER BY id DESC';
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll(
            $sql,
            $params
        );
    }

    public function listForInfoblockPaged($infoblockId, bool $includeDeleted, ?string $status, int $limit, int $offset): array
    {
        $where = 'infoblock_id = :infoblock_id';
        if (!$includeDeleted) {
            $where .= ' AND is_deleted = 0';
        }
        if ($status !== null && $status !== '') {
            $where .= ' AND status = :status';
        }

        $params = [
            'infoblock_id' => $infoblockId,
            'limit' => $limit,
            'offset' => $offset,
        ];
        if ($status !== null && $status !== '') {
            $params['status'] = $status;
        }

        $sql = 'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects
            WHERE ' . $where . '
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset';
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll($sql, $params);
    }

    public function countForInfoblock($infoblockId, bool $includeDeleted = false, ?string $status = null): int
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

        $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM objects WHERE ' . $where, $params);
        return $row ? (int) $row['cnt'] : 0;
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
        if ($order === '') {
            $order = ' ORDER BY id DESC';
        }

        $limit = '';
        $offset = '';
        if (isset($override['limit']) && is_numeric($override['limit'])) {
            $limitValue = (int) $override['limit'];
            if ($limitValue > 0) {
                $limit = ' LIMIT ' . $limitValue;
            }
        }
        if (isset($override['offset']) && is_numeric($override['offset'])) {
            $offsetValue = (int) $override['offset'];
            if ($offsetValue >= 0) {
                if ($limit === '') {
                    $limit = ' LIMIT -1';
                }
                $offset = ' OFFSET ' . $offsetValue;
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
            WHERE ' . implode(' AND ', $where) . $order . $limit . $offset;
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchAll($sql, $params);
    }

    public function listByFilters(array $filters): array
    {
        $sql = isset($filters['sql']) && is_string($filters['sql']) ? trim($filters['sql']) : '';
        $params = isset($filters['params']) && is_array($filters['params']) ? $filters['params'] : [];
        if ($sql !== '') {
            return $this->listBySql($sql, $params);
        }

        $infoblockId = $filters['infoblock_id'] ?? null;
        $componentId = $filters['component_id'] ?? null;
        if ($infoblockId === null && $componentId === null) {
            return [];
        }

        $status = null;
        if (isset($filters['status'])) {
            $statusValue = trim((string) $filters['status']);
            $status = $statusValue !== '' ? $statusValue : null;
        }

        $override = [
            'where' => [],
            'params' => $params,
        ];

        $includeDeleted = false;
        if (array_key_exists('is_deleted', $filters)) {
            $isDeleted = $filters['is_deleted'];
            if ($isDeleted !== null && $isDeleted !== '') {
                $isDeletedValue = (int) (bool) $isDeleted;
                if ($isDeletedValue === 1) {
                    $includeDeleted = true;
                    $override['where'][] = 'is_deleted = :is_deleted';
                    $override['params']['is_deleted'] = 1;
                }
            }
        }

        if (isset($filters['section_id']) && $filters['section_id'] !== '' && $filters['section_id'] !== null) {
            $override['where'][] = 'section_id = :section_id';
            $override['params']['section_id'] = (int) $filters['section_id'];
        }

        if (isset($filters['site_id']) && $filters['site_id'] !== '' && $filters['site_id'] !== null) {
            $override['where'][] = 'site_id = :site_id';
            $override['params']['site_id'] = (int) $filters['site_id'];
        }

        if (isset($filters['where'])) {
            foreach ((array) $filters['where'] as $condition) {
                if (is_string($condition) && trim($condition) !== '') {
                    $override['where'][] = $condition;
                }
            }
        }

        $order = $filters['order'] ?? $filters['sort'] ?? null;
        if (is_string($order) && trim($order) !== '') {
            $override['order'] = $order;
        }

        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $limitValue = (int) $filters['limit'];
            if ($limitValue > 0) {
                $override['limit'] = $limitValue;
            }
        }

        if (isset($filters['offset']) && is_numeric($filters['offset'])) {
            $offsetValue = (int) $filters['offset'];
            if ($offsetValue >= 0) {
                $override['offset'] = $offsetValue;
            }
        }

        $useIgnoreSub = !empty($filters['ignore_sub']) || ($infoblockId === null && $componentId !== null);
        if ($componentId !== null) {
            if ($useIgnoreSub) {
                $override['component_id'] = (int) $componentId;
            } else {
                $override['where'][] = 'component_id = :component_id';
                $override['params']['component_id'] = (int) $componentId;
            }
        }
        if ($useIgnoreSub) {
            $override['ignore_sub'] = 1;
        }

        return $this->listForInfoblockWithOverride((int) ($infoblockId ?? 0), $includeDeleted, $status, $override);
    }

    public function listBySystemQuery(array $context): array
    {
        $query = $this->buildSystemQuery($context);
        if ($query === null) {
            $this->lastSelectQuery = null;
            return [];
        }

        $sql = $query['sql'];
        $params = $query['params'];
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
        $sql = 'SELECT id, site_id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted, deleted_at, status, published_at
            FROM objects WHERE id = :id LIMIT 1';
        $params = ['id' => $id];
        $this->lastSelectQuery = $this->interpolateQuery($sql, $params);

        return DB::fetchOne($sql, $params);
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

    private function buildSystemQuery(array $context): ?array
    {
        $ignoreAll = !empty($context['ignore_all']);
        if ($ignoreAll) {
            return null;
        }

        $ignoreSub = !empty($context['ignore_sub']);
        $ignoreCc = !empty($context['ignore_cc']);
        $ignoreCheck = !empty($context['ignore_check']);
        $ignoreLimit = !empty($context['ignore_limit']);

        $distinct = $context['distinct'] ?? '';
        $distinctSql = '';
        if (is_string($distinct)) {
            $distinctSql = trim($distinct);
        }
        if ($distinctSql === '' && !empty($distinct)) {
            $distinctSql = 'DISTINCT';
        }
        if ($distinctSql !== '') {
            $distinctSql .= ' ';
        }

        $querySelect = $this->normalizeQueryPart($context['query_select'] ?? '');
        $queryFrom = $this->normalizeQueryPart($context['query_from'] ?? '');
        $queryJoin = $this->normalizeQueryPart($context['query_join'] ?? '');
        $queryWhere = $this->normalizeQueryPart($context['query_where'] ?? '');
        $queryGroup = $this->normalizeQueryPart($context['query_group'] ?? '');
        $queryHaving = $this->normalizeQueryPart($context['query_having'] ?? '');
        $queryOrder = $this->normalizeQueryPart($context['query_order'] ?? '');
        $queryLimit = $this->normalizeQueryPart($context['query_limit'] ?? '');

        $infoblockId = isset($context['infoblock_id']) ? (int) $context['infoblock_id'] : 0;
        $componentId = isset($context['component_id']) ? (int) $context['component_id'] : 0;
        $status = isset($context['status']) ? trim((string) $context['status']) : '';
        $includeDeleted = !empty($context['include_deleted']);
        $perPage = isset($context['per_page']) ? (int) $context['per_page'] : 0;
        $offset = isset($context['offset']) ? (int) $context['offset'] : 0;
        if ($offset < 0) {
            $offset = 0;
        }

        $fields = 'a.id, a.site_id, a.section_id, a.infoblock_id, a.component_id,'
            . ' a.data_json, a.created_at, a.updated_at, a.is_deleted, a.deleted_at,'
            . ' a.status, a.published_at';

        $sql = 'SELECT ' . $distinctSql . $fields;
        if ($querySelect !== '') {
            $sql .= ' ' . $querySelect;
        }
        $sql .= ' FROM objects AS a';
        if ($queryFrom !== '') {
            $sql .= ' ' . $queryFrom;
        }
        if ($queryJoin !== '') {
            $sql .= ' ' . $queryJoin;
        }

        $whereParts = [];
        $params = [];

        if (!$ignoreSub && $infoblockId > 0) {
            $whereParts[] = 'a.infoblock_id = :infoblock_id';
            $params['infoblock_id'] = $infoblockId;
        }
        if ($ignoreSub && !$ignoreCc && $componentId > 0) {
            $whereParts[] = 'a.component_id = :component_id';
            $params['component_id'] = $componentId;
        }
        if (!$includeDeleted) {
            $whereParts[] = 'a.is_deleted = 0';
        }
        if (!$ignoreCheck && $status !== '') {
            $whereParts[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $systemWhere = $whereParts !== [] ? implode(' AND ', $whereParts) : '1=1';
        $sql .= ' WHERE (' . $systemWhere . ')';

        if ($queryWhere !== '') {
            $sql .= ' AND (' . $queryWhere . ')';
        }
        if ($queryGroup !== '') {
            $sql .= ' GROUP BY ' . $queryGroup;
        }
        if ($queryHaving !== '') {
            $sql .= ' HAVING ' . $queryHaving;
        }

        $order = $queryOrder !== '' ? $queryOrder : 'a.id DESC';
        $sql .= ' ORDER BY ' . $order;

        if ($queryLimit !== '') {
            $sql .= ' LIMIT ' . $queryLimit;
        } elseif (!$ignoreLimit && $perPage > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params['limit'] = $perPage;
            $params['offset'] = $offset;
        }

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    private function normalizeQueryPart($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
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
