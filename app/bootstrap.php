<?php

$root = dirname(__DIR__);
date_default_timezone_set('UTC');

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
require $root . '/app/nav/Nav.php';
require $root . '/app/core/Core.php';
require $root . '/app/core/Utils.php';
require $root . '/app/core/Functions.php';
require $root . '/app/core/Auth.php';
require $root . '/app/core/AdminLog.php';
require $root . '/app/core/Permission.php';
require $root . '/app/core/Seo.php';
require $root . '/app/core/FieldValidator.php';
require $root . '/app/domain/SectionRepo.php';
require $root . '/app/domain/ComponentRepo.php';
require $root . '/app/domain/InfoblockRepo.php';
require $root . '/app/domain/ObjectRepo.php';
require $root . '/app/domain/UserRepo.php';
require $root . '/app/domain/VisualFieldRepo.php';
require $root . '/app/render/Renderer.php';
require $root . '/app/ui/Layout.php';
require $root . '/app/ui/AdminLayout.php';
require $root . '/app/ui/SectionTree.php';

Auth::start();

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

$dbPath = $varDir . '/app.sqlite';
$isNew = !is_file($dbPath);
DB::connect($dbPath);
if ($isNew || !DB::hasTable('sections')) {
    $schemaPath = $root . '/app/schema.sql';
    if (is_file($schemaPath)) {
        $schemaSql = file_get_contents($schemaPath);
        if ($schemaSql !== false && trim($schemaSql) !== '') {
            DB::pdo()->exec($schemaSql);
        }
    }
}

$core = new Core(DB::pdo(), new EventBus());
$GLOBALS['core'] = $core;
require $root . '/app/events.php';

ensureDefaultLayoutTemplates($root);
ensureDefaultSite(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');
ensureDefaultVisualFields();

function core(): Core
{
    return $GLOBALS['core'];
}

function users_count(): int
{
    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');

    return $row ? (int) $row['cnt'] : 0;
}

function objects_list(array $filters): array
{
    $infoblockId = $filters['infoblock_id'] ?? null;
    $componentId = $filters['component_id'] ?? null;
    if ($infoblockId === null && $componentId === null) {
        return [];
    }

    $infoblockRepo = new InfoblockRepo();
    $componentRepo = new ComponentRepo();
    $sectionRepo = new SectionRepo();
    $objectRepo = new ObjectRepo();

    $infoblock = $infoblockId !== null ? $infoblockRepo->findById((int) $infoblockId) : null;
    if ($infoblock !== null) {
        $componentId = (int) $infoblock['component_id'];
    }
    if ($componentId === null) {
        return [];
    }

    $component = $componentRepo->findById((int) $componentId);
    if ($component === null) {
        return [];
    }

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

    $template = isset($filters['template']) ? trim((string) $filters['template']) : '';
    if ($template === '') {
        if (in_array('list', $views, true)) {
            $template = 'list';
        } elseif (!empty($views)) {
            $template = (string) $views[0];
        } else {
            $template = 'list';
        }
    }

    $componentKey = (string) ($component['keyword'] ?? '');
    $templateDir = $componentKey !== '' && $template !== ''
        ? $root . '/templates/component/' . $componentKey . '/' . $template
        : '';
    if ($templateDir === '' || !is_dir($templateDir)) {
        $template = '';
        foreach ($views as $view) {
            $candidateDir = $componentKey !== ''
                ? $root . '/templates/component/' . $componentKey . '/' . $view
                : '';
            if ($candidateDir !== '' && is_dir($candidateDir)) {
                $templateDir = $candidateDir;
                $template = $view;
                break;
            }
        }
    }
    if ($templateDir === '' || !is_dir($templateDir)) {
        return [];
    }

    $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
    $includeDeleted = !empty($filters['is_deleted']);
    $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 0;
    $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? (int) $filters['offset'] : 0;
    if ($offset < 0) {
        $offset = 0;
    }
    $useIgnoreSub = !empty($filters['ignore_sub']) || ($infoblockId === null && $componentId !== null);

    $rows = $objectRepo->listBySystemQuery([
        'infoblock_id' => (int) ($infoblock['id'] ?? $infoblockId ?? 0),
        'component_id' => (int) $component['id'],
        'status' => $status,
        'include_deleted' => $includeDeleted,
        'per_page' => $limit,
        'offset' => $offset,
        'ignore_sub' => $useIgnoreSub ? 1 : 0,
        'ignore_cc' => !empty($filters['ignore_cc']) ? 1 : 0,
        'ignore_check' => !empty($filters['ignore_check']) ? 1 : 0,
        'ignore_all' => !empty($filters['ignore_all']) ? 1 : 0,
        'ignore_limit' => !empty($filters['ignore_limit']) ? 1 : 0,
        'query_select' => $filters['query_select'] ?? '',
        'query_from' => $filters['query_from'] ?? '',
        'query_join' => $filters['query_join'] ?? '',
        'query_where' => $filters['query_where'] ?? '',
        'query_group' => $filters['query_group'] ?? '',
        'query_having' => $filters['query_having'] ?? '',
        'query_order' => $filters['query_order'] ?? '',
        'query_limit' => $filters['query_limit'] ?? '',
        'distinct' => $filters['distinct'] ?? '',
    ]);

    $items = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data_json'], true);
        if (!is_array($data)) {
            $data = [];
        }
        $items[] = [
            'id' => $row['id'],
            'data' => $data,
            'status' => $row['status'] ?? 'draft',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'controls' => [],
        ];
    }

    $section = null;
    $site = null;
    if ($infoblock !== null) {
        $section = $sectionRepo->findById((int) $infoblock['section_id']);
        if ($section !== null && isset($section['site_id'])) {
            $site = $sectionRepo->findById((int) $section['site_id']);
        }
    }
    $section = $section ?? [];
    $site = $site ?? [];
    $settings = $infoblock['settings'] ?? [];
    $message_select = (string) ($objectRepo->getLastSelectQuery() ?? '');
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

    $result = [];
    foreach ($items as $item) {
        $objects = [$item];
        $object = $item;
        $isSingle = true;

        $templatePath = $isSingle && is_file($templateDir . '/single.php')
            ? $templateDir . '/single.php'
            : $templateDir . '/list.php';
        if (!is_file($templatePath)) {
            continue;
        }

        $previousScope = $GLOBALS['_snip_scope'] ?? null;
        $GLOBALS['_snip_scope'] = get_defined_vars();

        ob_start();
        require $templatePath;
        $result[] = (string) ob_get_clean();

        if ($previousScope !== null) {
            $GLOBALS['_snip_scope'] = $previousScope;
        } else {
            unset($GLOBALS['_snip_scope']);
        }
    }

    return $result;
}

