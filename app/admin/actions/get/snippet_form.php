<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

$keyword = isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '';
$saved = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
$errorMessage = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$error = '';

if ($keyword !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
    $error = 'Ключ должен содержать только латиницу, цифры, дефис и подчёркивание.';
    $keyword = '';
}

$root = dirname(__DIR__, 4);
$snippetsDir = $root . '/templates/snippets';
$content = '';
$snippetExists = false;

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
echo '<h1 class="h5 mb-0">' . ($snippetExists ? 'Редактирование врезки' : 'Новая врезка') . '</h1>';
echo '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'snippet_list']), ENT_QUOTES, 'UTF-8') . '">К списку</a>';
echo '</div>';

    // Сообщения create/update/delete теперь показываются только через AJAX → toast.
    if ($error !== '') {
        echo '<script>window.showGlobalSnackbar(' . json_encode((string) $error, JSON_UNESCAPED_UNICODE) . ', "error");</script>';
    }

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
echo '<textarea class="form-control font-monospace" id="snippet_content" name="content" rows="16">' . renderTextareaValue($content) . '</textarea>';
echo '<div class="mt-2 d-flex gap-2">';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
echo '</div>';
echo '</div>';

echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<script>';
echo 'document.addEventListener("DOMContentLoaded", function () {';
echo '  if (window.initCodeEditor) {';
echo '    window.initCodeEditor(document.getElementById("snippet_content"), "application/x-httpd-php");';
echo '  }';
echo '});';
echo '</script>';

AdminLayout::renderFooter();
