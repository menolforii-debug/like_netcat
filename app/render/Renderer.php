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
        $section['children'] = $children;

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
        $viewRepo = new ComponentViewRepo();
        foreach ($infoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);
            $infoblock['settings'] = $this->decodeSettings($infoblock);

            // Берем только опубликованные объекты сразу из БД, чтобы не гонять лишние данные.
            $viewRow = $viewRepo->findByName((int) ($component['id'] ?? 0), (string) $infoblock['view_template']);
            $queryOverride = $this->decodeQueryOverride($viewRow);
            if ($queryOverride !== null) {
                $queryOverride['component_id'] = (int) ($component['id'] ?? 0);
            }
            if ($queryOverride !== null && ($queryOverride['mode'] ?? 'extend') === 'replace' && !empty($queryOverride['sql'])) {
                $objects = $objectRepo->listBySql($queryOverride['sql'], $queryOverride['params'] ?? []);
                $objects = $this->filterOverrideObjects($objects, $infoblock, $queryOverride);
            } elseif ($queryOverride !== null) {
                $objects = $objectRepo->listForInfoblockWithOverride((int) $infoblock['id'], false, 'published', $queryOverride);
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
            'children' => $children,
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

        $templatePath = __DIR__ . '/../../templates/component/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }

    private function decodeQueryOverride(?array $viewRow): ?array
    {
        if ($viewRow === null || empty($viewRow['query_json'])) {
            return null;
        }

        $decoded = json_decode((string) $viewRow['query_json'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $mode = isset($decoded['mode']) && $decoded['mode'] === 'replace' ? 'replace' : 'extend';
        $sql = isset($decoded['sql']) && is_string($decoded['sql']) ? trim($decoded['sql']) : '';
        $params = isset($decoded['params']) && is_array($decoded['params']) ? $decoded['params'] : [];
        $where = isset($decoded['where']) ? (array) $decoded['where'] : [];
        $order = isset($decoded['order']) && is_string($decoded['order']) ? trim($decoded['order']) : '';
        $limit = isset($decoded['limit']) && is_numeric($decoded['limit']) ? (int) $decoded['limit'] : null;
        $ignoreSub = !empty($decoded['ignore_sub']);
        $systemTpl = isset($viewRow['system_tpl']) ? (string) $viewRow['system_tpl'] : '';
        if ($systemTpl !== '' && preg_match('/\\$ignore_sub\\s*=\\s*(\\d+)/', $systemTpl, $matches) === 1) {
            $ignoreSub = ((int) $matches[1]) === 1;
        }

        return [
            'mode' => $mode,
            'sql' => $sql,
            'params' => $params,
            'where' => $where,
            'order' => $order,
            'limit' => $limit,
            'ignore_sub' => $ignoreSub,
        ];
    }

    private function filterOverrideObjects(array $objects, array $infoblock, array $override): array
    {
        $filtered = [];
        $infoblockId = (int) ($infoblock['id'] ?? 0);
        $componentId = (int) ($infoblock['component_id'] ?? 0);
        $ignoreSub = !empty($override['ignore_sub']);

        foreach ($objects as $object) {
            if (!is_array($object)) {
                continue;
            }

            if (!isset($object['status']) || !isset($object['is_deleted'])) {
                continue;
            }

            if ((string) $object['status'] !== 'published') {
                continue;
            }

            if (!empty($object['is_deleted'])) {
                continue;
            }

            if ($ignoreSub) {
                if (!isset($object['component_id']) || (int) $object['component_id'] !== $componentId) {
                    continue;
                }
            } else {
                if (!isset($object['infoblock_id']) || (int) $object['infoblock_id'] !== $infoblockId) {
                    continue;
                }
            }

            $filtered[] = $object;
        }

        return $filtered;
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

    private function decodeSettings(array $row): array
    {
        if (isset($row['settings']) && is_array($row['settings'])) {
            return $row['settings'];
        }

        $decoded = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
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
