<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$rootDir = dirname(__DIR__, 4);
$backupDir = $rootDir . '/var/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

$backupFiles = [];
if (is_dir($backupDir)) {
    $items = scandir($backupDir);
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!preg_match('/\.tar\.gz$/', $item)) {
                continue;
            }
            $path = $backupDir . '/' . $item;
            if (!is_file($path)) {
                continue;
            }
            $mtime = filemtime($path);
            if ($mtime === false) {
                $mtime = 0;
            }
            $size = filesize($path);
            if ($size === false) {
                $size = 0;
            }
            $backupFiles[] = [
                'name' => $item,
                'mtime' => (int) $mtime,
                'size' => (int) $size,
            ];
        }
    }
}

usort($backupFiles, static function (array $a, array $b): int {
    return $b['mtime'] <=> $a['mtime'];
});


$maintenanceLockPath = $rootDir . '/var/restore.maintenance.lock';
$maintenanceLockInfo = null;
if (is_file($maintenanceLockPath) && is_readable($maintenanceLockPath)) {
    $raw = @file_get_contents($maintenanceLockPath);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $maintenanceLockInfo = $decoded;
        }
    }
}

$restoreLogEntries = [];
$restoreLogPath = $rootDir . '/var/logs/backup-restore.log';
if (is_file($restoreLogPath) && is_readable($restoreLogPath)) {
    $lines = @file($restoreLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $lines = array_slice($lines, -30);
        $lines = array_reverse($lines);
        foreach ($lines as $line) {
            $decoded = json_decode((string) $line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $ts = isset($decoded['ts']) ? (string) $decoded['ts'] : '';
            $status = isset($decoded['status']) ? (string) $decoded['status'] : '';
            $context = isset($decoded['context']) && is_array($decoded['context']) ? $decoded['context'] : [];

            if ($status === '') {
                continue;
            }

            $fileName = isset($context['file']) ? (string) $context['file'] : '';
            $policy = isset($context['policy']) ? (string) $context['policy'] : '';

            $details = [];
            if ($fileName !== '') {
                $details[] = 'file=' . $fileName;
            }
            if ($policy !== '') {
                $details[] = 'policy=' . $policy;
            }
            if (isset($context['missing']) && is_array($context['missing'])) {
                $details[] = 'missing=' . implode(', ', array_map('strval', $context['missing']));
            }
            if (isset($context['errors']) && is_array($context['errors'])) {
                $details[] = 'errors=' . implode(' | ', array_map('strval', $context['errors']));
            }

            $restoreLogEntries[] = [
                'ts' => $ts,
                'status' => $status,
                'details' => implode('; ', $details),
            ];
        }
    }
}

AdminLayout::renderHeader('Бэкапы');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Бэкапы</h1>';
echo '</div>';


if (is_file($maintenanceLockPath)) {
    echo '<div class="card shadow-sm mb-4 border-warning">';
    echo '<div class="card-body">';
    echo '<h2 class="h6 mb-2 text-warning">Maintenance lock активен</h2>';

    $startedAt = '';
    $lockFile = '';
    $lockPolicy = '';
    if (is_array($maintenanceLockInfo)) {
        $startedAt = isset($maintenanceLockInfo['started_at']) ? (string) $maintenanceLockInfo['started_at'] : '';
        $lockFile = isset($maintenanceLockInfo['file']) ? (string) $maintenanceLockInfo['file'] : '';
        $lockPolicy = isset($maintenanceLockInfo['policy']) ? (string) $maintenanceLockInfo['policy'] : '';
    }

    $parts = [];
    if ($startedAt !== '') {
        $parts[] = 'started_at=' . $startedAt;
    }
    if ($lockFile !== '') {
        $parts[] = 'file=' . $lockFile;
    }
    if ($lockPolicy !== '') {
        $parts[] = 'policy=' . $lockPolicy;
    }
    $details = $parts === [] ? 'нет деталей lock-файла' : implode('; ', $parts);

    echo '<div class="small text-muted mb-3">' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<form method="post" action="/admin.php?action=backups_unlock" data-confirm="Снять maintenance lock вручную? Делайте это только если восстановление точно завершено.">';
    echo csrf_token_field();
    echo '<button class="btn btn-sm btn-outline-warning" type="submit">Снять lock вручную</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Создать бэкап</h2>';
echo '<form method="post" action="/admin.php?action=backups_create">';
echo csrf_token_field();
echo '<button class="btn btn-primary" type="submit">Создать новый бэкап</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Загрузить архив бэкапа</h2>';
echo '<form method="post" action="/admin.php?action=backups_upload" enctype="multipart/form-data">';
echo csrf_token_field();
echo '<div class="mb-3">';
echo '<input class="form-control" type="file" name="backup_file" accept=".tar.gz" required>';
echo '</div>';
echo '<button class="btn btn-outline-primary" type="submit">Загрузить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Журнал восстановлений (последние 30 событий)</h2>';
if ($restoreLogEntries === []) {
    echo '<div class="alert alert-light border mb-0">События восстановлений не найдены.</div>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm align-middle mb-0">';
    echo '<thead><tr><th>Время (UTC)</th><th>Статус</th><th>Детали</th></tr></thead><tbody>';
    foreach ($restoreLogEntries as $entry) {
        $ts = htmlspecialchars((string) $entry['ts'], ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) $entry['status'], ENT_QUOTES, 'UTF-8');
        $details = htmlspecialchars((string) $entry['details'], ENT_QUOTES, 'UTF-8');

        $statusClass = 'secondary';
        if ((string) $entry['status'] === 'success') {
            $statusClass = 'success';
        } elseif (str_contains((string) $entry['status'], 'failed')) {
            $statusClass = 'danger';
        } elseif ((string) $entry['status'] === 'dry_run' || (string) $entry['status'] === 'strict_rejected') {
            $statusClass = 'warning';
        }

        echo '<tr>';
        echo '<td class="font-monospace">' . $ts . '</td>';
        echo '<td><span class="badge text-bg-' . $statusClass . '">' . $status . '</span></td>';
        echo '<td>' . ($details === '' ? '-' : $details) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}
echo '</div>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Список бэкапов</h2>';

if ($backupFiles === []) {
    echo '<div class="alert alert-light border mb-0">Бэкапы не найдены.</div>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm align-middle mb-0">';
    echo '<thead><tr><th>Файл</th><th>Дата</th><th>Размер</th><th class="text-end">Действия</th></tr></thead><tbody>';

    foreach ($backupFiles as $file) {
        $name = (string) $file['name'];
        $mtime = (int) $file['mtime'];
        $size = (int) $file['size'];
        $date = $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '-';
        $sizeKb = number_format($size / 1024, 2, '.', ' ');

        echo '<tr>';
        echo '<td class="font-monospace">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($sizeKb . ' KB', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td class="text-end">';
        echo '<a class="btn btn-sm btn-outline-secondary me-2" href="' . htmlspecialchars(buildAdminUrl(['action' => 'backups_download', 'file' => $name]), ENT_QUOTES, 'UTF-8') . '">Скачать</a>';
        echo '<form class="d-inline-flex align-items-center gap-2 me-2" method="post" action="/admin.php?action=backups_restore" data-confirm="Выполнить операцию с этим бэкапом? Для восстановления текущие данные будут перезаписаны.">';
        echo csrf_token_field();
        echo '<input type="hidden" name="file" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
        echo '<select class="form-select form-select-sm" name="run_mode" title="Режим операции">';
        echo '<option value="restore">Восстановить</option>';
        echo '<option value="preview">Только проверка (dry-run)</option>';
        echo '</select>';
        echo '<select class="form-select form-select-sm" name="restore_policy" title="Политика восстановления">';
        echo '<option value="strict">Строгая (требовать полный архив)</option>';
        echo '<option value="lenient">Мягкая (восстанавливать только найденные цели)</option>';
        echo '</select>';
        echo '<button class="btn btn-sm btn-danger" type="submit">Запустить</button>';
        echo '</form>';
        echo '<form class="d-inline" method="post" action="/admin.php?action=backups_delete" data-confirm="Удалить этот файл бэкапа без возможности восстановления?">';
        echo csrf_token_field();
        echo '<input type="hidden" name="file" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
