<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$file = isset($_POST['file']) ? trim((string) $_POST['file']) : '';
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.tar\.gz$/', $file)) {
    adminFlashSet('danger', 'Некорректное имя файла бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$rootDir = dirname(__DIR__, 4);
$backupDir = $rootDir . '/var/backups';
$archivePath = $backupDir . '/' . $file;

$realBackupDir = realpath($backupDir);
$realArchive = realpath($archivePath);
if ($realBackupDir === false || $realArchive === false || !is_file($realArchive)) {
    adminFlashSet('danger', 'Файл бэкапа не найден');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$realBackupDir = rtrim($realBackupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($realArchive, $realBackupDir)) {
    adminFlashSet('danger', 'Файл бэкапа недоступен');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$realRootDir = realpath($rootDir);
if ($realRootDir === false) {
    adminFlashSet('danger', 'Не удалось определить корень проекта');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}
$realRootDir = rtrim($realRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$targets = [
    'var/app.sqlite',
    'templates/layouts',
    'templates/component',
    'templates/snippets',
    'public_html/files',
];

$removePath = static function (string $path) use (&$removePath): bool {
    if (is_link($path) || is_file($path)) {
        return @unlink($path);
    }
    if (!is_dir($path)) {
        return true;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $childPath = $path . DIRECTORY_SEPARATOR . $item;
        if (!$removePath($childPath)) {
            return false;
        }
    }

    return @rmdir($path);
};

foreach ($targets as $target) {
    $targetPath = $rootDir . '/' . $target;
    if (!file_exists($targetPath) && !is_link($targetPath)) {
        continue;
    }

    $targetReal = realpath($targetPath);
    if ($targetReal === false) {
        adminFlashSet('danger', 'Не удалось подготовить путь для очистки: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!str_starts_with(rtrim($targetReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $realRootDir)) {
        adminFlashSet('danger', 'Запрещено очищать путь вне корня проекта: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!$removePath($targetReal)) {
        adminFlashSet('danger', 'Не удалось очистить путь перед восстановлением: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$command = 'tar -xzf ' . escapeshellarg($realArchive) . ' -C ' . escapeshellarg($rootDir);
exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    adminFlashSet('danger', 'Не удалось восстановить бэкап');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Бэкап восстановлен с полной очисткой целевых путей: ' . basename($realArchive));
redirectTo(buildAdminUrl(['action' => 'backups']));
