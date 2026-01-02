<?php

final class SectionRepo
{
    public function findByPath($path): ?array
    {
        $slugPath = trim($path, '/');
        $segments = $slugPath === '' ? [] : explode('/', $slugPath);

        $section = $this->findRoot();
        if ($section === null) {
            return null;
        }

        foreach ($segments as $slug) {
            $section = $this->findChildBySlug((int) $section['id'], $slug);
            if ($section === null) {
                return null;
            }
        }

        return $section;
    }

    public function findById($id): ?array
    {
        $section = DB::fetchOne(
            'SELECT id, parent_id, slug, title, sort, extra_json FROM sections WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $this->withExtra($section);
    }

    public function findChildren($parentId): array
    {
        $rows = DB::fetchAll(
            'SELECT id, parent_id, slug, title, sort, extra_json FROM sections WHERE parent_id = :parent_id ORDER BY sort ASC, id ASC',
            ['parent_id' => $parentId]
        );

        return $this->withExtraList($rows);
    }

    private function findRoot(): ?array
    {
        $section = DB::fetchOne(
            'SELECT id, parent_id, slug, title, sort, extra_json FROM sections WHERE parent_id IS NULL AND slug = :slug LIMIT 1',
            ['slug' => '']
        );

        return $this->withExtra($section);
    }

    private function findChildBySlug($parentId, $slug): ?array
    {
        $section = DB::fetchOne(
            'SELECT id, parent_id, slug, title, sort, extra_json FROM sections WHERE parent_id = :parent_id AND slug = :slug LIMIT 1',
            [
                'parent_id' => $parentId,
                'slug' => $slug,
            ]
        );

        return $this->withExtra($section);
    }

    private function withExtra(?array $section): ?array
    {
        if ($section === null) {
            return null;
        }

        $extra = json_decode((string) ($section['extra_json'] ?? '{}'), true);
        if (!is_array($extra)) {
            $extra = [];
        }
        $section['extra'] = $extra;

        return $section;
    }

    private function withExtraList(array $rows): array
    {
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
