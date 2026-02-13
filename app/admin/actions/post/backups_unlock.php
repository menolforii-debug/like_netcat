<?php

if (!Auth::isAdmin()) {
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$rootDir = dirname(__DIR__, 4);
$lockPath = $rootDir . '/var/restore.maintenance.lock';
if (!is_file($lockPath)) {
    adminFlashSet('info', 'Maintenance lock не найден');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

if (!@unlink($lockPath)) {
    adminFlashSet('danger', 'Не удалось снять maintenance lock');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

adminFlashSet('success', 'Maintenance lock снят вручную');
redirectTo(buildAdminUrl(['action' => 'backups']));
