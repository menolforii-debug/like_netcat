<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$componentId = isset($_GET['component_id']) ? (int) $_GET['component_id'] : 0;
$viewParam = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
$fileKey = isset($_GET['file']) ? trim((string) $_GET['file']) : 'list';
$saved = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
$errorMessage = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$allowedFiles = [
    'list' => 'list.php',
    'single' => 'single.php',
    'system' => 'system.php',
];

if (!isset($allowedFiles[$fileKey])) {
    $fileKey = 'list';
}

$component = $componentId > 0 ? $componentRepo->findById($componentId) : null;
$error = '';
$content = '';
$views = [];
$componentKey = '';

if ($component === null) {
    $error = 'Компонент не найден.';
} else {
    $componentKey = trim((string) ($component['keyword'] ?? ''));
    if ($componentKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
        $error = 'Ключ компонента некорректен.';
    } else {
        $decoded = json_decode((string) ($component['views_json'] ?? ''), true);
        if (is_array($decoded)) {
            foreach ($decoded as $view) {
                $view = trim((string) $view);
                if ($view !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
                    $views[] = $view;
                }
            }
        }
        if ($views === []) {
            $views = ['default'];
        }

        if ($viewParam === '' || !in_array($viewParam, $views, true)) {
            $viewParam = $views[0];
        }

        $root = dirname(__DIR__, 4);
        $baseDir = $root . '/templates/component';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            $error = 'Не удалось подготовить папку шаблонов.';
        } else {
            $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $componentDir = $baseReal . $componentKey . '/' . $viewParam;
            if (!is_dir($componentDir)) {
                mkdir($componentDir, 0775, true);
            }
            $componentDirReal = realpath($componentDir);
            if ($componentDirReal === false || strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) !== 0) {
                $error = 'Некорректный путь к шаблонам компонента.';
            } else {
                $fileName = $allowedFiles[$fileKey];
                $filePath = $componentDirReal . '/' . $fileName;
                if (is_file($filePath)) {
                    $realFile = realpath($filePath);
                    if ($realFile !== false && strpos($realFile, $componentDirReal . DIRECTORY_SEPARATOR) === 0) {
                        $content = file_get_contents($realFile) ?: '';
                    } else {
                        $error = 'Некорректный путь к файлу шаблона.';
                    }
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

AdminLayout::renderHeader('Шаблоны компонентов');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h5 mb-0">Редактирование шаблона</h1>';
echo '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'component_tpl_list']), ENT_QUOTES, 'UTF-8') . '">К списку</a>';
echo '</div>';

if ($saved === '1') {
    echo '<div class="alert alert-success">Шаблон сохранён.</div>';
}
if ($errorMessage !== '') {
    echo '<div class="alert alert-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}
if ($error !== '') {
    echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

if ($component === null) {
    echo '<div class="text-muted">Выберите компонент из списка.</div>';
    echo '</div></div>';
    AdminLayout::renderFooter();
    return;
}

if ($componentKey !== '') {
    echo '<div class="mb-3">';
    echo '<div class="fw-semibold">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<div class="text-muted small">Ключ: ' . htmlspecialchars($componentKey, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '</div>';
}

if ($views !== []) {
    echo '<ul class="nav nav-tabs mb-3">';
    foreach ($views as $view) {
        $activeClass = $view === $viewParam ? ' active' : '';
        $link = buildAdminUrl([
            'action' => 'component_tpl_form',
            'component_id' => $componentId,
            'view' => $view,
            'file' => $fileKey,
        ]);
        echo '<li class="nav-item"><a class="nav-link' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    echo '</ul>';
}

echo '<div class="btn-group mb-3" role="group" aria-label="Файлы">';
foreach ($allowedFiles as $key => $fileName) {
    $activeClass = $key === $fileKey ? ' btn-primary' : ' btn-outline-primary';
    $link = buildAdminUrl([
        'action' => 'component_tpl_form',
        'component_id' => $componentId,
        'view' => $viewParam,
        'file' => $key,
    ]);
    echo '<a class="btn' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</a>';
}
echo '</div>';

echo '<form method="post" action="/admin.php?action=component_tpl_save">';
echo csrf_token_field();
echo '<input type="hidden" name="component_id" value="' . (int) $componentId . '">';
echo '<input type="hidden" name="view" value="' . htmlspecialchars($viewParam, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="file" value="' . htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8') . '">';

echo '<div class="mb-3 js-code-editor-wrapper">';
echo '<label class="form-label">Содержимое файла</label>';
echo '<textarea class="form-control font-monospace" id="component_tpl_content" name="content" rows="18">' . renderTextareaValue($content) . '</textarea>';
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
echo '    window.initCodeEditor(document.getElementById("component_tpl_content"), "application/x-httpd-php");';
echo '  }';
echo '});';
echo '</script>';

AdminLayout::renderFooter();
