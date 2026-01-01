<?php

final class ComponentRepo
{
    public function findById($id): ?array
    {
        return DB::fetchOne(
            'SELECT id, name, keyword, fields_json FROM components WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByKeyword($keyword): ?array
    {
        return DB::fetchOne(
            'SELECT id, name, keyword, fields_json FROM components WHERE keyword = :keyword LIMIT 1',
            ['keyword' => $keyword]
        );
    }
}
