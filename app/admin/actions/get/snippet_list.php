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

function renderTextareaValue($value): string
{
    $s = (string) $value;
    $s = preg_replace('~</textarea~i', '&lt;/textarea', $s);
    return $s ?? '';
}

function renderSnippetsSidebarHtml(array $snippets, array $snippetNames, string $keyword): void
{
    $createLink = buildAdminUrl(['action' => 'snippet_list', 'new' => 1]);
    echo '<div class="d-flex align-items-center justify-content-between mb-2">';
    echo '<div class="fw-semibold">Врезки</div>';
    echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars($createLink, ENT_QUOTES, 'UTF-8') . '" title="Добавить врезку" aria-label="Добавить врезку">+</a>';
    echo '</div>';

    if (empty($snippets)) {
        echo '<div class="text-muted">Врезки пока не созданы.</div>';
        return;
    }

    echo '<nav class="nav-deep nav-deep-sm nav-deep-light component-tree">';
    echo '<ul class="nav flex-column component-tree-root">';
    foreach ($snippets as $snippet) {
        $snippetLabel = $snippetNames[$snippet] ?? '';
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
        if ($snippetLabel !== '') {
            echo '<div class="text-muted small ms-3 mb-2">' . htmlspecialchars($snippetLabel, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
    echo '</nav>';
}

function renderSnippetsContentHtml(
    array $snippets,
    string $keyword,
    bool $snippetExists,
    string $snippetName,
    string $content,
    string $error
): void {
    if ($error !== '') {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if ($keyword === '' && $snippets === []) {
        echo '<div class="text-muted">Врезки пока не созданы.</div>';
        return;
    }

    echo '<form method="post" action="/admin.php?action=snippet_save" data-ajax="true">';
    echo csrf_token_field();

    echo '<div class="mb-3">';
    echo '<label class="form-label">Ключ</label>';
    if ($snippetExists) {
        echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" readonly>';
    } else {
        echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" required>';
    }
    echo '</div>';

    echo '<div class="mb-3">';
    echo '<label class="form-label">Название</label>';
    echo '<input class="form-control" name="name" value="' . htmlspecialchars($snippetName, ENT_QUOTES, 'UTF-8') . '">';
    echo '</div>';

    echo '<div class="mb-3 js-code-editor-wrapper">';
    echo '<label class="form-label">Содержимое</label>';
    echo '<textarea class="form-control font-monospace code-editor" id="snippet_content" name="content" rows="16">' . renderTextareaValue($content) . '</textarea>';
    echo '<div class="mt-2 d-flex gap-2">';
    echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
    echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
    echo '</div>';
    echo '</div>';

    echo '<div class="d-flex gap-2 align-items-center">';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
    if ($snippetExists) {
        echo '<form method="post" action="/admin.php?action=snippet_delete" data-ajax="true" data-confirm="Удалить врезку? Это действие необратимо.">';
        echo csrf_token_field();
        echo '<input type="hidden" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
        echo '</form>';
    }
    echo '</div>';
}

// Snippets partials for AJAX refresh
if (isAjaxRequest() && isset($_GET['partial']) && (string) $_GET['partial'] === 'sidebar') {
    renderSnippetsSidebarHtml($snippets, $snippetNames, $keyword);
    exit;
}
if (isAjaxRequest() && isset($_GET['partial']) && (string) $_GET['partial'] === 'content') {
    renderSnippetsContentHtml($snippets, $keyword, $snippetExists, $snippetName, $content, $error);
    exit;
}

AdminLayout::renderHeader('Врезки');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h5 mb-0">Врезки</h1>';
echo '</div>';

echo '<div class="row g-4">';
echo '<div class="col-12 col-lg-4">';

$sidebarTpl = buildAdminUrl(['action' => 'snippet_list', 'keyword' => '{keyword}', 'partial' => 'sidebar']);
echo '<div id="snippetsSidebarBlock" data-refresh-url-template="' . htmlspecialchars($sidebarTpl, ENT_QUOTES, 'UTF-8') . '">';
renderSnippetsSidebarHtml($snippets, $snippetNames, $keyword);
echo '</div>';

echo '</div>';
echo '<div class="col-12 col-lg-8">';

$contentTpl = buildAdminUrl(['action' => 'snippet_list', 'keyword' => '{keyword}', 'partial' => 'content']);
echo '<div id="snippetsContentBlock" data-refresh-url-template="' . htmlspecialchars($contentTpl, ENT_QUOTES, 'UTF-8') . '">';
renderSnippetsContentHtml($snippets, $keyword, $snippetExists, $snippetName, $content, $error);
echo '</div>';

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
