<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
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

AdminLayout::renderHeader('Врезки');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h5 mb-0">Врезки</h1>';
echo '<a class="btn btn-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'snippet_form']), ENT_QUOTES, 'UTF-8') . '">Создать</a>';
echo '</div>';

if (empty($snippets)) {
    echo '<div class="text-muted">Врезки пока не созданы.</div>';
} else {
    echo '<div class="list-group">';
    foreach ($snippets as $snippet) {
        $link = buildAdminUrl(['action' => 'snippet_form', 'keyword' => $snippet]);
        echo '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo '<span>' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="text-muted small">templates/snippets/' . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '.php</span>';
        echo '</a>';
    }
    echo '</div>';
}

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
