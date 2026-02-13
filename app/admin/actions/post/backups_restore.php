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

$writeRestoreLog = static function (string $status, array $context = []) use ($rootDir): void {
    $logDir = $rootDir . '/var/logs';
    if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        return;
    }

    $record = [
        'ts' => date('c'),
        'status' => $status,
        'context' => $context,
    ];
    @file_put_contents(
        $logDir . '/backup-restore.log',
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
};

$normalizeArchivePath = static function (string $path): string {
    $path = trim($path);
    while (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    $path = trim($path, '/');
    if ($path === '.' || $path === '') {
        return '';
    }

    return $path;
};

$findTargetForPath = static function (string $path) use ($targets): ?string {
    foreach ($targets as $target) {
        if ($path === $target || str_starts_with($path, $target . '/')) {
            return $target;
        }
    }
    return null;
};

$isAllowedArchivePath = static function (string $path) use ($targets): bool {
    foreach ($targets as $target) {
        // exact target or nested content inside target
        if ($path === $target || str_starts_with($path, $target . '/')) {
            return true;
        }

        // allow parent directory entries emitted by tar (e.g. "templates", "public_html")
        if (str_starts_with($target, $path . '/')) {
            return true;
        }
    }

    return false;
};

$archiveListCmd = 'tar -tzf ' . escapeshellarg($realArchive);
$archiveRows = [];
if ($runTar($archiveListCmd, $archiveRows) !== 0 || $archiveRows === []) {
    $writeRestoreLog('preflight_failed', ['file' => basename($realArchive), 'reason' => 'tar_list_failed']);
    adminFlashSet('danger', 'Не удалось прочитать содержимое архива бэкапа');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$archiveTypeRows = [];
$typeScanCmd = 'tar -tvzf ' . escapeshellarg($realArchive);
if ($runTar($typeScanCmd, $archiveTypeRows) !== 0) {
    $writeRestoreLog('preflight_failed', ['file' => basename($realArchive), 'reason' => 'tar_type_scan_failed']);
    adminFlashSet('danger', 'Не удалось проверить типы записей архива');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

foreach ($archiveTypeRows as $typeRow) {
    $line = ltrim((string) $typeRow);
    if ($line === '') {
        continue;
    }

    $typeFlag = $line[0] ?? '-';
    if ($typeFlag === 'l' || $typeFlag === 'h') {
        adminFlashSet('danger', 'Архив содержит ссылки (symlink/hardlink), восстановление запрещено');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$archiveTargets = [];
$wrapperPrefix = null;
$wrappedDataEntries = 0;
$directDataEntries = 0;
foreach ($archiveRows as $row) {
    $entry = $normalizeArchivePath((string) $row);
    if ($entry === '') {
        continue;
    }

    if (str_starts_with($entry, '/') || str_contains($entry, '\\') || preg_match('#(^|/)\.\.(?:/|$)#', $entry)) {
        adminFlashSet('danger', 'Архив содержит небезопасные пути, восстановление запрещено');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    $resolvedEntry = $entry;
    $target = null;

    if ($isAllowedArchivePath($resolvedEntry)) {
        $directDataEntries++;
        $target = $findTargetForPath($resolvedEntry);
    } else {
        $slashPos = strpos($entry, '/');
        if ($slashPos !== false) {
            $candidateWrapper = substr($entry, 0, $slashPos);
            $strippedEntry = substr($entry, $slashPos + 1);
            if ($strippedEntry !== '' && $isAllowedArchivePath($strippedEntry)) {
                if ($wrapperPrefix === null) {
                    $wrapperPrefix = $candidateWrapper;
                } elseif ($wrapperPrefix !== $candidateWrapper) {
                    adminFlashSet('danger', 'Архив содержит несколько корневых каталогов бэкапа');
                    redirectTo(buildAdminUrl(['action' => 'backups']));
                }

                $wrappedDataEntries++;
                $resolvedEntry = $strippedEntry;
                $target = $findTargetForPath($resolvedEntry);
            } else {
                adminFlashSet('danger', 'Архив содержит пути вне разрешённого списка целей бэкапа');
                redirectTo(buildAdminUrl(['action' => 'backups']));
            }
        } else {
            // allow top-level directory declaration (may be wrapper root)
            continue;
        }
    }

    if ($target !== null) {
        $archiveTargets[$target] = true;
    }
}

if ($wrappedDataEntries > 0 && $directDataEntries > 0) {
    adminFlashSet('danger', 'Архив имеет смешанную структуру (часть путей во вложенной папке, часть в корне)');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

if ($archiveTargets === []) {
    adminFlashSet('danger', 'Архив не содержит допустимых данных для восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$archiveTargetList = array_keys($archiveTargets);
$missingTargets = array_values(array_diff($targets, $archiveTargetList));
if ($restorePolicy === 'strict' && $missingTargets !== []) {
    $writeRestoreLog('strict_rejected', ['file' => basename($realArchive), 'missing' => $missingTargets]);
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
    $writeRestoreLog('dry_run', [
        'file' => basename($realArchive),
        'policy' => $restorePolicy,
        'cleanup_targets' => $currentTargets,
        'restore_targets' => $archiveTargetList,
        'missing_targets' => $missingTargets,
    ]);
    adminFlashSet(
        'info',
        'Dry-run (' . $modeLabel . '): будет очищено [' . $cleanupText . '], восстановлено [' . $restoreText . '], отсутствуют в архиве [' . $missingText . '].'
    );
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$maintenanceLockPath = $rootDir . '/var/restore.maintenance.lock';
$setMaintenance = static function () use ($maintenanceLockPath, $file, $restorePolicy): void {
    $payload = [
        'started_at' => date('c'),
        'file' => $file,
        'policy' => $restorePolicy,
        'reason' => 'backup_restore',
    ];
    @file_put_contents(
        $maintenanceLockPath,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
};
$clearMaintenance = static function () use ($maintenanceLockPath): void {
    if (is_file($maintenanceLockPath)) {
        @unlink($maintenanceLockPath);
    }
};

$setMaintenance();


$backupTimestamp = date('Ymd-His');
$preRestoreArchive = $backupDir . '/pre-restore-safety-' . $backupTimestamp . '.tar.gz';
if ($currentTargets !== []) {
    $parts = array_map(static fn(string $item): string => escapeshellarg($item), $currentTargets);
    $snapshotCmd = 'tar -czf ' . escapeshellarg($preRestoreArchive) . ' -C ' . escapeshellarg($rootDir) . ' ' . implode(' ', $parts);
    if ($runTar($snapshotCmd) !== 0 || !is_file($preRestoreArchive)) {
        $clearMaintenance();
        adminFlashSet('danger', 'Не удалось создать аварийный снимок перед восстановлением');
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$stagingBase = $rootDir . '/var/tmp';
if (!is_dir($stagingBase) && !mkdir($stagingBase, 0775, true) && !is_dir($stagingBase)) {
    $clearMaintenance();
    adminFlashSet('danger', 'Не удалось подготовить временную директорию восстановления');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}
$stagingDir = $stagingBase . '/restore-' . $backupTimestamp . '-' . substr(sha1(uniqid('', true)), 0, 8);
if (!mkdir($stagingDir, 0775, true) && !is_dir($stagingDir)) {
    $clearMaintenance();
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
if ($wrappedDataEntries > 0 && $directDataEntries === 0) {
    $extractToStagingCmd .= ' --strip-components=1';
}
if ($runTar($extractToStagingCmd) !== 0) {
    $cleanupStaging();
    $clearMaintenance();
    adminFlashSet('danger', 'Не удалось распаковать архив в staging-область');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

foreach ($archiveTargetList as $target) {
    $stagingTarget = $stagingDir . '/' . $target;
    if (!file_exists($stagingTarget) && !is_link($stagingTarget)) {
        $cleanupStaging();
        $clearMaintenance();
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
        $clearMaintenance();
        adminFlashSet('danger', 'Не удалось подготовить путь для очистки: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!str_starts_with(rtrim($targetReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $realRootDir)) {
        $cleanupStaging();
        $restoreSnapshot();
        $clearMaintenance();
        adminFlashSet('danger', 'Запрещено очищать путь вне корня проекта: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }

    if (!$removePath($targetReal)) {
        $cleanupStaging();
        $restoreSnapshot();
        $clearMaintenance();
        adminFlashSet('danger', 'Не удалось очистить путь перед восстановлением: ' . $target);
        redirectTo(buildAdminUrl(['action' => 'backups']));
    }
}

$applyPathFromStaging = static function (string $sourcePath, string $targetPath) use (&$applyPathFromStaging): bool {
    if (is_link($sourcePath)) {
        $linkTarget = readlink($sourcePath);
        if ($linkTarget === false) {
            return false;
        }

        return symlink($linkTarget, $targetPath);
    }

    if (is_file($sourcePath)) {
        return @copy($sourcePath, $targetPath);
    }

    if (!is_dir($sourcePath)) {
        return false;
    }

    if (!is_dir($targetPath) && !@mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
        return false;
    }

    $items = scandir($sourcePath);
    if (!is_array($items)) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $childSrc = $sourcePath . DIRECTORY_SEPARATOR . $item;
        $childDst = $targetPath . DIRECTORY_SEPARATOR . $item;
        if (!$applyPathFromStaging($childSrc, $childDst)) {
            return false;
        }
    }

    return true;
};

$applyFailedTarget = null;
foreach ($archiveTargetList as $target) {
    $stagingTarget = $stagingDir . '/' . $target;
    $targetPath = $rootDir . '/' . $target;

    if (!file_exists($stagingTarget) && !is_link($stagingTarget)) {
        $applyFailedTarget = $target;
        break;
    }

    if (@rename($stagingTarget, $targetPath)) {
        continue;
    }

    if (!$applyPathFromStaging($stagingTarget, $targetPath)) {
        $applyFailedTarget = $target;
        break;
    }
}

if ($applyFailedTarget !== null) {
    $cleanupStaging();
    $restoreSnapshot();
    $writeRestoreLog('apply_failed_rolled_back', [
        'file' => basename($realArchive),
        'policy' => $restorePolicy,
        'target' => $applyFailedTarget,
    ]);
    $clearMaintenance();
    adminFlashSet('danger', 'Не удалось применить данные из staging в рабочую директорию (цель: ' . $applyFailedTarget . '). Выполнен откат.');
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$postCheckErrors = [];
foreach ($archiveTargetList as $target) {
    $targetPath = $rootDir . '/' . $target;
    if (!file_exists($targetPath) && !is_link($targetPath)) {
        $postCheckErrors[] = 'Отсутствует восстановленный путь: ' . $target;
        continue;
    }

    if ($target === 'var/app.sqlite' && (!is_file($targetPath) || filesize($targetPath) === 0)) {
        $postCheckErrors[] = 'SQLite после restore отсутствует или пустой';
    }

    if ($target !== 'var/app.sqlite' && !is_dir($targetPath)) {
        $postCheckErrors[] = 'Путь должен быть директорией: ' . $target;
    }
}

if ($postCheckErrors !== []) {
    $cleanupStaging();
    $restoreSnapshot();
    $writeRestoreLog('postcheck_failed', [
        'file' => basename($realArchive),
        'policy' => $restorePolicy,
        'errors' => $postCheckErrors,
    ]);
    $clearMaintenance();
    adminFlashSet('danger', 'Post-check не пройден, выполнен откат: ' . implode(' | ', $postCheckErrors));
    redirectTo(buildAdminUrl(['action' => 'backups']));
}

$cleanupStaging();

$writeRestoreLog('success', [
    'file' => basename($realArchive),
    'policy' => $restorePolicy,
    'restored_targets' => $archiveTargetList,
    'snapshot' => is_file($preRestoreArchive) ? basename($preRestoreArchive) : null,
]);

$restoredTargets = implode(', ', $archiveTargetList);
$safetyTail = is_file($preRestoreArchive)
    ? ' Аварийный снимок: ' . basename($preRestoreArchive)
    : '';
$policyTail = ' Режим: ' . $restorePolicy . '.';
$clearMaintenance();
adminFlashSet('success', 'Бэкап восстановлен после очистки. Восстановленные цели: ' . $restoredTargets . '.' . $policyTail . $safetyTail);
redirectTo(buildAdminUrl(['action' => 'backups']));
