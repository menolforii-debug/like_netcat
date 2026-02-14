<?php

$root = dirname(__DIR__, 2);
date_default_timezone_set('UTC');

require $root . '/app/shared/core/DB.php';
require $root . '/app/shared/core/EventBus.php';
require $root . '/app/shared/nav/Nav.php';
require $root . '/app/shared/core/Core.php';
require $root . '/app/shared/core/Utils.php';
require $root . '/app/shared/core/functions.php';
require $root . '/app/shared/core/FieldValidator.php';
require $root . '/app/shared/core/Seo.php';
require $root . '/app/shared/domain/SectionRepo.php';
require $root . '/app/shared/domain/ComponentRepo.php';
require $root . '/app/shared/domain/InfoblockRepo.php';
require $root . '/app/shared/domain/ObjectRepo.php';
require $root . '/app/shared/domain/UserRepo.php';
require $root . '/app/shared/domain/VisualFieldRepo.php';

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

$dbPath = $varDir . '/app.sqlite';
$isNew = !is_file($dbPath);
DB::connect($dbPath);
if ($isNew || !DB::hasTable('sections')) {
    $schemaPath = $root . '/app/shared/schema.sql';
    if (is_file($schemaPath)) {
        $schemaSql = file_get_contents($schemaPath);
        if ($schemaSql !== false && trim($schemaSql) !== '') {
            DB::pdo()->exec($schemaSql);
        }
    }
}

$core = new Core(DB::pdo(), new EventBus());
$GLOBALS['core'] = $core;
require $root . '/app/shared/events.php';

if ($isNew) {
    ensureDefaultSiteForNewDatabase(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');
}

function core(): Core
{
    return $GLOBALS['core'];
}

function users_count(): int
{
    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');

    return $row ? (int) $row['cnt'] : 0;
}

function ensureDefaultSiteForNewDatabase(string $host): void
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

    if ($repo->findRootByEnglishName($siteId, 'index') === null) {
        $repo->createSection($siteId, $siteId, 'index', 'Главная', 0, []);
    }

    if ($repo->findRootByEnglishName($siteId, '404') === null) {
        $repo->createSection($siteId, $siteId, '404', '404', 0, []);
    }
}
