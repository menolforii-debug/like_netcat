<?php

final class ObjectRepo
{
    public function findByInfoblock($infoblockId): array
    {
        return DB::fetchAll(
            'SELECT id, section_id, infoblock_id, component_id, data_json, created_at, updated_at, is_deleted
            FROM objects
            WHERE infoblock_id = :infoblock_id AND is_deleted = 0
            ORDER BY id ASC',
            ['infoblock_id' => $infoblockId]
        );
    }
}
