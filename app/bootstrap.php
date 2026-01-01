<?php

$root = dirname(__DIR__);

require $root . '/app/core/DB.php';
require $root . '/app/domain/SectionRepo.php';
require $root . '/app/domain/ComponentRepo.php';
require $root . '/app/domain/InfoblockRepo.php';
require $root . '/app/domain/ObjectRepo.php';
require $root . '/app/render/Renderer.php';

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

DB::connect($varDir . '/app.sqlite');

$skipMigrations = [];
if (hasColumn('sections', 'slug')) {
    $skipMigrations[] = '002_section_slug.sql';
}

runMigrations(DB::pdo(), $root . '/migrations', $skipMigrations);

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

function hasColumn($table, $column): bool
{
    $stmt = DB::pdo()->prepare('SELECT name FROM pragma_table_info(:table) WHERE name = :column');
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (bool) $stmt->fetchColumn();
}
