<?php

final class VisualFieldRepo
{
    public function listAll(): array
    {
        $rows = DB::fetchAll(
            'SELECT id, name, label, type, options_json, sort, created_at, updated_at
            FROM visual_fields
            ORDER BY sort ASC, id ASC'
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['options'] = $this->decodeOptions($row);
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $row = DB::fetchOne(
            'SELECT id, name, label, type, options_json, sort, created_at, updated_at
            FROM visual_fields
            WHERE id = :id
            LIMIT 1',
            ['id' => $id]
        );
        if ($row === null) {
            return null;
        }

        $row['options'] = $this->decodeOptions($row);

        return $row;
    }

    public function findByName(string $name): ?array
    {
        $row = DB::fetchOne(
            'SELECT id, name, label, type, options_json, sort, created_at, updated_at
            FROM visual_fields
            WHERE name = :name
            LIMIT 1',
            ['name' => $name]
        );
        if ($row === null) {
            return null;
        }

        $row['options'] = $this->decodeOptions($row);

        return $row;
    }

    public function create(string $name, string $label, string $type, array $options, int $sort): int
    {
        $now = $this->now();
        $stmt = DB::pdo()->prepare(
            'INSERT INTO visual_fields (name, label, type, options_json, sort, created_at, updated_at)
            VALUES (:name, :label, :type, :options_json, :sort, :created_at, :updated_at)'
        );
        $stmt->execute([
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'sort' => $sort,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::pdo()->lastInsertId();
    }

    public function update(
        int $id,
        string|array $labelOrData,
        ?string $type = null,
        ?array $options = null,
        ?int $sort = null
    ): void {
        $data = is_array($labelOrData)
            ? $labelOrData
            : [
                'label' => $labelOrData,
                'type' => $type ?? 'text',
                'options' => $options ?? [],
                'sort' => $sort ?? 0,
            ];

        $fields = [];
        $params = [
            'id' => $id,
            'updated_at' => $this->now(),
        ];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = :name';
            $params['name'] = (string) $data['name'];
        }
        if (array_key_exists('label', $data)) {
            $fields[] = 'label = :label';
            $params['label'] = (string) $data['label'];
        }
        if (array_key_exists('type', $data)) {
            $fields[] = 'type = :type';
            $params['type'] = (string) $data['type'];
        }
        if (array_key_exists('options', $data)) {
            $fields[] = 'options_json = :options_json';
            $params['options_json'] = json_encode($data['options'] ?? [], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('sort', $data)) {
            $fields[] = 'sort = :sort';
            $params['sort'] = (int) $data['sort'];
        }

        if ($fields === []) {
            return;
        }

        $sql = sprintf(
            'UPDATE visual_fields
            SET %s, updated_at = :updated_at
            WHERE id = :id',
            implode(', ', $fields)
        );
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM visual_fields WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function decodeOptions(array $row): array
    {
        $decoded = json_decode((string) ($row['options_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    }
}
