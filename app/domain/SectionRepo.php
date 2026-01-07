<?php

final class SectionRepo
{
    public function findSiteByHost(string $host): ?array
    {
        $sites = $this->listSitesOnly();
        if (empty($sites)) {
            return null;
        }

        return $sites[0];
    }

    public function listSites(): array
    {
        return $this->listSitesOnly();
    }

    public function listSitesOnly(): array
    {
        return DB::fetchAll(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE parent_id IS NULL AND id = site_id
            ORDER BY id ASC'
        );
    }

    public function findByEnglishName($siteId, string $englishName, $excludeId = null): ?array
    {
        $params = [
            'site_id' => $siteId,
            'english_name' => $englishName,
        ];
        $where = 'site_id = :site_id AND english_name = :english_name';
        if ($excludeId !== null) {
            $where .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        return DB::fetchOne(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE ' . $where . '
            LIMIT 1',
            $params
        );
    }

    public function findRootByEnglishName($siteId, string $englishName): ?array
    {
        return DB::fetchOne(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE site_id = :site_id AND parent_id = :parent_id AND english_name = :english_name
            LIMIT 1',
            [
                'site_id' => $siteId,
                'parent_id' => $siteId,
                'english_name' => $englishName,
            ]
        );
    }

    public function existsSiblingEnglishName($siteId, $parentId, string $englishName, $excludeId = null): bool
    {
        $params = [
            'site_id' => $siteId,
            'parent_id' => $parentId,
            'english_name' => $englishName,
        ];
        $where = 'site_id = :site_id AND parent_id = :parent_id AND english_name = :english_name';
        if ($excludeId !== null) {
            $where .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $row = DB::fetchOne(
            'SELECT 1 FROM sections WHERE ' . $where . ' LIMIT 1',
            $params
        );

        return $row !== null;
    }

    public function getSiteSettings(array $site): array
    {
        $extra = Utils::decodeExtra($site);
        $mirrors = [];
        if (isset($extra['site_mirrors']) && is_array($extra['site_mirrors'])) {
            foreach ($extra['site_mirrors'] as $mirror) {
                if (is_string($mirror) && $mirror !== '') {
                    $mirrors[] = $mirror;
                }
            }
        }

        return [
            'site_domain' => isset($extra['site_domain']) ? (string) $extra['site_domain'] : '',
            'site_mirrors' => array_values(array_unique($mirrors)),
            'site_enabled' => array_key_exists('site_enabled', $extra) ? (bool) $extra['site_enabled'] : true,
            'site_offline_html' => isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '<h1>Site offline</h1>',
        ];
    }

    public function findById($id): ?array
    {
        return DB::fetchOne(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE id = :id
            LIMIT 1',
            ['id' => $id]
        );
    }

    public function listChildren($parentId): array
    {
        return DB::fetchAll(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE parent_id = :parent_id
            ORDER BY sort ASC, id ASC',
            ['parent_id' => $parentId]
        );
    }

    public function createSite(string $title, array $extra = []): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO sections (parent_id, site_id, english_name, title, sort, extra_json)
            VALUES (NULL, 0, NULL, :title, 0, :extra_json)'
        );
        $stmt->execute([
            'title' => $title,
            'extra_json' => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        $update = DB::pdo()->prepare('UPDATE sections SET site_id = :site_id WHERE id = :id');
        $update->execute([
            'site_id' => $id,
            'id' => $id,
        ]);

        core()->events()->emit('section.created', [
            'id' => $id,
            'data' => [
                'parent_id' => null,
                'site_id' => $id,
                'english_name' => null,
                'title' => $title,
                'sort' => 0,
                'extra' => $extra,
                'is_site' => true,
            ],
        ]);

        return $id;
    }

    public function createSection($parentId, $siteId, string $englishName, string $title, int $sort = 0, array $extra = []): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO sections (parent_id, site_id, english_name, title, sort, extra_json)
            VALUES (:parent_id, :site_id, :english_name, :title, :sort, :extra_json)'
        );
        $stmt->execute([
            'parent_id' => $parentId,
            'site_id' => $siteId,
            'english_name' => $englishName,
            'title' => $title,
            'sort' => $sort,
            'extra_json' => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) DB::pdo()->lastInsertId();
        core()->events()->emit('section.created', [
            'id' => $id,
            'data' => [
                'parent_id' => $parentId,
                'site_id' => $siteId,
                'english_name' => $englishName,
                'title' => $title,
                'sort' => $sort,
                'extra' => $extra,
                'is_site' => false,
            ],
        ]);

        return $id;
    }

    public function update($id, array $data): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE sections
            SET parent_id = :parent_id, site_id = :site_id, english_name = :english_name, title = :title, sort = :sort, extra_json = :extra_json
            WHERE id = :id'
        );
        $stmt->execute([
            'parent_id' => $data['parent_id'],
            'site_id' => $data['site_id'],
            'english_name' => $data['english_name'],
            'title' => $data['title'],
            'sort' => $data['sort'] ?? 0,
            'extra_json' => json_encode($data['extra'] ?? [], JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);

        core()->events()->emit('section.updated', [
            'id' => $id,
            'data' => $data,
        ]);
    }

    public function delete($id): void
    {
        $this->deleteStrict($id);
    }

    public function deleteStrict($id): void
    {
        $child = DB::fetchOne('SELECT 1 FROM sections WHERE parent_id = :id LIMIT 1', ['id' => $id]);
        if ($child) {
            throw new RuntimeException('Нельзя удалить раздел с дочерними разделами.');
        }

        $infoblock = DB::fetchOne('SELECT 1 FROM infoblocks WHERE section_id = :id LIMIT 1', ['id' => $id]);
        if ($infoblock) {
            throw new RuntimeException('Нельзя удалить раздел с инфоблоками.');
        }

        $stmt = DB::pdo()->prepare('DELETE FROM sections WHERE id = :id');
        $stmt->execute(['id' => $id]);

        core()->events()->emit('section.deleted', ['id' => $id]);
    }

    public function deleteRecursive(int $id): void
    {
        $this->deleteSectionRecursive($id);
    }

    public function deleteSiteRecursive(int $siteId): void
    {
        $site = $this->findById($siteId);
        if ($site === null || $site['parent_id'] !== null) {
            throw new RuntimeException('Сайт не найден.');
        }

        $this->deleteSectionRecursive($siteId, $siteId);
    }

    public function deleteSectionRecursive(int $sectionId, ?int $expectedSiteId = null): void
    {
        $section = $this->findById($sectionId);
        if ($section === null) {
            throw new RuntimeException('Раздел не найден.');
        }

        if ($expectedSiteId !== null && (int) $section['site_id'] !== (int) $expectedSiteId) {
            throw new RuntimeException('Раздел не принадлежит указанному сайту.');
        }

        $pdo = DB::pdo();
        $manageTransaction = !$pdo->inTransaction();

        if ($manageTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $sectionIds = $this->listTreeIds($sectionId);
            if (empty($sectionIds)) {
                throw new RuntimeException('Раздел не найден.');
            }

            $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

            $infoblockIds = DB::fetchAll(
                'SELECT id FROM infoblocks WHERE section_id IN (' . $placeholders . ')',
                $sectionIds
            );
            $infoblockIds = array_map(static fn(array $row): int => (int) $row['id'], $infoblockIds);

            if (!empty($infoblockIds)) {
                $infoblockPlaceholders = implode(',', array_fill(0, count($infoblockIds), '?'));
                $stmt = $pdo->prepare('DELETE FROM objects WHERE infoblock_id IN (' . $infoblockPlaceholders . ')');
                $stmt->execute($infoblockIds);

                $stmt = $pdo->prepare('DELETE FROM infoblocks WHERE id IN (' . $infoblockPlaceholders . ')');
                $stmt->execute($infoblockIds);
            }

            $stmt = $pdo->prepare('DELETE FROM sections WHERE id IN (' . $placeholders . ')');
            $stmt->execute($sectionIds);

            if ($manageTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($manageTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        core()->events()->emit('section.deleted', ['id' => $sectionId]);
    }

    public function isDescendant(int $sectionId, int $candidateParentId): bool
    {
        if ($sectionId <= 0 || $candidateParentId <= 0) {
            return false;
        }

        $row = DB::fetchOne(
            'WITH RECURSIVE tree(id) AS (
                SELECT id FROM sections WHERE id = :section_id
                UNION ALL
                SELECT s.id FROM sections s JOIN tree t ON s.parent_id = t.id
            )
            SELECT 1 FROM tree WHERE id = :candidate_id AND id != :section_id LIMIT 1',
            [
                'section_id' => $sectionId,
                'candidate_id' => $candidateParentId,
            ]
        );

        return $row !== null;
    }

    public function buildPath(int $sectionId): string
    {
        $segments = [];
        $currentId = $sectionId;
        while ($currentId !== null) {
            $section = $this->findById($currentId);
            if ($section === null) {
                break;
            }

            if (!empty($section['english_name'])) {
                if ($section['english_name'] === 'index' && (int) $section['parent_id'] === (int) $section['site_id']) {
                    // Пропускаем системную "Главную" в пути.
                } else {
                    $segments[] = $section['english_name'];
                }
            }

            $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
        }

        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', array_reverse($segments)) . '/';
    }

    public function resolveVisualSettings(int $sectionId): array
    {
        $chain = [];
        $currentId = $sectionId;
        while ($currentId !== null) {
            $section = $this->findById($currentId);
            if ($section === null) {
                break;
            }
            $chain[] = $section;
            $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
        }

        $chain = array_reverse($chain);
        $resolved = [];
        foreach ($chain as $section) {
            $extra = Utils::decodeExtra($section);
            $visual = $extra['visual_settings'] ?? [];
            if (!is_array($visual)) {
                continue;
            }
            foreach ($visual as $key => $value) {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    private function listTreeIds(int $rootId): array
    {
        $rows = DB::fetchAll(
            'WITH RECURSIVE tree(id) AS (
                SELECT id FROM sections WHERE id = :id
                UNION ALL
                SELECT s.id FROM sections s JOIN tree t ON s.parent_id = t.id
            )
            SELECT id FROM tree',
            ['id' => $rootId]
        );

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

}
