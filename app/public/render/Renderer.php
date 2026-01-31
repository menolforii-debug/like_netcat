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

            $perPage = isset($infoblock['per_page']) ? (int) $infoblock['per_page'] : 0;
            if ($perPage < 0) {
                $perPage = 0;
            }
            $infoblock['per_page'] = $perPage;
            $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $offset = $perPage > 0 ? ($currentPage - 1) * $perPage : 0;

            $systemSettings = $this->loadSystemSettings(
                (string) ($component['keyword'] ?? ''),
                (string) $infoblock['view_template'],
                $section,
                $site,
                $infoblock,
                $component,
                $isSingle
            );
            $helpers = $systemSettings['helpers'] ?? [];
            if (!is_array($helpers)) {
                $helpers = [];
            }

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

            $infoblocksHtml .= $this->renderInfoblockWithWrappers($section, $site, $infoblock, $component, $items, $isSingle, false, $helpers);
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
        $sectionExtra = Utils::decodeExtra($section);
        $h1 = isset($sectionExtra['h1']) && is_string($sectionExtra['h1']) ? trim($sectionExtra['h1']) : '';
        if ($h1 === '') {
            $h1 = (string) ($section['title'] ?? '');
        }

        Layout::render($layoutKey, [
            'title' => (string) ($seo['title'] ?? ''),
            'meta' => $seo,
            'site' => $site,
            'section' => $section,
            'h1' => $h1,
            'visual' => $visualSettings,
            'children' => $children,
        ], function () use ($core): void {
            echo $core['infoblocks_html'] ?? '';
        });
    }

    private function renderInfoblockWithWrappers(array $section, array $site, array $infoblock, array $component, array $items, bool $isSingle, $editMode, array $helpers): string
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

        $html .= $this->renderInfoblock($section, $site, $infoblock, $component, $items, $isSingle, $editMode, $helpers);

        if ($afterHtml !== '') {
            $html .= $afterHtml;
        }
        if ($afterImage !== '') {
            $html .= '<img src="' . htmlspecialchars($afterImage, ENT_QUOTES, 'UTF-8') . '">';
        }

        return $html;
    }

    private function renderInfoblock(array $section, array $site, array $infoblock, array $component, array $items, bool $isSingle, $editMode, array $helpers): string
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

        $templatePath = $this->resolveTemplatePath(
            (string) ($component['keyword'] ?? ''),
            (string) $infoblock['view_template'],
            $isSingle
        );
        if ($templatePath === '') {
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

    private function loadSystemSettings(string $componentKey, string $viewName, array $section, array $site, array $infoblock, array $component, bool $isSingle): array
    {
        $systemPath = $this->resolveSystemPath($componentKey, $viewName);
        if ($systemPath === '') {
            return $this->normalizeSystemSettings([]);
        }

        $context = [
            'section' => $section,
            'site' => $site,
            'infoblock' => $infoblock,
            'component' => $component,
            'isSingle' => $isSingle,
        ];
        extract($context, EXTR_SKIP);
        $result = require $systemPath;
        if (!is_array($result)) {
            throw new RuntimeException('system.php must return an array.');
        }

        $helpers = [];
        if (isset($result['helpers']) && is_array($result['helpers'])) {
            $helpers = $result['helpers'];
        }

        $normalized = $this->normalizeSystemSettings($result);
        $normalized['helpers'] = $helpers;

        return $normalized;
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

        $views = array_values(array_filter($views, static function ($view): bool {
            return is_string($view) && trim($view) !== '';
        }));

        if ($views === []) {
            return 'default';
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

        return 'default';
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

        $dir = __DIR__ . '/../../../templates/component/' . $componentKey . '/' . $view;
        if (!is_dir($dir)) {
            return false;
        }

        return is_file($dir . '/list.php') || is_file($dir . '/single.php');
    }

    private function resolveTemplatePath(string $componentKey, string $view, bool $isSingle): string
    {
        if ($componentKey === '' || $view === '') {
            return '';
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
            return '';
        }

        $baseDir = __DIR__ . '/../../../templates/component';
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return '';
        }
        $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $dir = $baseReal . $componentKey . '/' . $view;
        $realDir = realpath($dir);
        if ($realDir === false || strpos($realDir . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
            return '';
        }

        $singlePath = $realDir . '/single.php';
        $listPath = $realDir . '/list.php';
        if ($isSingle && is_file($singlePath)) {
            return $singlePath;
        }
        if (is_file($listPath)) {
            return $listPath;
        }
        if (is_file($singlePath)) {
            return $singlePath;
        }

        return '';
    }

    private function resolveSystemPath(string $componentKey, string $view): string
    {
        if ($componentKey === '' || $view === '') {
            return '';
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
            return '';
        }

        $baseDir = __DIR__ . '/../../../templates/component';
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return '';
        }
        $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $path = $baseReal . $componentKey . '/' . $view . '/system.php';
        $realPath = realpath($path);
        if ($realPath === false || strpos($realPath, $baseReal) !== 0) {
            return '';
        }
        return is_file($realPath) ? $realPath : '';
    }

    private function normalizeSystemSettings(array $settings): array
    {
        $distinctValue = '';
        if (isset($settings['distinct']) && is_string($settings['distinct'])) {
            $distinctValue = trim($settings['distinct']);
        } elseif (!empty($settings['distinct'])) {
            $distinctValue = 'DISTINCT';
        }

        return [
            'ignore_sub' => !empty($settings['ignore_sub']) ? 1 : 0,
            'ignore_cc' => !empty($settings['ignore_cc']) ? 1 : 0,
            'ignore_check' => !empty($settings['ignore_check']) ? 1 : 0,
            'ignore_all' => !empty($settings['ignore_all']) ? 1 : 0,
            'ignore_limit' => !empty($settings['ignore_limit']) ? 1 : 0,
            'query_select' => isset($settings['query_select']) && is_string($settings['query_select']) ? trim($settings['query_select']) : '',
            'query_from' => isset($settings['query_from']) && is_string($settings['query_from']) ? trim($settings['query_from']) : '',
            'query_join' => isset($settings['query_join']) && is_string($settings['query_join']) ? trim($settings['query_join']) : '',
            'query_where' => isset($settings['query_where']) && is_string($settings['query_where']) ? trim($settings['query_where']) : '',
            'query_group' => isset($settings['query_group']) && is_string($settings['query_group']) ? trim($settings['query_group']) : '',
            'query_having' => isset($settings['query_having']) && is_string($settings['query_having']) ? trim($settings['query_having']) : '',
            'query_order' => isset($settings['query_order']) && is_string($settings['query_order']) ? trim($settings['query_order']) : '',
            'query_limit' => isset($settings['query_limit']) && is_string($settings['query_limit']) ? trim($settings['query_limit']) : '',
            'distinct' => $distinctValue,
        ];
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
