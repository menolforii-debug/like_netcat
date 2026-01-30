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
        core()->nav()->setContext($site, $section, $sectionRepo);

        $children = $sectionRepo->listChildren((int) $section['id']);
        foreach ($children as $index => $child) {
            $children[$index]['path'] = $this->joinPath($sectionPath, $child['english_name'] ?? '');
        }

        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();
        $viewRepo = DB::hasTable('component_views') ? new ComponentViewRepo() : null;

        $infoblocks = $infoblockRepo->listForSection((int) $section['id'], true);
        $requestedObjectId = isset($_GET['object_id']) ? (int) $_GET['object_id'] : 0;
        $previewAllowed = $this->isPreviewAllowed($requestedObjectId);
        $requestedObject = null;
        $requestedObjectQuery = null;
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

            $requestedObjectQuery = $objectRepo->getLastSelectQuery();
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
            $isSingle = $requestedObject && (int) $requestedObject['infoblock_id'] === (int) $infoblock['id'];
            $viewRow = null;
            if ($viewRepo !== null) {
                $viewRow = $viewRepo->findByName((int) $component['id'], (string) $infoblock['view_template']);
            }
            $this->ensureComponentViewTemplateFile($component, (string) $infoblock['view_template'], $viewRow);

            $perPage = isset($infoblock['per_page']) ? (int) $infoblock['per_page'] : 0;
            if ($perPage < 0) {
                $perPage = 0;
            }
            $infoblock['per_page'] = $perPage;
            $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $offset = $perPage > 0 ? ($currentPage - 1) * $perPage : 0;

            $systemSettings = $this->executeSystemSettings(
                $viewRow['system_tpl'] ?? '',
                $section,
                $site,
                $infoblock,
                $component,
                $isSingle
            );

            $objects = [];
            $messageSelect = '';
            if ($isSingle && $requestedObject !== null) {
                $objects = [$requestedObject];
                $messageSelect = (string) ($requestedObjectQuery ?? '');
                $itemTitle = $this->resolveItemTitle($requestedObject, $component);
            } elseif (!empty($systemSettings['ignore_all'])) {
                $objects = [];
                $messageSelect = '';
            } else {
                $objects = $objectRepo->listBySystemQuery([
                    'infoblock_id' => (int) $infoblock['id'],
                    'component_id' => (int) $component['id'],
                    'status' => 'published',
                    'per_page' => $perPage,
                    'offset' => $offset,
                    'ignore_sub' => $systemSettings['ignore_sub'] ?? 0,
                    'ignore_cc' => $systemSettings['ignore_cc'] ?? 0,
                    'ignore_check' => $systemSettings['ignore_check'] ?? 0,
                    'ignore_all' => $systemSettings['ignore_all'] ?? 0,
                    'ignore_limit' => $systemSettings['ignore_limit'] ?? 0,
                    'query_select' => $systemSettings['query_select'] ?? '',
                    'query_from' => $systemSettings['query_from'] ?? '',
                    'query_join' => $systemSettings['query_join'] ?? '',
                    'query_where' => $systemSettings['query_where'] ?? '',
                    'query_group' => $systemSettings['query_group'] ?? '',
                    'query_having' => $systemSettings['query_having'] ?? '',
                    'query_order' => $systemSettings['query_order'] ?? '',
                    'query_limit' => $systemSettings['query_limit'] ?? '',
                    'distinct' => $systemSettings['distinct'] ?? '',
                ]);
                $messageSelect = (string) ($objectRepo->getLastSelectQuery() ?? '');
            }
            $infoblock['message_select'] = $messageSelect;

            $items = $this->decodeItems($objects);
            $totalItems = 0;
            $totalPages = 0;
            if (!$isSingle && empty($systemSettings['ignore_all']) && $perPage > 0) {
                $totalItems = $objectRepo->countBySystemQuery([
                    'infoblock_id' => (int) $infoblock['id'],
                    'component_id' => (int) $component['id'],
                    'status' => 'published',
                    'per_page' => $perPage,
                    'offset' => 0,
                    'ignore_sub' => $systemSettings['ignore_sub'] ?? 0,
                    'ignore_cc' => $systemSettings['ignore_cc'] ?? 0,
                    'ignore_check' => $systemSettings['ignore_check'] ?? 0,
                    'ignore_all' => $systemSettings['ignore_all'] ?? 0,
                    'ignore_limit' => $systemSettings['ignore_limit'] ?? 0,
                    'query_select' => $systemSettings['query_select'] ?? '',
                    'query_from' => $systemSettings['query_from'] ?? '',
                    'query_join' => $systemSettings['query_join'] ?? '',
                    'query_where' => $systemSettings['query_where'] ?? '',
                    'query_group' => $systemSettings['query_group'] ?? '',
                    'query_having' => $systemSettings['query_having'] ?? '',
                    'query_order' => $systemSettings['query_order'] ?? '',
                    'query_limit' => $systemSettings['query_limit'] ?? '',
                    'distinct' => $systemSettings['distinct'] ?? '',
                ]);
                $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
                if ($totalPages > 0 && $currentPage > $totalPages) {
                    $currentPage = $totalPages;
                }
            }
            $queryParams = $_GET;
            unset($queryParams['page'], $queryParams['object_id'], $queryParams['preview_token']);
            $infoblock['cc_env'] = [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'base_url' => $sectionPath,
                'query_params' => $queryParams,
                'per_page' => $perPage,
                'total_items' => $totalItems,
            ];

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
        $cc_env = isset($infoblock['cc_env']) && is_array($infoblock['cc_env']) ? $infoblock['cc_env'] : [];
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
        $hadSystemTpl = array_key_exists('_system_tpl_executed', $GLOBALS);
        $previousSystemTpl = $hadSystemTpl ? $GLOBALS['_system_tpl_executed'] : null;
        $GLOBALS['_snip_scope'] = get_defined_vars();

        ob_start();
        require $templatePath;
        $output = (string) ob_get_clean();

        if ($previousScope !== null) {
            $GLOBALS['_snip_scope'] = $previousScope;
        } else {
            unset($GLOBALS['_snip_scope']);
        }
        if ($hadSystemTpl) {
            $GLOBALS['_system_tpl_executed'] = $previousSystemTpl;
        } else {
            unset($GLOBALS['_system_tpl_executed']);
        }

        return $output;
    }

    private function executeSystemSettings(string $systemTpl, array $section, array $site, array $infoblock, array $component, bool $isSingle): array
    {
        $ignore_sub = 0;
        $ignore_cc = 0;
        $ignore_check = 0;
        $ignore_all = 0;
        $ignore_limit = 0;
        $query_select = '';
        $query_from = '';
        $query_join = '';
        $query_where = '';
        $query_group = '';
        $query_having = '';
        $query_order = '';
        $query_limit = '';
        $distinct = '';

        $objects = [];
        $object = null;
        $settings = $infoblock['settings'] ?? [];
        $message_select = '';
        $editMode = false;
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

        $systemTpl = Utils::stripSystemTemplateTags((string) $systemTpl);
        if ($systemTpl !== '') {
            $GLOBALS['_system_tpl_executed'] = true;
            eval($systemTpl);
        } else {
            unset($GLOBALS['_system_tpl_executed']);
        }

        $distinctValue = '';
        if (isset($distinct) && is_string($distinct)) {
            $distinctValue = trim($distinct);
        } elseif (!empty($distinct)) {
            $distinctValue = 'DISTINCT';
        }

        return [
            'ignore_sub' => !empty($ignore_sub) ? 1 : 0,
            'ignore_cc' => !empty($ignore_cc) ? 1 : 0,
            'ignore_check' => !empty($ignore_check) ? 1 : 0,
            'ignore_all' => !empty($ignore_all) ? 1 : 0,
            'ignore_limit' => !empty($ignore_limit) ? 1 : 0,
            'query_select' => is_string($query_select) ? trim($query_select) : '',
            'query_from' => is_string($query_from) ? trim($query_from) : '',
            'query_join' => is_string($query_join) ? trim($query_join) : '',
            'query_where' => is_string($query_where) ? trim($query_where) : '',
            'query_group' => is_string($query_group) ? trim($query_group) : '',
            'query_having' => is_string($query_having) ? trim($query_having) : '',
            'query_order' => is_string($query_order) ? trim($query_order) : '',
            'query_limit' => is_string($query_limit) ? trim($query_limit) : '',
            'distinct' => $distinctValue,
        ];
    }

    private function ensureComponentViewTemplateFile(array $component, string $viewName, ?array $viewRow): void
    {
        if ($viewRow === null) {
            return;
        }

        $componentKey = (string) ($component['keyword'] ?? '');
        if ($componentKey === '' || $viewName === '') {
            return;
        }

        $templatesDir = dirname(__DIR__, 2) . '/templates/component/' . $componentKey;
        $templatePath = $templatesDir . '/' . $viewName . '.php';
        $systemTpl = (string) ($viewRow['system_tpl'] ?? '');
        $needsGuard = trim($systemTpl) !== '';

        $shouldWrite = !is_file($templatePath);
        if (!$shouldWrite && $needsGuard) {
            $current = file_get_contents($templatePath);
            if ($current === false || strpos($current, '_system_tpl_executed') === false) {
                $shouldWrite = true;
            }
        }

        if (!$shouldWrite) {
            return;
        }

        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0770, true);
            @chmod($templatesDir, 0770);
        }

        $content = $this->renderComponentViewTemplate(
            (string) ($viewRow['list_tpl'] ?? ''),
            (string) ($viewRow['single_tpl'] ?? ''),
            (string) ($viewRow['system_tpl'] ?? '')
        );

        file_put_contents($templatePath, $content);
        @chmod($templatePath, 0660);
    }

    private function renderComponentViewTemplate(string $listTpl, string $singleTpl, string $systemTpl): string
    {
        $systemTpl = trim(Utils::stripSystemTemplateTags($systemTpl));
        $content = "<?php\n";
        $content .= "/** GENERATED FILE. Do not edit manually. */\n";
        $content .= "if (!isset(\$isSingle)) { \$isSingle = false; }\n";
        $content .= "if (\$isSingle && isset(\$object) && is_array(\$object)) {\n";
        $content .= "?>\n";
        $content .= $singleTpl . "\n";
        $content .= "<?php\n";
        $content .= "} else {\n";
        $content .= "?>\n";
        $content .= $listTpl . "\n";
        $content .= "<?php\n";
        $content .= "}\n";

        if ($systemTpl !== '') {
            $content .= "if (empty(\$GLOBALS['_system_tpl_executed'])) {\n";
            $content .= "    \$GLOBALS['_system_tpl_executed'] = true;\n";
            $content .= rtrim($systemTpl) . "\n";
            $content .= "}\n";
            $content .= "?>\n";
        }

        return $content;
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
