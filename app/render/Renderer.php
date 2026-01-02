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

        $infoblocks = $infoblockRepo->listForSection((int) $section['id'], true);

        $infoblocksHtml = '';
        $infoblockViews = [];
        foreach ($infoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);
            $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);
            $items = $this->decodeItems($objects);

            $infoblocksHtml .= $this->renderInfoblockWithWrappers($section, $site, $infoblock, $component, $items, false);
            $infoblockViews[] = [
                'infoblock' => $infoblock,
                'component' => $component,
                'items' => $items,
            ];
        }

        $core = [
            'infoblocks_html' => $infoblocksHtml,
        ];

        $seo = $this->resolveSeo($section, $infoblocks, $infoblockViews);
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

    private function renderInfoblockWithWrappers(array $section, array $site, array $infoblock, array $component, array $items, $editMode): string
    {
        $extra = $this->decodeExtra($infoblock);
        $beforeImage = isset($extra['before_image']) ? trim((string) $extra['before_image']) : '';
        $afterImage = isset($extra['after_image']) ? trim((string) $extra['after_image']) : '';
        $beforeHtml = isset($extra['before_html']) ? (string) $extra['before_html'] : '';
        $afterHtml = isset($extra['after_html']) ? (string) $extra['after_html'] : '';

        $html = '';
        if ($beforeImage !== '') {
            $html .= '<img src="' . htmlspecialchars($beforeImage, ENT_QUOTES, 'UTF-8') . '">';
        }
        if ($beforeHtml !== '') {
            $html .= $beforeHtml;
        }

        $html .= $this->renderInfoblock($section, $site, $infoblock, $component, $items, $editMode);

        if ($afterHtml !== '') {
            $html .= $afterHtml;
        }
        if ($afterImage !== '') {
            $html .= '<img src="' . htmlspecialchars($afterImage, ENT_QUOTES, 'UTF-8') . '">';
        }

        return $html;
    }

    private function renderInfoblock(array $section, array $site, array $infoblock, array $component, array $items, $editMode): string
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

    private function resolveSeo(array $section, array $infoblocks, array $infoblockViews): array
    {
        $sectionExtra = $this->decodeExtra($section);

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
            } elseif (count($infoblocks) === 1) {
                $only = $infoblocks[0];
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
}
