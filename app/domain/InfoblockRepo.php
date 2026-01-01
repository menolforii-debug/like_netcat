<?php

final class InfoblockRepo
{
    public function findBySection($sectionId): array
    {
        return DB::fetchAll(
            'SELECT id, section_id, component_id, name, settings_json, view_template, sort, is_enabled
            FROM infoblocks
            WHERE section_id = :section_id AND is_enabled = 1
            ORDER BY sort ASC, id ASC',
            ['section_id' => $sectionId]
        );
    }
}
