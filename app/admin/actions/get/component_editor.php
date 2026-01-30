<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$components = $componentRepo->listAll();
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

$selectedComponent = null;
foreach ($components as $component) {
    if ((int) $component['id'] === $componentId) {
        $selectedComponent = $component;
        break;
    }
}

if ($selectedComponent === null && $components !== []) {
    $selectedComponent = $components[0];
    $componentId = (int) $selectedComponent['id'];
}

$viewsByComponent = [];
foreach ($components as $component) {
    $views = [];
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
    $viewsByComponent[(int) $component['id']] = $views;
}

$content = '';
$componentKey = '';
$error = '';
$selectedView = '';

if ($selectedComponent !== null) {
    $componentKey = trim((string) ($selectedComponent['keyword'] ?? ''));
    if ($componentKey === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
        $error = 'Ключ компонента некорректен.';
    } else {
        $views = $viewsByComponent[(int) $selectedComponent['id']] ?? ['default'];
        if ($viewParam === '' || !in_array($viewParam, $views, true)) {
            $viewParam = $views[0];
        }
        $selectedView = $viewParam;

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
            $fileName = $allowedFiles[$fileKey];
            $filePath = $baseReal . $componentKey . '/' . $selectedView . '/' . $fileName;
            if (is_file($filePath)) {
                $realFile = realpath($filePath);
                if ($realFile !== false && strpos($realFile, $baseReal) === 0) {
                    $content = file_get_contents($realFile) ?: '';
                } else {
                    $error = 'Некорректный путь к файлу шаблона.';
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

AdminLayout::renderHeader('Компоненты');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>'; 
echo '<h1 class="h5 mb-0">Компоненты</h1>';
echo '<div class="text-muted small">Редактор шаблонов компонентов</div>';
echo '</div>';
echo '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'components']), ENT_QUOTES, 'UTF-8') . '">Настройки компонентов</a>';
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

echo '<div class="row">';
echo '<div class="col-3 border-end" style="height:85vh; overflow:auto">';

if (empty($components)) {
    echo '<div class="text-muted">Компоненты пока не созданы.</div>';
} else {
    echo '<div class="accordion" id="componentEditorAccordion">';
    foreach ($components as $index => $component) {
        $compId = (int) $component['id'];
        $compName = (string) $component['name'];
        $compKeyword = (string) ($component['keyword'] ?? '');
        $isActive = $selectedComponent !== null && $compId === (int) $selectedComponent['id'];
        $views = $viewsByComponent[$compId] ?? ['default'];
        $collapseId = 'componentCollapse' . $compId;
        $headerId = 'componentHeading' . $compId;
        $expanded = $isActive ? 'true' : 'false';
        $showClass = $isActive ? ' show' : '';

        echo '<div class="accordion-item">';
        echo '<h2 class="accordion-header" id="' . htmlspecialchars($headerId, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button class="accordion-button' . ($isActive ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') . '" aria-expanded="' . $expanded . '" aria-controls="' . htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($compName, ENT_QUOTES, 'UTF-8');
        echo '<span class="ms-2 text-muted small">' . htmlspecialchars($compKeyword, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</button>';
        echo '</h2>';

        echo '<div id="' . htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') . '" class="accordion-collapse collapse' . $showClass . '" aria-labelledby="' . htmlspecialchars($headerId, ENT_QUOTES, 'UTF-8') . '" data-bs-parent="#componentEditorAccordion">';
        echo '<div class="accordion-body">';

        foreach ($views as $view) {
            echo '<div class="mb-2">';
            echo '<div class="fw-semibold small">' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="list-group list-group-flush ms-2">';
            foreach ($allowedFiles as $key => $fileName) {
                $link = buildAdminUrl([
                    'action' => 'component_editor',
                    'component_id' => $compId,
                    'view' => $view,
                    'file' => $key,
                ]);
                $isSelected = $isActive && $selectedView === $view && $fileKey === $key;
                $activeClass = $isSelected ? ' active' : '';
                echo '<a class="list-group-item list-group-item-action' . $activeClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
                echo htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';
echo '<div class="col-9">';

if ($selectedComponent === null) {
    echo '<div class="text-muted">Выберите компонент слева.</div>';
} elseif ($error !== '') {
    echo '<div class="text-muted">Исправьте ошибки слева.</div>';
} else {
    echo '<form method="post" action="/admin.php?action=component_editor_save">';
    echo csrf_token_field();
    echo '<input type="hidden" name="component_id" value="' . (int) $componentId . '">';
    echo '<input type="hidden" name="view" value="' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="file" value="' . htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8') . '">';

    echo '<div class="mb-2 text-muted small">';
    echo 'templates/component/' . htmlspecialchars($componentKey, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($allowedFiles[$fileKey], ENT_QUOTES, 'UTF-8');
    echo '</div>';

    echo '<div class="mb-3 js-code-editor-wrapper">';
    echo '<label class="form-label">Содержимое файла</label>';
    echo '<textarea class="form-control font-monospace" id="code" name="content" rows="20">' . renderTextareaValue($content) . '</textarea>';
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
    echo '    window.initCodeEditor(document.getElementById("code"), "application/x-httpd-php");';
    echo '  }';
    echo '});';
    echo '</script>';
}

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
