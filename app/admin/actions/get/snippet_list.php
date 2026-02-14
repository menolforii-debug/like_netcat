<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$root = dirname(__DIR__, 4);
$snippetsDir = $root . '/templates/snippets';
$files = [];
if (is_dir($snippetsDir)) {
    $files = glob($snippetsDir . '/*.php') ?: [];
}
$snippets = [];
foreach ($files as $file) {
    $name = basename($file, '.php');
    if ($name !== '') {
        $snippets[] = $name;
    }
}
$snippets = array_values(array_unique($snippets));
$snippetsCount = count($snippets);
if ($snippetsCount > 1) {
    sort($snippets, SORT_NATURAL | SORT_FLAG_CASE);
}

$keyword = isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '';
$isNew = isset($_GET['new']) && (string) $_GET['new'] === '1';
$error = '';
$content = '';
$snippetExists = false;
$snippetName = '';
$snippetNames = [];

if ($keyword !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    $error = 'Ключ должен содержать только латиницу, цифры, дефис и подчёркивание.';
    $keyword = '';
}

if ($keyword === '' && !$isNew && $snippets !== []) {
    $keyword = $snippets[0];
}

if ($keyword !== '') {
    if (is_dir($snippetsDir)) {
        $baseReal = realpath($snippetsDir);
        if ($baseReal !== false) {
            $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $snippetPath = $baseReal . $keyword . '.php';
            if (is_file($snippetPath)) {
                $realSnippetPath = realpath($snippetPath);
                if ($realSnippetPath !== false && strpos($realSnippetPath, $baseReal) === 0) {
                    $content = file_get_contents($realSnippetPath) ?: '';
                    $snippetExists = true;
                } else {
                    $error = 'Некорректный путь к файлу врезки.';
                }
            }
        }
    }
}

if (DB::hasTable('snippet')) {
    $rows = DB::fetchAll('SELECT keyword, name FROM snippet');
    foreach ($rows as $row) {
        $rowKey = isset($row['keyword']) ? trim((string) $row['keyword']) : '';
        if ($rowKey === '') {
            continue;
        }
        $snippetNames[$rowKey] = isset($row['name']) ? trim((string) $row['name']) : '';
    }
}

if ($keyword !== '') {
    $snippetName = $snippetNames[$keyword] ?? '';
}

AdminLayout::renderHeader('Врезки');

AdminLayout::openSidebar();
echo '<div id="left-sidebar">';
SnippetListView::renderSidebar($snippets, $snippetNames, $keyword);
echo '</div>';
AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div id="content">';
SnippetListView::renderContent($snippets, $keyword, $snippetExists, $snippetName, $content, $error, $isNew);
echo '</div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
