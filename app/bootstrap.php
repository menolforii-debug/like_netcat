<?php

$root = dirname(__DIR__);
date_default_timezone_set('UTC');

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
require $root . '/app/core/Core.php';
require $root . '/app/core/Auth.php';
require $root . '/app/core/AdminLog.php';
require $root . '/app/core/Seo.php';
require $root . '/app/core/FieldValidator.php';
require $root . '/app/domain/SectionRepo.php';
require $root . '/app/domain/ComponentRepo.php';
require $root . '/app/domain/InfoblockRepo.php';
require $root . '/app/domain/ObjectRepo.php';
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

runMigrations(DB::pdo(), $root . '/migrations');

$core = new Core(DB::pdo(), new EventBus());
$GLOBALS['core'] = $core;

ensureDefaultSite(isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '');

function core(): Core
{
    return $GLOBALS['core'];
}

function runMigrations(PDO $pdo, $migrationsDir): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

    $files = glob(rtrim($migrationsDir, '/') . '/*.sql');
    if ($files === false) {
        return;
    }

    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        if (migrationApplied($pdo, $name)) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            continue;
        }

        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)');
        $stmt->execute([
            'name' => $name,
            'applied_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
        ]);
        $pdo->commit();
    }
}

function migrationApplied(PDO $pdo, $name): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM migrations WHERE name = :name');
    $stmt->execute(['name' => $name]);

    return (bool) $stmt->fetchColumn();
}

function usersCount(): int
{
    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');

    return $row ? (int) $row['cnt'] : 0;
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

    $host = normalizeHost($host);
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
        $indexId = $repo->createSection(null, $siteId, 'index', 'Главная', 0, []);
        $rootIndex = $repo->findById($indexId);
    }

    $rootNotFound = $repo->findRootByEnglishName($siteId, '404');
    if ($rootNotFound === null) {
        $repo->createSection(null, $siteId, '404', '404', 0, []);
    }

    if ($rootIndex !== null) {
        $children = $repo->listChildren((int) $rootIndex['id']);
        if (empty($children)) {
            $repo->createSection((int) $rootIndex['id'], $siteId, 'news', 'News', 0, []);
        }
    }
}

function normalizeHost(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return '';
    }

    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }

    return $host;
}
