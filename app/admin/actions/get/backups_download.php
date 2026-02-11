<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$file = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+\.tar\.gz$/', $file)) {
    adminFlashSet('danger', 'Некорректное имя файла бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$rootDir = dirname(__DIR__, 4);
$backupDir = $rootDir . '/var/backups';
$filePath = $backupDir . '/' . $file;

$realBackupDir = realpath($backupDir);
$realFile = realpath($filePath);
if ($realBackupDir === false || $realFile === false) {
    adminFlashSet('danger', 'Файл бэкапа не найден');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$realBackupDir = rtrim($realBackupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($realFile, $realBackupDir) || !is_file($realFile)) {
    adminFlashSet('danger', 'Файл бэкапа недоступен');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
header('Content-Length: ' . (string) filesize($realFile));
readfile($realFile);
exit;
