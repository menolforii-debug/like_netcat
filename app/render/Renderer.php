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
        $settings = $sectionRepo->getSiteSettings($site);
        if (empty($settings['site_enabled'])) {
            http_response_code(503);
            echo (string) ($settings['site_offline_html'] ?? '');
            return;
        }
        $section = $this->resolveSectionByPath($sectionRepo, $site, $path);

        if ($section === null) {
            $section404 = $sectionRepo->findRootByEnglishName((int) $site['id'], '404');
            if ($section404 === null) {
                http_response_code(404);
                echo '404';
                return;
            }

            http_response_code(404);
            $section = $section404;
            $path = '/404';
        }

        $sectionPath = $sectionRepo->buildPath((int) $section['id']);
        $section['path'] = $sectionPath;

        $children = $sectionRepo->listChildren((int) $section['id']);
        foreach ($children as $index => $child) {
            $children[$index]['path'] = $this->joinPath($sectionPath, $child['english_name'] ?? '');
        }

        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();

        $infoblocks = $infoblockRepo->listForSection((int) $section['id'], true);
        $requestedObjectId = isset($_GET['object_id']) ? (int) $_GET['object_id'] : 0;
        $previewAllowed = $this->isPreviewAllowed($requestedObjectId);
        $requestedObject = null;
        if ($requestedObjectId > 0) {
            $requestedObject = $objectRepo->findById($requestedObjectId);
            if ($requestedObject === null || !empty($requestedObject['is_deleted'])) {
                http_response_code(404);
                echo 'Object not found';
                return;
            }

            if ((int) ($requestedObject['section_id'] ?? 0) !== (int) $section['id']) {
                http_response_code(404);
                echo 'Object not found';
                return;
            }

            if ($requestedObject['status'] !== 'published' && !$previewAllowed) {
                http_response_code(404);
                echo 'Object not found';
                return;
            }
        }

        $infoblocksHtml = '';
        $infoblockViews = [];
        $itemTitle = '';
        foreach ($infoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);

            $perPage = isset($infoblock['per_page']) ? (int) $infoblock['per_page'] : 0;
            if ($perPage < 0) {
                $perPage = 0;
            }
            $infoblock['per_page'] = $perPage;

            // Берем только опубликованные объекты сразу из БД, чтобы не гонять лишние данные.
            if ($perPage > 0) {
                $objects = $objectRepo->listForInfoblockPaged((int) $infoblock['id'], false, 'published', $perPage, 0);
            } else {
                $objects = $objectRepo->listForInfoblock((int) $infoblock['id'], false, 'published');
            }
            $infoblock['message_select'] = $objectRepo->getLastSelectQuery();

            $isSingle = $requestedObject && (int) $requestedObject['infoblock_id'] === (int) $infoblock['id'];
            if ($isSingle) {
                $objects = [$requestedObject];
                $itemTitle = $this->resolveItemTitle($requestedObject, $component);
            }

            $items = $this->decodeItems($objects);

            $infoblocksHtml .= $this->renderInfoblockWithWrappers($section, $site, $infoblock, $component, $items, $isSingle, false);
            $infoblockViews[] = [
                'infoblock' => $infoblock,
                'component' => $component,
                'items' => $items,
            ];
        }

        $core = [
            'infoblocks_html' => $infoblocksHtml,
        ];

        $seo = $this->resolveSeo($section, $infoblocks, $infoblockViews, $itemTitle);
        $layoutKey = $this->resolveLayoutKey($path, $section, $site);

        $visualSettings = $sectionRepo->resolveVisualSettings((int) $section['id']);

        Layout::render($layoutKey, [
            'title' => (string) ($seo['title'] ?? ''),
            'meta' => $seo,
            'site' => $site,
            'section' => $section,
            'visual' => $visualSettings,
            'children' => $children,
        ], function () use ($core): void {
            echo $core['infoblocks_html'] ?? '';
        });
    }

    private function renderInfoblockWithWrappers(array $section, array $site, array $infoblock, array $component, array $items, bool $isSingle, $editMode): string
    {
        $extra = Utils::decodeExtra($infoblock);
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

        $html .= $this->renderInfoblock($section, $site, $infoblock, $component, $items, $isSingle, $editMode);

        if ($afterHtml !== '') {
            $html .= $afterHtml;
        }
        if ($afterImage !== '') {
            $html .= '<img src="' . htmlspecialchars($afterImage, ENT_QUOTES, 'UTF-8') . '">';
        }

        return $html;
    }

    private function renderInfoblock(array $section, array $site, array $infoblock, array $component, array $items, bool $isSingle, $editMode): string
    {
        $core = [];
        $objects = $items;
        $object = $isSingle && !empty($objects) ? $objects[0] : null;
        $isSingle = $isSingle;
        $settings = $infoblock['settings'] ?? [];
        $message_select = isset($infoblock['message_select']) ? (string) $infoblock['message_select'] : '';
        $setFields = static function (array $item): void {
            $data = $item['data'] ?? [];
            if (!is_array($data)) {
                return;
            }
            foreach ($data as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $GLOBALS['f_' . $key] = $value;
            }
        };
        if ($object !== null) {
            $setFields($object);
        }

        $templatePath = __DIR__ . '/../../templates/component/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        $previousScope = $GLOBALS['_snip_scope'] ?? null;
        $GLOBALS['_snip_scope'] = get_defined_vars();

        ob_start();
        require $templatePath;
        $output = (string) ob_get_clean();

        if ($previousScope !== null) {
            $GLOBALS['_snip_scope'] = $previousScope;
        } else {
            unset($GLOBALS['_snip_scope']);
        }

        return $output;
    }


    private function resolveViewTemplate(array $infoblock, array $component): string
    {
        $views = [];
        if (DB::hasTable('component_views')) {
            $viewRepo = new ComponentViewRepo();
            $views = $viewRepo->listNamesForComponent((int) ($component['id'] ?? 0));
        }

        if (empty($views) && isset($component['views_json'])) {
            $decoded = json_decode((string) $component['views_json'], true);
            if (is_array($decoded)) {
                $views = $decoded;
            }
        }

        $views = array_values(array_filter($views, static function ($view): bool {
            return is_string($view) && trim($view) !== '';
        }));

        if ($views === []) {
            return 'list';
        }

        $keyword = (string) ($component['keyword'] ?? '');
        $template = isset($infoblock['view_template']) ? trim((string) $infoblock['view_template']) : '';
        if ($template !== '' && in_array($template, $views, true) && $this->templateExists($keyword, $template)) {
            return $template;
        }

        foreach ($views as $view) {
            if ($this->templateExists($keyword, $view)) {
                return $view;
            }
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
                'status' => $object['status'] ?? 'draft',
                'created_at' => $object['created_at'],
                'updated_at' => $object['updated_at'],
                'controls' => [],
            ];
        }

        return $items;
    }

    private function resolveSeo(array $section, array $infoblocks, array $infoblockViews, string $itemTitle): array
    {
        $objectData = [];
        foreach ($infoblockViews as $view) {
            if (($view['infoblock']['view_template'] ?? '') === 'item' && !empty($view['items'])) {
                $objectData = $view['items'][0]['data'] ?? [];
                break;
            }
        }

        $fallbackTitle = '';
        if ($itemTitle !== '') {
            $fallbackTitle = $itemTitle;
        } elseif (count($infoblocks) === 1) {
            $only = $infoblocks[0];
            $fallbackTitle = (string) ($section['title'] ?? '') . ' — ' . (string) ($only['name'] ?? '');
        }

        $object = null;
        if (!empty($objectData)) {
            $object = ['data' => $objectData];
        }

        return Seo::resolve($section, $object, $fallbackTitle);
    }

    private function resolveItemTitle(array $object, array $component): string
    {
        $data = json_decode((string) ($object['data_json'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }

        if (!empty($data['title'])) {
            return (string) $data['title'];
        }

        $fields = $this->extractFields($component);
        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            if (!in_array($type, ['text', 'textarea', 'string'], true)) {
                continue;
            }
            $name = $field['name'] ?? '';
            if ($name !== '' && !empty($data[$name])) {
                return (string) $data[$name];
            }
        }

        foreach ($data as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractFields(array $component): array
    {
        $fieldsJson = $component['fields_json'] ?? '{}';
        $decoded = json_decode((string) $fieldsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $fields = $decoded['fields'] ?? $decoded;
        if (!is_array($fields)) {
            return [];
        }

        return $fields;
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
        // Разрешаем путь через один запрос, чтобы не делать N+1 по дереву.
        return $repo->findByPath((int) $site['id'], $segments);
    }

    private function templateExists(string $componentKey, string $view): bool
    {
        if ($componentKey === '' || $view === '') {
            return false;
        }

        $templatePath = __DIR__ . '/../../templates/component/' . $componentKey . '/' . $view . '.php';

        return is_file($templatePath);
    }

    private function resolveLayoutKey(string $path, array $section, array $site): string
    {
        $layoutKey = trim($path, '/') === '' ? 'home' : 'default';

        $siteExtra = Utils::decodeExtra($site);
        if (!empty($siteExtra['layout']) && is_string($siteExtra['layout'])) {
            $candidate = trim($siteExtra['layout']);
            if ($candidate !== '' && Layout::layoutExists($candidate)) {
                $layoutKey = $candidate;
            }
        }

        $sectionExtra = Utils::decodeExtra($section);
        if (!empty($sectionExtra['layout']) && is_string($sectionExtra['layout'])) {
            $candidate = trim($sectionExtra['layout']);
            if ($candidate !== '' && Layout::layoutExists($candidate)) {
                $layoutKey = $candidate;
            }
        }

        return Layout::layoutExists($layoutKey) ? $layoutKey : 'default';
    }

    private function isPreviewAllowed($objectId): bool
    {
        if ($objectId <= 0) {
            return false;
        }

        $token = isset($_GET['preview_token']) ? (string) $_GET['preview_token'] : '';
        if ($token === '') {
            return false;
        }

        if (!Auth::user()) {
            return false;
        }

        return isset($_SESSION['preview_token']) && hash_equals($_SESSION['preview_token'], $token);
    }
}
