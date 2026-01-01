<?php

final class ComponentRepo
{
    public function findById($id): ?array
    {
        $component = DB::fetchOne(
            'SELECT id, name, keyword, fields_json FROM components WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $this->hydrate($component);
    }

    public function findByKeyword($keyword): ?array
    {
        $component = DB::fetchOne(
            'SELECT id, name, keyword, fields_json FROM components WHERE keyword = :keyword LIMIT 1',
            ['keyword' => $keyword]
        );

        return $this->hydrate($component);
    }

    private function hydrate(?array $component): ?array
    {
        if ($component === null) {
            return null;
        }

        $fields = json_decode((string) ($component['fields_json'] ?? '{}'), true);
        if (!is_array($fields)) {
            $fields = [];
        }

        $views = [];
        if (isset($fields['views']) && is_array($fields['views'])) {
            foreach ($fields['views'] as $view) {
                if (is_string($view) && $view !== '') {
                    $views[] = $view;
                }
            }
        }

        if (!in_array('list', $views, true)) {
            $views[] = 'list';
        }

        $component['views'] = array_values(array_unique($views));

        return $component;
    }
}
