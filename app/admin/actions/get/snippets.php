<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$snippets = $snippetRepo->listAll();
$snippetKey = isset($_GET['snippet']) ? trim((string) $_GET['snippet']) : '';

if ($snippetKey !== '' && $snippetKey !== '_new') {
    $exists = false;
    foreach ($snippets as $snippet) {
        if ((string) $snippet['keyword'] === $snippetKey) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $snippetKey = '';
    }
}

$selectedSnippet = null;
if ($snippetKey !== '' && $snippetKey !== '_new') {
    $selectedSnippet = $snippetRepo->findByKeyword($snippetKey);
}

function renderTextareaValue($value): string
{
    $s = (string) $value;
    $s = preg_replace('~</textarea~i', '&lt;/textarea', $s);
    return $s ?? '';
}

AdminLayout::renderHeader('Врезки');

AdminLayout::openSidebar();

echo '<div class="card shadow-sm border-0">';
echo '<div class="card-body p-3">';
echo '<div class="d-flex align-items-center justify-content-between mb-2">';
echo '<div class="fw-semibold">Врезки</div>';
echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'snippets', 'snippet' => '_new']), ENT_QUOTES, 'UTF-8') . '" title="Добавить врезку" aria-label="Добавить врезку">+</a>';
echo '</div>';

if (empty($snippets)) {
    echo '<div class="text-muted">Врезок пока нет.</div>';
} else {
    echo '<nav class="nav-deep nav-deep-sm nav-deep-light">';
    echo '<ul class="nav flex-column">';
    foreach ($snippets as $snippet) {
        $keyword = (string) $snippet['keyword'];
        $isActive = $keyword === $snippetKey;
        $link = buildAdminUrl(['action' => 'snippets', 'snippet' => $keyword]);
        $activeClass = $isActive ? ' fw-bold' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link text-decoration-none text-truncate' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</nav>';
}
echo '</div>';
echo '</div>';

AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';

if ($snippetKey === '_new') {
    echo '<form method="post" action="/admin.php?action=snippet_create">';
    echo csrf_token_field();
    echo '<div class="mb-3"><label class="form-label">Ключ врезки</label><input class="form-control" name="keyword" required></div>';
    echo '<div class="mb-3 js-code-editor-wrapper">';
    echo '<label class="form-label">Содержимое</label>';
    echo '<textarea class="form-control font-monospace code-editor" name="content" rows="14"></textarea>';
    echo '<div class="mt-2 d-flex gap-2">';
    echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-expand" type="button">Развернуть</button>';
    echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-collapse d-none" type="button">Свернуть</button>';
    echo '</div>';
    echo '</div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
} elseif ($selectedSnippet !== null) {
    echo '<form method="post" action="/admin.php?action=snippet_update">';
    echo csrf_token_field();
    echo '<input type="hidden" name="id" value="' . (int) $selectedSnippet['id'] . '">';
    echo '<div class="mb-3"><label class="form-label">Ключ врезки</label><input class="form-control" name="keyword" value="' . htmlspecialchars((string) $selectedSnippet['keyword'], ENT_QUOTES, 'UTF-8') . '" readonly></div>';
    echo '<div class="mb-3 js-code-editor-wrapper">';
    echo '<label class="form-label">Содержимое</label>';
    echo '<textarea class="form-control font-monospace code-editor" name="content" rows="14">' . renderTextareaValue($selectedSnippet['content'] ?? '') . '</textarea>';
    echo '<div class="mt-2 d-flex gap-2">';
    echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-expand" type="button">Развернуть</button>';
    echo '<button class="btn btn-sm btn-outline-secondary js-code-editor-collapse d-none" type="button">Свернуть</button>';
    echo '</div>';
    echo '</div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
    echo '<form class="mt-2" method="post" action="/admin.php?action=snippet_delete" onsubmit="return confirm(\'Удалить врезку?\')">';
    echo csrf_token_field();
    echo '<input type="hidden" name="id" value="' . (int) $selectedSnippet['id'] . '">';
    echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
    echo '</form>';
} else {
    if (empty($snippets)) {
        echo '<div class="text-muted">Создайте первую врезку.</div>';
    } else {
        echo '<div class="text-muted">Выберите врезку для редактирования.</div>';
    }
}

echo '</div></div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
