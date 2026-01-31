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

$keyword = isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '';
$isNew = isset($_GET['new']) && (string) $_GET['new'] === '1';
$saved = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
$errorMessage = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$error = '';
$content = '';
$snippetExists = false;

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

function renderTextareaValue($value): string
{
    $s = (string) $value;
    $s = preg_replace('~</textarea~i', '&lt;/textarea', $s);
    return $s ?? '';
}

AdminLayout::renderHeader('Врезки');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h5 mb-0">Врезки</h1>';
echo '</div>';

echo '<div class="row g-4">';
echo '<div class="col-12 col-lg-4">';

$createLink = buildAdminUrl(['action' => 'snippet_list', 'new' => 1]);
echo '<div class="d-flex align-items-center justify-content-between mb-2">';
echo '<div class="fw-semibold">Врезки</div>';
echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars($createLink, ENT_QUOTES, 'UTF-8') . '" title="Добавить врезку" aria-label="Добавить врезку">+</a>';
echo '</div>';

if (empty($snippets)) {
    echo '<div class="text-muted">Врезки пока не созданы.</div>';
} else {
    echo '<nav class="nav-deep nav-deep-sm nav-deep-light component-tree">';
    echo '<ul class="nav flex-column component-tree-root">';
    foreach ($snippets as $snippet) {
        $link = buildAdminUrl(['action' => 'snippet_list', 'keyword' => $snippet]);
        $liClass = 'nav-item component-tree-item';
        if ($keyword === $snippet) {
            $liClass .= ' is-active is-open';
        }
        echo '<li class="' . $liClass . '">';
        echo '<div class="component-tree-row">';
        echo '<a class="component-tree-link text-decoration-none text-truncate" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
        echo '</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</nav>';
}

echo '</div>';
echo '<div class="col-12 col-lg-8">';

if ($saved === '1') {
    echo '<div class="alert alert-success">Врезка сохранена.</div>';
}
if ($errorMessage !== '') {
    echo '<div class="alert alert-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}
if ($error !== '') {
    echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

if ($keyword === '' && $snippets === []) {
    echo '<div class="text-muted">Врезки пока не созданы.</div>';
} else {
    echo '<form method="post" action="/admin.php?action=snippet_save">';
    echo csrf_token_field();

    echo '<div class="mb-3">';
    echo '<label class="form-label">Ключ</label>';
    if ($snippetExists) {
        echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" readonly>';
    } else {
        echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" required>';
    }
    echo '</div>';

    echo '<div class="mb-3 js-code-editor-wrapper">';
    echo '<label class="form-label">Содержимое</label>';
    echo '<textarea class="form-control font-monospace code-editor" id="snippet_content" name="content" rows="16">' . renderTextareaValue($content) . '</textarea>';
    echo '<div class="mt-2 d-flex gap-2">';
    echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
    echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
    echo '</div>';
    echo '</div>';

    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';

    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function () {';
    echo '  if (window.initCodeEditor) {';
    echo '    window.initCodeEditor(document.getElementById("snippet_content"), "application/x-httpd-php");';
    echo '  }';
    echo '});';
    echo '</script>';
}

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
