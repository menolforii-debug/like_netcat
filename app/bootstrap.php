<?php

$root = dirname(__DIR__);
date_default_timezone_set('UTC');

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
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
require $root . '/app/domain/ComponentViewRepo.php';
require $root . '/app/domain/InfoblockRepo.php';
require $root . '/app/domain/ObjectRepo.php';
require $root . '/app/domain/UserRepo.php';
require $root . '/app/domain/VisualFieldRepo.php';
require $root . '/app/domain/SnippetRepo.php';
require $root . '/app/MigrationRunner.php';
require $root . '/app/render/Renderer.php';
require $root . '/app/ui/Layout.php';
require $root . '/app/ui/AdminLayout.php';
require $root . '/app/ui/SectionTree.php';

Auth::start();

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

DB::connect($varDir . '/app.sqlite');

// Миграции выполняем только в CLI или в админке, чтобы не тормозить фронтенд.
$scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$runMigrations = PHP_SAPI === 'cli' || $scriptName === 'admin.php';
if ($runMigrations) {
    MigrationRunner::run(DB::pdo(), $root . '/migrations');
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
    $repo = new ObjectRepo();

    return $repo->listByFilters($filters);
}

function insert_snip(string $keyword, array $vars = []): string
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return '';
    }

    $repo = new SnippetRepo();
    $snippet = $repo->findByKeyword($keyword);
    if ($snippet === null) {
        return '';
    }

    $content = isset($snippet['content']) ? (string) $snippet['content'] : '';
    if ($vars === [] && isset($GLOBALS['_snip_scope']) && is_array($GLOBALS['_snip_scope'])) {
        $vars = $GLOBALS['_snip_scope'];
    }

    if ($vars !== []) {
        extract($vars, EXTR_SKIP);
    }

    ob_start();
    try {
        eval('?>' . $content);
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }

    $rendered = (string) ob_get_clean();
    echo $rendered;

    return $rendered;
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
