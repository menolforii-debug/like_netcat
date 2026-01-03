<?php

final class SectionRepo
{
    public function findSiteByHost(string $host): ?array
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return null;
        }

        $sites = $this->listSitesOnly();
        foreach ($sites as $site) {
            $settings = $this->getSiteSettings($site);
            $domain = $this->normalizeHost((string) ($settings['site_domain'] ?? ''));
            if ($domain !== '' && $domain === $host) {
                return $site;
            }

            foreach ($settings['site_mirrors'] as $mirror) {
                $mirrorHost = $this->normalizeHost($mirror);
                if ($mirrorHost !== '' && $mirrorHost === $host) {
                    return $site;
                }
            }
        }

        return null;
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
        $extra = $this->decodeExtra($site);
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

        return (int) DB::pdo()->lastInsertId();
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
    }

    public function delete($id): void
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
    }

    private function decodeExtra(array $row): array
    {
        if (isset($row['extra']) && is_array($row['extra'])) {
            return $row['extra'];
        }

        $decoded = json_decode((string) ($row['extra_json'] ?? '{}'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return $host;
    }
}
