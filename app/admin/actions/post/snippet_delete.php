<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'error' => 'Врезка не найдена']));
}

$snippet = $snippetRepo->findById($id);
if ($snippet === null) {
    redirectTo(buildAdminUrl(['action' => 'snippets', 'error' => 'Врезка не найдена']));
}

$snippetRepo->delete($id);

if ($user) {
    AdminLog::log($user['id'], 'snippet_delete', 'snippet', $id, [
        'keyword' => $snippet['keyword'],
    ]);
}

redirectTo(buildAdminUrl(['action' => 'snippets']));
