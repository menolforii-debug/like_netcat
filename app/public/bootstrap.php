<?php

if (!defined('APP_RUNTIME')) {
    define('APP_RUNTIME', 'public');
}
require __DIR__ . '/../shared/runtime_guard.php';

$root = dirname(__DIR__, 2);
$dbPath = $root . '/var/app.sqlite';
$isDbNew = !is_file($dbPath);

// shared runtime
require __DIR__ . '/../shared/bootstrap.php';
require __DIR__ . '/../shared/core/Auth.php';

// public runtime
require __DIR__ . '/render/Renderer.php';
require __DIR__ . '/ui/Layout.php';
require __DIR__ . '/helpers.php';

Auth::start();

if ($isDbNew) {
    ensureDefaultLayoutTemplates($root);
    ensureDefaultSite(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');
    ensureDefaultVisualFields();
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

    // Ensure default layout source directory exists.
    $defaultSourceDir = $templatesDir . '/default';
    if (!is_dir($defaultSourceDir)) {
        mkdir($defaultSourceDir, 0770, true);
        @chmod($defaultSourceDir, 0770);
    }

    // New canonical sources:
    $canonicalLayoutSource = $defaultSourceDir . '/default.php';
    $canonicalNavSource = $defaultSourceDir . '/default.nav.php';

    // Backward fallback sources (older branches):
    $legacyLayoutSource = $defaultSourceDir . '/layout.tpl.php';
    $legacyNavSource = $defaultSourceDir . '/nav.tpl.php';

    $resourceLayoutSource = $root . '/app/admin/resources/default_layouts_templates/default.php';
    $resourceNavSource = $root . '/app/admin/resources/default_layouts_templates/default.nav.php';

    // If canonical sources are missing, create them from resources, then legacy sources (or empty).
    if (!is_file($canonicalLayoutSource)) {
        if (is_file($resourceLayoutSource)) {
            $content = file_get_contents($resourceLayoutSource);
        } elseif (is_file($legacyLayoutSource)) {
            $content = file_get_contents($legacyLayoutSource);
        } else {
            $content = '';
        }
        if ($content === false || $content === null) {
            $content = '';
        }
        file_put_contents($canonicalLayoutSource, (string) $content);
        @chmod($canonicalLayoutSource, 0660);
    }
    if (!is_file($canonicalNavSource)) {
        if (is_file($resourceNavSource)) {
            $content = file_get_contents($resourceNavSource);
        } elseif (is_file($legacyNavSource)) {
            $content = file_get_contents($legacyNavSource);
        } else {
            $content = '';
        }
        if ($content === false || $content === null) {
            $content = '';
        }
        file_put_contents($canonicalNavSource, (string) $content);
        @chmod($canonicalNavSource, 0660);
    }

    $defaultLayoutPath = $templatesDir . '/default.php';
    if (!is_file($defaultLayoutPath)) {
        $defaultLayout = file_get_contents($canonicalLayoutSource);
        if ($defaultLayout === false || $defaultLayout === null) {
            $defaultLayout = '';
        }
        file_put_contents($defaultLayoutPath, (string) $defaultLayout);
        @chmod($defaultLayoutPath, 0660);
    }

    $defaultNavPath = $templatesDir . '/default.nav.php';
    if (!is_file($defaultNavPath)) {
        $defaultNav = file_get_contents($canonicalNavSource);
        if ($defaultNav === false || $defaultNav === null) {
            $defaultNav = '';
        }
        file_put_contents($defaultNavPath, (string) $defaultNav);
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
