<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$rootDir = dirname(__DIR__, 4);
$backupDir = $rootDir . '/var/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    adminFlashSet('danger', 'Не удалось создать директорию бэкапов');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$archiveName = 'cms-backup-' . date('Ymd-His') . '.tar.gz';
$archivePath = $backupDir . '/' . $archiveName;

$targets = [
    'var/app.sqlite',
    'templates/layouts',
    'templates/component',
    'templates/snippets',
    'public_html/files',
];
$existingTargets = [];
foreach ($targets as $target) {
    if (file_exists($rootDir . '/' . $target)) {
        $existingTargets[] = $target;
    }
}

if ($existingTargets === []) {
    adminFlashSet('danger', 'Нет данных для создания бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$parts = array_map(static fn(string $item): string => escapeshellarg($item), $existingTargets);
$command = 'tar -czf ' . escapeshellarg($archivePath) . ' -C ' . escapeshellarg($rootDir) . ' ' . implode(' ', $parts);
exec($command, $output, $exitCode);

if ($exitCode !== 0 || !is_file($archivePath)) {
    adminFlashSet('danger', 'Не удалось создать архив бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Бэкап создан: ' . $archiveName);
redirectTo(buildAdminUrl(['action' => 'backups']));
