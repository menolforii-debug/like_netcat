<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file'])) {
    adminFlashSet('danger', 'Файл не загружен');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$file = $_FILES['backup_file'];
$errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
    adminFlashSet('danger', 'Ошибка загрузки файла');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$originalName = isset($file['name']) ? (string) $file['name'] : '';
if ($originalName === '' || !preg_match('/^[A-Za-z0-9._-]+\.tar\.gz$/', $originalName)) {
    adminFlashSet('danger', 'Разрешены только файлы .tar.gz с безопасным именем');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    adminFlashSet('danger', 'Некорректный временный файл загрузки');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$rootDir = dirname(__DIR__, 4);
$backupDir = $rootDir . '/var/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    adminFlashSet('danger', 'Не удалось подготовить директорию бэкапов');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$targetPath = $backupDir . '/' . $originalName;
if (!move_uploaded_file($tmpName, $targetPath)) {
    adminFlashSet('danger', 'Не удалось сохранить загруженный бэкап');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Бэкап загружен: ' . $originalName);
redirectTo(buildAdminUrl(['action' => 'backups']));
