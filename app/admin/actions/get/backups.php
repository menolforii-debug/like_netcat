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

AdminLayout::renderHeader('Бэкапы');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Бэкапы</h1>';
echo '</div>';

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
        echo '<form class="d-inline-flex align-items-center gap-2" method="post" action="/admin.php?action=backups_restore" data-confirm="Восстановить из этого бэкапа? Текущие данные будут перезаписаны.">';
        echo csrf_token_field();
        echo '<input type="hidden" name="file" value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
        echo '<select class="form-select form-select-sm" name="restore_policy" title="Политика восстановления">';
        echo '<option value="strict">strict</option>';
        echo '<option value="lenient">lenient</option>';
        echo '</select>';
        echo '<button class="btn btn-sm btn-outline-primary" type="submit" name="run_mode" value="preview">Dry-run</button>';
        echo '<button class="btn btn-sm btn-danger" type="submit" name="run_mode" value="restore">Восстановить</button>';
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
