<?php

$root = dirname(__DIR__, 2);
date_default_timezone_set('UTC');

require $root . '/app/shared/core/DB.php';
require $root . '/app/shared/core/EventBus.php';
require $root . '/app/shared/nav/Nav.php';
require $root . '/app/shared/core/Core.php';
require $root . '/app/shared/core/Utils.php';
require $root . '/app/shared/core/Functions.php';
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

function core(): Core
{
    return $GLOBALS['core'];
}

function users_count(): int
{
    $row = DB::fetchOne('SELECT COUNT(*) AS cnt FROM users');

    return $row ? (int) $row['cnt'] : 0;
}
