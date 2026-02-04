<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['action' => 'snippet_list']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
if ($keyword === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ врезки']);
    }
    adminFlashSet('danger', 'Некорректный ключ врезки');
    redirectTo(buildAdminUrl(['action' => 'snippet_list']));
}

$root = dirname(__DIR__, 4);
$snippetsDir = $root . '/templates/snippets';

// удаляем файл безопасно (только внутри templates/snippets)
if (is_dir($snippetsDir)) {
    $baseReal = realpath($snippetsDir);
    if ($baseReal !== false) {
        $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $snippetPath = $baseReal . $keyword . '.php';
        if (is_file($snippetPath)) {
            $realSnippetPath = realpath($snippetPath);
            if ($realSnippetPath !== false && strpos($realSnippetPath, $baseReal) === 0) {
                @unlink($realSnippetPath);
            } else {
                if (isAjaxRequest()) {
                    jsonResponse(['ok' => false, 'error' => 'Некорректный путь к файлу врезки']);
                }
                adminFlashSet('danger', 'Некорректный путь к файлу врезки');
                redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
            }
        }
    }
}

// удаляем метаданные из БД (если таблица существует)
if (DB::hasTable('snippet')) {
    try {
        $stmt = DB::pdo()->prepare('DELETE FROM snippet WHERE keyword = :keyword');
        $stmt->execute(['keyword' => $keyword]);
    } catch (Throwable $e) {
        // файл уже удалён — но сообщим о проблеме с БД
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Файл удалён, но не удалось удалить запись из БД']);
        }
        adminFlashSet('danger', 'Файл удалён, но не удалось удалить запись из БД');
        redirectTo(buildAdminUrl(['action' => 'snippet_list', 'keyword' => $keyword]));
    }
}

if (isAjaxRequest()) {
    adminOk('Врезка удалена', ['keyword' => ''], true);
}
adminFlashSet('success', 'Врезка удалена');
redirectTo(buildAdminUrl(['action' => 'snippet_list']));
