<?php

if (!Auth::isAdmin()) {
    adminFlashSet('error', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'snippet_list']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';

if ($keyword === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    adminFlashSet('error', 'Некорректный ключ врезки');
    redirectTo(buildAdminUrl(['action' => 'snippet_list']));
}

$root = dirname(__DIR__, 4);
$snippetsDir = $root . '/templates/snippets';
if (!is_dir($snippetsDir)) {
    mkdir($snippetsDir, 0775, true);
}

$baseReal = realpath($snippetsDir);
if ($baseReal === false) {
    adminFlashSet('error', 'Не удалось создать папку для врезок');
    redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
}
$baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$snippetPath = $baseReal . $keyword . '.php';

$snippetDirReal = realpath(dirname($snippetPath));
if ($snippetDirReal === false || strpos($snippetDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
    adminFlashSet('error', 'Некорректный путь к файлу врезки');
    redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
}

$ok = @file_put_contents($snippetPath, $content, LOCK_EX);
if ($ok === false) {
    $last = error_get_last();
    $msg = is_array($last) && isset($last['message']) ? $last['message'] : 'unknown';
    adminFlashSet('error', 'Не удалось записать файл врезки: ' . $msg);
    redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
}

if (!DB::hasTable('snippet')) {
    DB::pdo()->exec('CREATE TABLE IF NOT EXISTS snippet (keyword TEXT PRIMARY KEY, name TEXT NOT NULL DEFAULT \'\')');
}

$stmt = DB::pdo()->prepare('INSERT INTO snippet (keyword, name) VALUES (:keyword, :name) ON CONFLICT(keyword) DO UPDATE SET name = excluded.name');
$stmt->execute([
    'keyword' => $keyword,
    'name' => $name,
]);

adminFlashSet('success', 'Врезка сохранена.');
redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
