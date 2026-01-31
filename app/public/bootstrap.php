<?php

require __DIR__ . '/../shared/bootstrap.php';
require __DIR__ . '/../shared/core/Auth.php';
require __DIR__ . '/render/Renderer.php';
require __DIR__ . '/ui/Layout.php';
require __DIR__ . '/helpers.php';

Auth::start();

$root = dirname(__DIR__, 2);
ensureDefaultLayoutTemplates($root);
ensureDefaultSite(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');
ensureDefaultVisualFields();

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
