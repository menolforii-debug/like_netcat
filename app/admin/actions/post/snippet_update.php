<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';

if ($id <= 0) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'error' => 'Врезка не найдена']));
}

$snippet = $snippetRepo->findById($id);
if ($snippet === null) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'error' => 'Врезка не найдена']));
}

$snippetRepo->update($id, $content);

if ($user) {
    AdminLog::log($user['id'], 'snippet_update', 'snippet', $id, [
        'keyword' => $snippet['keyword'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'snippets', 'snippet' => $snippet['keyword']]));
