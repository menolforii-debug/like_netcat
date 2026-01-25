<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';

if ($keyword === '') {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'snippet' => '_new', 'error' => 'Введите ключ врезки']));
}

if (!snippetKeyIsValid($keyword)) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'snippet' => '_new', 'error' => 'Ключ врезки должен быть URL-безопасным']));
}

$existing = $snippetRepo->findByKeyword($keyword);
if ($existing !== null) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'snippet' => '_new', 'error' => 'Врезка с таким ключом уже существует']));
}

$snippetId = $snippetRepo->create($keyword, $content);

if ($user) {
    AdminLog::log($user['id'], 'snippet_create', 'snippet', $snippetId, [
        'keyword' => $keyword,
    ]);
}

redirectTo(buildAdminUrl(['action' => 'snippets', 'snippet' => $keyword]));
