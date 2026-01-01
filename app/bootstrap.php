<?php
declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$varDir = $root . '/var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0777, true);
}

$dbPath = $varDir . '/app.sqlite';
Database::connect($dbPath);
MigrationRunner::run(Database::connection(), $root . '/migrations');
