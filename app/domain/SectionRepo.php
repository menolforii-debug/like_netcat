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

    public function findChildren($parentId): array
    {
        return DB::fetchAll(
            'SELECT id, slug, title FROM sections WHERE parent_id = :parent_id ORDER BY id ASC',
            ['parent_id' => $parentId]
        );
    }

    private function findRoot(): ?array
    {
        return DB::fetchOne(
            'SELECT id, slug, title FROM sections WHERE parent_id IS NULL AND slug = :slug LIMIT 1',
            ['slug' => '']
        );
    }

    private function findChildBySlug($parentId, $slug): ?array
    {
        return DB::fetchOne(
            'SELECT id, slug, title FROM sections WHERE parent_id = :parent_id AND slug = :slug LIMIT 1',
            [
                'parent_id' => $parentId,
                'slug' => $slug,
            ]
        );
    }
}
