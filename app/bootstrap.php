<?php

$root = dirname(__DIR__);

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
require $root . '/app/core/Core.php';
require $root . '/app/core/Auth.php';
require $root . '/app/domain/SectionRepo.php';
require $root . '/app/domain/ComponentRepo.php';
require $root . '/app/domain/InfoblockRepo.php';
require $root . '/app/domain/ObjectRepo.php';
require $root . '/app/render/Renderer.php';

Auth::start();

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

DB::connect($varDir . '/app.sqlite');

$skipMigrations = [];
if (hasColumn('sections', 'slug')) {
    $skipMigrations[] = '002_section_slug.sql';
}
if (hasColumn('objects', 'deleted_at')) {
    $skipMigrations[] = '021_objects_deleted_at.sql';
}

runMigrations(DB::pdo(), $root . '/migrations', $skipMigrations);

$core = new Core(DB::pdo(), new EventBus());
$GLOBALS['core'] = $core;

if (hasTable('users') && usersCount() === 0) {
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

function hasTable($table): bool
{
    $stmt = DB::pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
    $stmt->execute(['name' => $table]);

    return (bool) $stmt->fetchColumn();
}

function hasColumn($table, $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    $stmt = DB::pdo()->query('PRAGMA table_info(' . $table . ')');
    if ($stmt === false) {
        return false;
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($row['name']) && $row['name'] === $column) {
            return true;
        }
    }

    return false;
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
