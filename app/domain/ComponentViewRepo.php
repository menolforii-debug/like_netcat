<?php

final class ComponentViewRepo
{
    public function listForComponent(int $componentId): array
    {
        return DB::fetchAll(
            'SELECT id, component_id, name, list_tpl, single_tpl, created_at, updated_at
            FROM component_views
            WHERE component_id = :component_id
            ORDER BY id ASC',
            ['component_id' => $componentId]
        );
    }

    public function listNamesForComponent(int $componentId): array
    {
        $rows = DB::fetchAll(
            'SELECT name FROM component_views WHERE component_id = :component_id ORDER BY id ASC',
            ['component_id' => $componentId]
        );
        $names = [];
        foreach ($rows as $row) {
            if (!empty($row['name'])) {
                $names[] = (string) $row['name'];
            }
        }

        return $names;
    }

    public function findById(int $id): ?array
    {
        return DB::fetchOne(
            'SELECT id, component_id, name, list_tpl, single_tpl, created_at, updated_at
            FROM component_views WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByName(int $componentId, string $name): ?array
    {
        return DB::fetchOne(
            'SELECT id, component_id, name, list_tpl, single_tpl, created_at, updated_at
            FROM component_views WHERE component_id = :component_id AND name = :name LIMIT 1',
            [
                'component_id' => $componentId,
                'name' => $name,
            ]
        );
    }

    public function create(int $componentId, string $name, string $listTpl, string $singleTpl): int
    {
        $now = $this->now();
        $stmt = DB::pdo()->prepare(
            'INSERT INTO component_views (component_id, name, list_tpl, single_tpl, created_at, updated_at)
            VALUES (:component_id, :name, :list_tpl, :single_tpl, :created_at, :updated_at)'
        );
        $stmt->execute([
            'component_id' => $componentId,
            'name' => $name,
            'list_tpl' => $listTpl,
            'single_tpl' => $singleTpl,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, string $listTpl, string $singleTpl): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE component_views
            SET name = :name, list_tpl = :list_tpl, single_tpl = :single_tpl, updated_at = :updated_at
            WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'list_tpl' => $listTpl,
            'single_tpl' => $singleTpl,
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM component_views WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countForComponent(int $componentId): int
    {
        $row = DB::fetchOne(
            'SELECT COUNT(*) AS cnt FROM component_views WHERE component_id = :component_id',
            ['component_id' => $componentId]
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
