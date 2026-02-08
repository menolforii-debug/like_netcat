<?php

if (!Auth::isAdmin()) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    // fallback без сообщений
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl([]));
}

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$view = isset($_POST['view']) ? trim((string) $_POST['view']) : '';

if ($componentId <= 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

if ($view === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректное имя шаблона']);
    }
    adminFlashSet('danger', 'Некорректное имя шаблона');
    redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $componentId, 'tab' => 'templates']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

$views = [];
$decodedViews = json_decode((string) ($component['views_json'] ?? ''), true);
if (is_array($decodedViews)) {
    foreach ($decodedViews as $existingView) {
        if (is_string($existingView) && $existingView !== '') {
            $views[] = $existingView;
        }
    }
}

if (!in_array($view, $views, true)) {
    $views[] = $view;
}

$fields = [];
$decodedFields = json_decode((string) ($component['fields_json'] ?? '[]'), true);
if (is_array($decodedFields)) {
    $fields = $decodedFields['fields'] ?? $decodedFields;
    if (!is_array($fields)) {
        $fields = [];
    }
}

$componentRepo->update($componentId, (string) ($component['keyword'] ?? ''), (string) ($component['name'] ?? ''), $fields, $views);

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
    $root = dirname(__DIR__, 4);
    $baseDir = $root . '/templates/component';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }
    $baseReal = realpath($baseDir);
    if ($baseReal !== false) {
        $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $componentDir = $baseReal . $componentKey . '/' . $view;
        if (!is_dir($componentDir)) {
            mkdir($componentDir, 0775, true);
        }
        $componentDirReal = realpath($componentDir);
        if ($componentDirReal !== false && strpos($componentDirReal . DIRECTORY_SEPARATOR, $baseReal) === 0) {
            $defaultListContent = <<<'PHP'
<?php
/** @var array $items */
?>
<div class="component-list">
    <?php foreach ($items as $item): ?>
        <?php $title = (string) ($item['data']['title'] ?? ''); ?>
        <?php $text = (string) ($item['data']['text'] ?? ''); ?>
        <article class="component-item">
            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if ($text !== ''): ?>
                <p><?php echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
PHP;

            $defaultSingleContent = <<<'PHP'
<?php
/** @var array|null $object */
if ($object === null) {
    return;
}
$title = (string) ($object['data']['title'] ?? '');
$text = (string) ($object['data']['text'] ?? '');
?>
<article class="component-single">
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($text !== ''): ?>
        <div class="content"><?php echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')); ?></div>
    <?php endif; ?>
</article>
PHP;

            $defaultSystemContent = <<<'PHP'
<?php
// Доступны переменные: $section, $site, $infoblock, $component, $isSingle
// Пример системных настроек:
// $query_order = 'a.created_at DESC';
// $query_limit = '10';
// $distinct = 'DISTINCT';
PHP;

            $defaultFiles = [
                'list.php' => $defaultListContent,
                'single.php' => $defaultSingleContent,
                'system.php' => $defaultSystemContent,
            ];

            foreach ($defaultFiles as $fileName => $content) {
                $filePath = $componentDirReal . '/' . $fileName;
                if (!is_file($filePath)) {
                    file_put_contents($filePath, $content, LOCK_EX);
                }
            }
        }
    }
}

if (isAjaxRequest()) {
    adminOk('Шаблон добавлен', [], true, [
        'redirect' => buildAdminUrl([
            'action' => 'components',
            'component_id' => $componentId,
            'tab' => 'templates',
            'view' => $view,
        ]),
    ]);
}
adminFlashSet('success', 'Шаблон добавлен');

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'tab' => 'templates',
    'view' => $view,
]));
