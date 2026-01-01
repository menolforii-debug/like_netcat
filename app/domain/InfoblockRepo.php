<?php

final class InfoblockRepo
{
    public function findBySection($sectionId): array
    {
        $rows = DB::fetchAll(
            'SELECT id, section_id, component_id, name, settings_json, view_template, sort, is_enabled, extra_json
            FROM infoblocks
            WHERE section_id = :section_id AND is_enabled = 1
            ORDER BY sort ASC, id ASC',
            ['section_id' => $sectionId]
        );

        foreach ($rows as $index => $row) {
            $rows[$index] = $this->hydrate($row);
        }

        return $rows;
    }

    public function findById($id): ?array
    {
        $row = DB::fetchOne(
            'SELECT id, section_id, component_id, name, settings_json, view_template, sort, is_enabled, extra_json
            FROM infoblocks
            WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $this->hydrate($row);
    }

    private function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $extra = json_decode((string) ($row['extra_json'] ?? '{}'), true);
        if (!is_array($extra)) {
            $extra = [];
        }
        $row['extra'] = $extra;

        $viewTemplate = isset($row['view_template']) ? trim((string) $row['view_template']) : '';
        $row['view_template'] = $viewTemplate !== '' ? $viewTemplate : 'list';

        return $row;
    }
}
