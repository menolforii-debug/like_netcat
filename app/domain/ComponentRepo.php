<?php

final class ComponentRepo
{
    public function listAll(): array
    {
        return DB::fetchAll(
            'SELECT id, keyword, name, fields_json, views_json FROM components ORDER BY id ASC'
        );
    }

    public function findById($id): ?array
    {
        return DB::fetchOne(
            'SELECT id, keyword, name, fields_json, views_json FROM components WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByKeyword($keyword): ?array
    {
        return DB::fetchOne(
            'SELECT id, keyword, name, fields_json, views_json FROM components WHERE keyword = :keyword LIMIT 1',
            ['keyword' => $keyword]
        );
    }

    public function create(string $keyword, string $name, array $fields, array $views = []): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO components (keyword, name, fields_json, views_json)
            VALUES (:keyword, :name, :fields_json, :views_json)'
        );
        $stmt->execute([
            'keyword' => $keyword,
            'name' => $name,
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'views_json' => json_encode($views, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        core()->events()->emit('component.created', [
            'id' => $id,
            'data' => [
                'keyword' => $keyword,
                'name' => $name,
                'fields' => $fields,
                'views' => $views,
            ],
        ]);

        return $id;
    }

    public function update($id, string $keyword, string $name, array $fields, array $views = []): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE components
            SET keyword = :keyword, name = :name, fields_json = :fields_json, views_json = :views_json
            WHERE id = :id'
        );
        $stmt->execute([
            'keyword' => $keyword,
            'name' => $name,
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'views_json' => json_encode($views, JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);

        core()->events()->emit('component.updated', [
            'id' => $id,
            'data' => [
                'keyword' => $keyword,
                'name' => $name,
                'fields' => $fields,
                'views' => $views,
            ],
        ]);
    }

    public function delete($id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM components WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