function insert_snip(string $keyword, array $vars = []): string
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return '';
    }

    $root = dirname(__DIR__);
    $snippetPath = $root . '/templates/snippets/' . $keyword . '.php';
    if (!is_file($snippetPath)) {
        return '';
    }

    if ($vars === [] && isset($GLOBALS['_snip_scope']) && is_array($GLOBALS['_snip_scope'])) {
        $vars = $GLOBALS['_snip_scope'];
    }

    if ($vars !== []) {
        extract($vars, EXTR_SKIP);
    }

    ob_start();
    require $snippetPath;
    return (string) ob_get_clean();
}

function ensureDefaultSite(string $host): void
{
    if (!DB::hasTable('sections')) {
        return;
    }

    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM sections WHERE parent_id IS NULL');
    $count = $row ? (int) $row['cnt'] : 0;
    if ($count > 0) {
        return;
    }

    $host = Utils::normalizeHost($host);
    if ($host === '') {
        $host = 'localhost';
    }

    $repo = new SectionRepo();
    $siteId = $repo->createSite('Default Site', [
        'site_domain' => $host,
        'site_mirrors' => [],
        'site_enabled' => true,
        'site_offline_html' => '<h1>Site offline</h1>',
    ]);

    $rootIndex = $repo->findRootByEnglishName($siteId, 'index');
    if ($rootIndex === null) {
        $indexId = $repo->createSection($siteId, $siteId, 'index', 'Главная', 0, []);
        $rootIndex = $repo->findById($indexId);
    }

    $rootNotFound = $repo->findRootByEnglishName($siteId, '404');
    if ($rootNotFound === null) {
        $repo->createSection($siteId, $siteId, '404', '404', 0, []);
    }

}

function ensureDefaultLayoutTemplates(string $root): void
{
    $templatesDir = $root . '/templates/layouts';
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0770, true);
        @chmod($templatesDir, 0770);
    }

    $defaultLayoutPath = $templatesDir . '/default.php';
    if (!is_file($defaultLayoutPath)) {
        $defaultLayoutSource = $templatesDir . '/default/layout.tpl.php';
        $defaultLayout = is_file($defaultLayoutSource) ? file_get_contents($defaultLayoutSource) : null;
        if ($defaultLayout === false || $defaultLayout === null) {
            $defaultLayout = '';
        }
        file_put_contents($defaultLayoutPath, $defaultLayout);
        @chmod($defaultLayoutPath, 0660);
    }

    $defaultNavPath = $templatesDir . '/default.nav.php';
    if (!is_file($defaultNavPath)) {
        $defaultNavSource = $templatesDir . '/default/nav.tpl.php';
        $defaultNav = is_file($defaultNavSource) ? file_get_contents($defaultNavSource) : null;
        if ($defaultNav === false || $defaultNav === null) {
            $defaultNav = '';
        }
        file_put_contents($defaultNavPath, $defaultNav);
        @chmod($defaultNavPath, 0660);
    }
}

function ensureDefaultVisualFields(): void
{
    if (!DB::hasTable('visual_fields')) {
        return;
    }

    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM visual_fields');
    $count = $row ? (int) $row['cnt'] : 0;
    if ($count > 0) {
        return;
    }

    $repo = new VisualFieldRepo();
    $defaults = [
        ['phone', 'телефон', 'text'],
        ['email', 'емайл', 'text'],
        ['adres', 'адрес', 'text'],
        ['map', 'карта', 'text'],
    ];

    foreach ($defaults as $index => [$name, $label, $type]) {
        $repo->create($name, $label, $type, [], $index + 1);
    }
}
