<?php

$root = dirname(__DIR__);
date_default_timezone_set('UTC');

require $root . '/app/core/DB.php';
require $root . '/app/core/EventBus.php';
require $root . '/app/core/Core.php';
require $root . '/app/core/Utils.php';
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
        $defaultLayout = <<<'PHP'
<?php
/** @var array $ctx */
/** @var callable $body */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php Layout::renderCss(); ?>
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (!empty($meta['description'])): ?>
        <meta name="description" content="<?= htmlspecialchars((string) $meta['description'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty($meta['keywords'])): ?>
        <meta name="keywords" content="<?= htmlspecialchars((string) $meta['keywords'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body class="bg-light">
<div class="page-wrapper d-flex flex-column min-vh-100">
    <div class="content-wrapper flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="/"><?= htmlspecialchars((string) ($site['title'] ?? 'CMS'), ENT_QUOTES, 'UTF-8') ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/admin.php">Админ</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container py-4">
            <?php $body(); ?>
        </main>
    </div>
</div>
<?php Layout::renderJs(); ?>
</body>
</html>
PHP;
        file_put_contents($defaultLayoutPath, $defaultLayout);
        @chmod($defaultLayoutPath, 0660);
    }

    $defaultNavPath = $templatesDir . '/default.nav.php';
    if (!is_file($defaultNavPath)) {
        $defaultNav = <<<'PHP'
<?php
// Здесь можно описать функции построения меню или другие helper-функции для макета.
PHP;
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
