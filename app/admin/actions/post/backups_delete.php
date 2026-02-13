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
$filePath = $backupDir . '/' . $file;

$realBackupDir = realpath($backupDir);
$realFile = realpath($filePath);
if ($realBackupDir === false || $realFile === false || !is_file($realFile)) {
    adminFlashSet('danger', 'Файл бэкапа не найден');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$realBackupDir = rtrim($realBackupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (!str_starts_with($realFile, $realBackupDir)) {
    adminFlashSet('danger', 'Файл бэкапа недоступен для удаления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

if (!@unlink($realFile)) {
    adminFlashSet('danger', 'Не удалось удалить файл бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Бэкап удалён: ' . basename($realFile));
redirectTo(buildAdminUrl(['action' => 'backups']));
