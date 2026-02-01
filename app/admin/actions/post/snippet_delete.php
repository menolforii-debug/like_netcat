<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['action' => 'snippet_list', 'error' => 'Недостаточно прав']));
}

$keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
if ($keyword === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    redirectTo(buildAdminUrl(['action' => 'snippet_list', 'error' => 'Некорректный ключ врезки']));
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
                redirectTo(buildAdminUrl([
                    'action' => 'snippet_list',
                    'keyword' => $keyword,
                    'error' => 'Некорректный путь к файлу врезки',
                ]));
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
        redirectTo(buildAdminUrl([
            'action' => 'snippet_list',
            'keyword' => $keyword,
            'error' => 'Файл удалён, но не удалось удалить запись из БД',
        ]));
    }
}

redirectTo(buildAdminUrl(['action' => 'snippet_list', 'deleted' => 1]));
