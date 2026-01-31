<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';

if ($keyword === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    redirectTo(buildAdminUrl(['action' => 'snippet_form', 'error' => 'Некорректный ключ врезки']));
}

$root = dirname(__DIR__, 4);
$snippetsDir = $root . '/templates/snippets';
if (!is_dir($snippetsDir)) {
    mkdir($snippetsDir, 0775, true);
}

$baseReal = realpath($snippetsDir);
if ($baseReal === false) {
    redirectTo(buildAdminUrl(['action' => 'snippet_form', 'keyword' => $keyword, 'error' => 'Не удалось создать папку для врезок']));
}
$baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$snippetPath = $baseReal . $keyword . '.php';

$snippetDirReal = realpath(dirname($snippetPath));
if ($snippetDirReal === false || strpos($snippetDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
    redirectTo(buildAdminUrl(['action' => 'snippet_form', 'keyword' => $keyword, 'error' => 'Некорректный путь к файлу врезки']));
}

file_put_contents($snippetPath, $content, LOCK_EX);

redirectTo(buildAdminUrl(['action' => 'snippet_form', 'keyword' => $keyword, 'saved' => 1]));
