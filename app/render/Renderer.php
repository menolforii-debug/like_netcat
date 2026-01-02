<?php

final class Renderer
{
    public function renderPath($path): void
    {
        $sectionRepo = new SectionRepo();
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $site = $sectionRepo->findSiteByHost($host);
        if ($site === null) {
            http_response_code(404);
            echo 'Site not found';
            return;
        }

        $this->renderSitePath($site, $path);
    }

    public function renderSitePath(array $site, string $path): void
    {
        $sectionRepo = new SectionRepo();
        $section = $this->resolveSectionByPath($sectionRepo, $site, $path);

        if ($section === null) {
            http_response_code(404);
            echo 'Section not found';
            return;
        }

        $sectionPath = $this->buildSectionPath($sectionRepo, (int) $section['id']);
        $section['path'] = $sectionPath;

        $children = $sectionRepo->listChildren((int) $section['id']);
        foreach ($children as $index => $child) {
            $children[$index]['path'] = $this->joinPath($sectionPath, $child['english_name'] ?? '');
        }

        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo(core()->events());

        $infoblocks = $infoblockRepo->listForSection((int) $section['id']);
        $enabledInfoblocks = array_values(array_filter($infoblocks, static function (array $infoblock): bool {
            return !empty($infoblock['is_enabled']);
        }));

        $infoblocksHtml = '';
        $infoblockViews = [];
        foreach ($enabledInfoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);
            $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);
            $items = $this->decodeItems($objects);

            $infoblocksHtml .= $this->renderInfoblock($section, $infoblock, $component, $items, false);
            $infoblockViews[] = [
                'infoblock' => $infoblock,
                'component' => $component,
                'items' => $items,
            ];
        }

        $core = [
            'infoblocks_html' => $infoblocksHtml,
        ];

        $seo = $this->resolveSeo($section, $enabledInfoblocks, $infoblockViews);
        $this->renderDocumentStart($seo);
        $this->renderSection($section, $children, $core, false);
        $this->renderDocumentEnd();
    }

    private function renderDocumentStart(array $seo): void
    {
        $title = (string) ($seo['title'] ?? '');
        Layout::renderDocumentStart($title, $seo);
        Layout::renderNavbar('CMS', [
            ['label' => 'Админ', 'href' => '/admin.php'],
        ]);
        echo '<main class="container mb-5">';
    }

    private function renderDocumentEnd(): void
    {
        echo '</main>';
        Layout::renderDocumentEnd();
    }

    private function renderSection(array $section, array $children, array $core, $editMode): void
    {
        $component = ['keyword' => 'section'];
        $infoblock = ['view_template' => 'default'];
        $items = $children;

        $templatePath = __DIR__ . '/../../templates/section/default.php';
        if (!is_file($templatePath)) {
            http_response_code(500);
            echo 'Template not found';
            return;
        }

        require $templatePath;
    }

    private function renderInfoblock(array $section, array $infoblock, array $component, array $items, $editMode): string
    {
        $core = [];

        $templatePath = __DIR__ . '/../../templates/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }

    private function resolveViewTemplate(array $infoblock, array $component): string
    {
        $views = [];
        if (isset($component['views_json'])) {
            $decoded = json_decode((string) $component['views_json'], true);
            if (is_array($decoded)) {
                $views = $decoded;
            }
        }

        $template = isset($infoblock['view_template']) ? trim((string) $infoblock['view_template']) : '';
        if ($template !== '' && in_array($template, $views, true)) {
            return $template;
        }

        return 'list';
    }

    private function decodeItems(array $objects): array
    {
        $items = [];

        foreach ($objects as $object) {
            $data = json_decode((string) $object['data_json'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $items[] = [
                'id' => $object['id'],
                'data' => $data,
                'status' => $object['status'] ?? 'published',
                'created_at' => $object['created_at'],
                'updated_at' => $object['updated_at'],
                'controls' => [],
            ];
        }

        return $items;
    }

    private function resolveSeo(array $section, array $enabledInfoblocks, array $infoblockViews): array
    {
        $sectionExtra = [];
        if (isset($section['extra']) && is_array($section['extra'])) {
            $sectionExtra = $section['extra'];
        } elseif (isset($section['extra_json'])) {
            $decoded = json_decode((string) $section['extra_json'], true);
            if (is_array($decoded)) {
                $sectionExtra = $decoded;
            }
        }

        $objectData = [];
        foreach ($infoblockViews as $view) {
            if (($view['infoblock']['view_template'] ?? '') === 'item' && !empty($view['items'])) {
                $objectData = $view['items'][0]['data'] ?? [];
                break;
            }
        }

        $title = '';
        if (!empty($objectData['seo_title'])) {
            $title = (string) $objectData['seo_title'];
        } elseif (!empty($sectionExtra['seo_title'])) {
            $title = (string) $sectionExtra['seo_title'];
        }

        if ($title === '') {
            if (!empty($objectData['title'])) {
                $title = (string) $objectData['title'];
            } elseif (count($enabledInfoblocks) === 1) {
                $only = $enabledInfoblocks[0];
                $title = (string) ($section['title'] ?? '') . ' — ' . (string) ($only['name'] ?? '');
            } else {
                $title = (string) ($section['title'] ?? '');
            }
        }

        $description = '';
        if (!empty($objectData['seo_description'])) {
            $description = (string) $objectData['seo_description'];
        } elseif (!empty($sectionExtra['seo_description'])) {
            $description = (string) $sectionExtra['seo_description'];
        }

        $keywords = '';
        if (!empty($objectData['seo_keywords'])) {
            $keywords = (string) $objectData['seo_keywords'];
        } elseif (!empty($sectionExtra['seo_keywords'])) {
            $keywords = (string) $sectionExtra['seo_keywords'];
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
        ];
    }

    private function buildSectionPath(SectionRepo $repo, $sectionId): string
    {
        $segments = [];
        $currentId = $sectionId;

        while ($currentId !== null) {
            $section = $repo->findById($currentId);
            if ($section === null) {
                break;
            }

            if (!empty($section['english_name'])) {
                $segments[] = $section['english_name'];
            }

            $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
        }

        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', array_reverse($segments)) . '/';
    }

    private function joinPath($basePath, $englishName): string
    {
        $basePath = rtrim($basePath, '/');
        $englishName = trim((string) $englishName, '/');

        if ($basePath === '') {
            $basePath = '/';
        }

        if ($englishName === '') {
            return $basePath . '/';
        }

        if ($basePath === '/') {
            return '/' . $englishName . '/';
        }

        return $basePath . '/' . $englishName . '/';
    }

    private function resolveSectionByPath(SectionRepo $repo, array $site, string $path): ?array
    {
        $segments = trim($path, '/') === '' ? [] : explode('/', trim($path, '/'));
        $current = $site;

        foreach ($segments as $segment) {
            $children = $repo->listChildren((int) $current['id']);
            $next = null;
            foreach ($children as $child) {
                if (($child['english_name'] ?? null) === $segment) {
                    $next = $child;
                    break;
                }
            }

            if ($next === null) {
                return null;
            }

            $current = $next;
        }

        return $current;
    }
}
