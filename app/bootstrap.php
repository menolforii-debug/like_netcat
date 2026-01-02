<?php

$root = dirname(__DIR__);
date_default_timezone_set('UTC');

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
require $root . '/app/core/Core.php';
require $root . '/app/core/Auth.php';
require $root . '/app/core/Permission.php';
require $root . '/app/core/Workflow.php';
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

$skipMigrations = [];
if (DB::hasColumn('sections', 'slug')) {
    $skipMigrations[] = '002_section_slug.sql';
}
if (DB::hasColumn('objects', 'deleted_at')) {
    $skipMigrations[] = '021_objects_deleted_at.sql';
}
if (DB::hasColumn('sections', 'sort')) {
    $skipMigrations[] = '040_sections_sort.sql';
}

runMigrations(DB::pdo(), $root . '/migrations', $skipMigrations);

ensureColumn('sections', 'extra_json', "ALTER TABLE sections ADD COLUMN extra_json TEXT NOT NULL DEFAULT '{}'");
ensureColumn('infoblocks', 'extra_json', "ALTER TABLE infoblocks ADD COLUMN extra_json TEXT NOT NULL DEFAULT '{}'");
ensureColumn('objects', 'status', "ALTER TABLE objects ADD COLUMN status TEXT NOT NULL DEFAULT 'published'");
ensureColumn('objects', 'published_at', 'ALTER TABLE objects ADD COLUMN published_at TEXT NULL');

$core = new Core(DB::pdo(), new EventBus());
$GLOBALS['core'] = $core;

if (DB::hasTable('users') && usersCount() === 0) {
    seedAdminUser();
}

function core(): Core
{
    return $GLOBALS['core'];
}

function runMigrations(PDO $pdo, $migrationsDir, array $skipMigrations = []): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

    $files = glob(rtrim($migrationsDir, '/') . '/*.sql');
    if ($files === false) {
        return;
    }

    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $skipMigrations, true)) {
            if (!migrationApplied($pdo, $name)) {
                $stmt = $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)');
                $stmt->execute([
                    'name' => $name,
                    'applied_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
                ]);
            }
            continue;
        }

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

function ensureColumn($table, $column, $ddl): void
{
    if (!DB::hasTable($table)) {
        return;
    }

    if (DB::hasColumn($table, $column)) {
        return;
    }

    DB::pdo()->exec($ddl);
}

function usersCount(): int
{
    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');

    return $row ? (int) $row['cnt'] : 0;
}

function seedAdminUser(): void
{
    $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash, role) VALUES (:login, :pass_hash, :role)');
    $stmt->execute([
        'login' => 'admin',
        'pass_hash' => password_hash('admin', PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
}
