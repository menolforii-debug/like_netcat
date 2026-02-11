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

$command = 'tar -xzf ' . escapeshellarg($realArchive) . ' -C ' . escapeshellarg($rootDir);
exec($command, $output, $exitCode);
if ($exitCode !== 0) {
    adminFlashSet('danger', 'Не удалось восстановить бэкап');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Бэкап восстановлен: ' . basename($realArchive));
redirectTo(buildAdminUrl(['action' => 'backups']));
