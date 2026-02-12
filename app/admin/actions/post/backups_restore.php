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

$runMode = isset($_POST['run_mode']) ? (string) $_POST['run_mode'] : 'restore';
if ($runMode !== 'preview' && $runMode !== 'restore') {
    adminFlashSet('danger', 'Некорректный режим восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$restorePolicy = isset($_POST['restore_policy']) ? (string) $_POST['restore_policy'] : 'strict';
if ($restorePolicy !== 'strict' && $restorePolicy !== 'lenient') {
    adminFlashSet('danger', 'Некорректная политика восстановления');
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

$runTar = static function (string $command, ?array &$output = null): int {
    $lines = [];
    exec($command . ' 2>&1', $lines, $code);
    $output = $lines;
    return (int) $code;
};

$normalizeArchivePath = static function (string $path): string {
    $path = trim($path);
    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }
    return trim($path, '/');
};

$findTargetForPath = static function (string $path) use ($targets): ?string {
    foreach ($targets as $target) {
        if ($path === $target || str_starts_with($path, $target . '/')) {
            return $target;
        }
    }
    return null;
};

$archiveListCmd = 'tar -tvzf ' . escapeshellarg($realArchive);
$archiveRows = [];
if ($runTar($archiveListCmd, $archiveRows) !== 0 || $archiveRows === []) {
    adminFlashSet('danger', 'Не удалось прочитать содержимое архива бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$archiveTargets = [];
foreach ($archiveRows as $row) {
    $line = trim((string) $row);
    if ($line === '') {
        continue;
    }

    $typeFlag = $line[0] ?? '-';
    if ($typeFlag === 'l' || $typeFlag === 'h') {
        adminFlashSet('danger', 'Архив содержит ссылки (symlink/hardlink), восстановление запрещено');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!preg_match('/\s(.+)$/', $line, $m)) {
        adminFlashSet('danger', 'Не удалось разобрать содержимое архива бэкапа');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    $entryRaw = (string) $m[1];
    $entryRaw = preg_replace('/\s+->\s+.*/', '', $entryRaw) ?? $entryRaw;
    $entry = $normalizeArchivePath($entryRaw);
    if ($entry === '') {
        continue;
    }

    if (str_starts_with($entry, '/') || str_contains($entry, '\\') || preg_match('#(^|/)\.\.(?:/|$)#', $entry)) {
        adminFlashSet('danger', 'Архив содержит небезопасные пути, восстановление запрещено');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    $target = $findTargetForPath($entry);
    if ($target === null) {
        adminFlashSet('danger', 'Архив содержит пути вне разрешённого списка целей бэкапа');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    $archiveTargets[$target] = true;
}

if ($archiveTargets === []) {
    adminFlashSet('danger', 'Архив не содержит допустимых данных для восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$archiveTargetList = array_keys($archiveTargets);
$missingTargets = array_values(array_diff($targets, $archiveTargetList));
if ($restorePolicy === 'strict' && $missingTargets !== []) {
    adminFlashSet('danger', 'Strict-режим: архив неполный. Отсутствуют цели: ' . implode(', ', $missingTargets));
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$targetsToCleanup = $restorePolicy === 'lenient' ? $archiveTargetList : $targets;
$currentTargets = [];
foreach ($targetsToCleanup as $target) {
    $targetPath = $rootDir . '/' . $target;
    if (file_exists($targetPath) || is_link($targetPath)) {
        $currentTargets[] = $target;
    }
}

if ($runMode === 'preview') {
    $modeLabel = $restorePolicy === 'strict' ? 'strict' : 'lenient';
    $missingText = $missingTargets === [] ? 'нет' : implode(', ', $missingTargets);
    $cleanupText = $currentTargets === [] ? 'ничего (пути отсутствуют)' : implode(', ', $currentTargets);
    $restoreText = implode(', ', $archiveTargetList);
    adminFlashSet(
        'info',
        'Dry-run (' . $modeLabel . '): будет очищено [' . $cleanupText . '], восстановлено [' . $restoreText . '], отсутствуют в архиве [' . $missingText . '].'
    );
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$backupTimestamp = date('Ymd-His');
$preRestoreArchive = $backupDir . '/pre-restore-safety-' . $backupTimestamp . '.tar.gz';
if ($currentTargets !== []) {
    $parts = array_map(static fn(string $item): string => escapeshellarg($item), $currentTargets);
    $snapshotCmd = 'tar -czf ' . escapeshellarg($preRestoreArchive) . ' -C ' . escapeshellarg($rootDir) . ' ' . implode(' ', $parts);
    if ($runTar($snapshotCmd) !== 0 || !is_file($preRestoreArchive)) {
        adminFlashSet('danger', 'Не удалось создать аварийный снимок перед восстановлением');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$stagingBase = $rootDir . '/var/tmp';
if (!is_dir($stagingBase) && !mkdir($stagingBase, 0775, true) && !is_dir($stagingBase)) {
    adminFlashSet('danger', 'Не удалось подготовить временную директорию восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}
$stagingDir = $stagingBase . '/restore-' . $backupTimestamp . '-' . substr(sha1(uniqid('', true)), 0, 8);
if (!mkdir($stagingDir, 0775, true) && !is_dir($stagingDir)) {
    adminFlashSet('danger', 'Не удалось создать staging-директорию восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

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

$cleanupStaging = static function () use (&$removePath, $stagingDir): void {
    if (is_dir($stagingDir) || is_link($stagingDir)) {
        $removePath($stagingDir);
    }
};

$restoreSnapshot = static function () use (
    $runTar,
    $rootDir,
    $preRestoreArchive,
    $targetsToCleanup,
    &$removePath,
    $realRootDir
): void {
    foreach ($targetsToCleanup as $target) {
        $targetPath = $rootDir . '/' . $target;
        if (!file_exists($targetPath) && !is_link($targetPath)) {
            continue;
        }
        $targetReal = realpath($targetPath);
        if ($targetReal === false) {
            continue;
        }
        if (!str_starts_with(rtrim($targetReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $realRootDir)) {
            continue;
        }
        $removePath($targetReal);
    }

    if (is_file($preRestoreArchive)) {
        $recoverCmd = 'tar -xzf ' . escapeshellarg($preRestoreArchive) . ' -C ' . escapeshellarg($rootDir);
        $runTar($recoverCmd);
    }
};

$extractToStagingCmd = 'tar -xzf ' . escapeshellarg($realArchive) . ' -C ' . escapeshellarg($stagingDir);
if ($runTar($extractToStagingCmd) !== 0) {
    $cleanupStaging();
    adminFlashSet('danger', 'Не удалось распаковать архив в staging-область');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

foreach ($archiveTargetList as $target) {
    $stagingTarget = $stagingDir . '/' . $target;
    if (!file_exists($stagingTarget) && !is_link($stagingTarget)) {
        $cleanupStaging();
        adminFlashSet('danger', 'Проверка staging не пройдена: отсутствует путь ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

foreach ($targetsToCleanup as $target) {
    $targetPath = $rootDir . '/' . $target;
    if (!file_exists($targetPath) && !is_link($targetPath)) {
        continue;
    }

    $targetReal = realpath($targetPath);
    if ($targetReal === false) {
        $cleanupStaging();
        $restoreSnapshot();
        adminFlashSet('danger', 'Не удалось подготовить путь для очистки: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!str_starts_with(rtrim($targetReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $realRootDir)) {
        $cleanupStaging();
        $restoreSnapshot();
        adminFlashSet('danger', 'Запрещено очищать путь вне корня проекта: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!$removePath($targetReal)) {
        $cleanupStaging();
        $restoreSnapshot();
        adminFlashSet('danger', 'Не удалось очистить путь перед восстановлением: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$copyFromStagingCmd = 'tar -czf - -C ' . escapeshellarg($stagingDir) . ' . | tar -xzf - -C ' . escapeshellarg($rootDir);
if ($runTar($copyFromStagingCmd) !== 0) {
    $cleanupStaging();
    $restoreSnapshot();
    adminFlashSet('danger', 'Не удалось применить данные из staging в рабочую директорию. Выполнен откат.');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$cleanupStaging();

$restoredTargets = implode(', ', $archiveTargetList);
$safetyTail = is_file($preRestoreArchive)
    ? ' Аварийный снимок: ' . basename($preRestoreArchive)
    : '';
$policyTail = ' Режим: ' . $restorePolicy . '.';
adminFlashSet('success', 'Бэкап восстановлен после очистки. Восстановленные цели: ' . $restoredTargets . '.' . $policyTail . $safetyTail);
redirectTo(buildAdminUrl(['action' => 'backups']));
