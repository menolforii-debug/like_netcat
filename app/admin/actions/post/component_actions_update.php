<?php

$componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
$actionsTpl = isset($_POST['actions_tpl']) ? (string) $_POST['actions_tpl'] : '';
$returnView = isset($_POST['return_view']) ? (string) $_POST['return_view'] : '';
$returnTab = isset($_POST['return_tab']) ? (string) $_POST['return_tab'] : 'actions';

if ($componentId === 0) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$component = $componentRepo->findById($componentId);
if ($component === null) {
    redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Компонент не найден']));
}

$error = null;
if (!writeComponentActionTemplate((string) $component['keyword'], $actionsTpl, $error)) {
    redirectTo(buildAdminUrl([
        'action' => 'components',
        'component_id' => $componentId,
        'view' => $returnView,
        'view_tab' => $returnTab,
        'error' => $error ?? 'Не удалось сохранить шаблон действий',
    ]));
}

redirectTo(buildAdminUrl([
    'action' => 'components',
    'component_id' => $componentId,
    'view' => $returnView,
    'view_tab' => $returnTab,
]));
