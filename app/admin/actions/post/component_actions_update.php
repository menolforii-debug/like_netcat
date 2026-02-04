<?php

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$actionsTpl = isset($_POST['actions_tpl']) ? (string) $_POST['actions_tpl'] : '';
$returnView = isset($_POST['return_view']) ? (string) $_POST['return_view'] : '';
$returnTab = isset($_POST['return_tab']) ? (string) $_POST['return_tab'] : 'actions';

if ($componentId === 0) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['action' => 'components']));
}

$error = null;
if (!writeComponentActionTemplate((string) $component['keyword'], $actionsTpl, $error)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $error ?? 'Не удалось сохранить шаблон действий']);
    }
    adminFlashSet('danger', $error ?? 'Не удалось сохранить шаблон действий');
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
        'view' => $returnView,
        'view_tab' => $returnTab,
    ]));
}

if (isAjaxRequest()) {
    adminOk('Шаблон действий сохранен', [], true, [
        'redirect' => buildAdminUrl([
            'action' => 'components',
            'component_id' => $componentId,
            'view' => $returnView,
            'view_tab' => $returnTab,
        ]),
    ]);
}
adminFlashSet('success', 'Шаблон действий сохранен');

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'view' => $returnView,
    'view_tab' => $returnTab,
]));
