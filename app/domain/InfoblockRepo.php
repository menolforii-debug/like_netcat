<?php

final class InfoblockRepo
{
    public function listForSection($sectionId): array
    {
        return DB::fetchAll(
            'SELECT id, site_id, section_id, component_id, name, view_template, settings_json, extra_json, sort, is_enabled
            FROM infoblocks
            WHERE section_id = :section_id
            ORDER BY sort ASC, id ASC',
            ['section_id' => $sectionId]
        );
    }

    public function create(array $data): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO infoblocks (site_id, section_id, component_id, name, view_template, settings_json, extra_json, sort, is_enabled)
            VALUES (:site_id, :section_id, :component_id, :name, :view_template, :settings_json, :extra_json, :sort, :is_enabled)'
        );
        $stmt->execute([
            'site_id' => $data['site_id'],
            'section_id' => $data['section_id'],
            'component_id' => $data['component_id'],
            'name' => $data['name'],
            'view_template' => $data['view_template'],
            'settings_json' => json_encode($data['settings'] ?? [], JSON_UNESCAPED_UNICODE),
            'extra_json' => json_encode($data['extra'] ?? [], JSON_UNESCAPED_UNICODE),
            'sort' => $data['sort'] ?? 0,
            'is_enabled' => $data['is_enabled'] ?? 1,
        ]);

        return (int) DB::pdo()->lastInsertId();
    }

    public function update($id, array $data): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE infoblocks
            SET name = :name, view_template = :view_template, settings_json = :settings_json, extra_json = :extra_json, sort = :sort, is_enabled = :is_enabled
            WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'view_template' => $data['view_template'],
            'settings_json' => json_encode($data['settings'] ?? [], JSON_UNESCAPED_UNICODE),
            'extra_json' => json_encode($data['extra'] ?? [], JSON_UNESCAPED_UNICODE),
            'sort' => $data['sort'] ?? 0,
            'is_enabled' => $data['is_enabled'] ?? 1,
            'id' => $id,
        ]);
    }

    public function delete($id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM infoblocks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
