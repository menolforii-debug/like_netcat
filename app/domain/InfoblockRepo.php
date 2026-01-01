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
            $extra = json_decode((string) ($row['extra_json'] ?? '{}'), true);
            if (!is_array($extra)) {
                $extra = [];
            }
            $rows[$index]['extra'] = $extra;
        }

        return $rows;
    }
}
