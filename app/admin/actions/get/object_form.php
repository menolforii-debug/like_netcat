<?php

$objectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;

$object = $objectId > 0 ? $objectRepo->findById($objectId) : null;
if ($object !== null) {
    $infoblockId = (int) $object['infoblock_id'];
}

$infoblock = null;
$infoblocks = $infoblockRepo->listForSection($sectionId);
foreach ($infoblocks as $row) {
    if ((int) $row['id'] === $infoblockId) {
        $infoblock = $row;
        break;
    }
}

if ($infoblock === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
}

$permissionAction = $object ? 'edit' : 'create';
if (!Permission::canAction($user, $infoblock, $permissionAction)) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
}

$component = $componentRepo->findById((int) $infoblock['component_id']);
if ($component === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
}

$fields = parseComponentFields($component);
$data = [];
$status = 'draft';
if ($object !== null) {
    $data = json_decode((string) $object['data_json'], true);
    if (!is_array($data)) {
        $data = [];
    }
    $status = (string) ($object['status'] ?? 'draft');
}

$isAjax = isAjaxRequest();
if (!$isAjax) {
    AdminLayout::renderHeader('Объект');
    echo '<div class="container" style="max-width: 900px">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h1 class="h5 mb-0">' . ($object ? 'Редактировать объект' : 'Новый объект') . '</h1>';
    echo '<a class="btn btn-link p-0 link-dotted" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
    echo '</div>';
} else {
    echo '<span data-modal-title="' . ($object ? 'Редактировать объект' : 'Новый объект') . '"></span>';
}

echo '<form method="post" action="/admin.php?action=' . ($object ? 'object_update' : 'object_create') . '" enctype="multipart/form-data"' . ($isAjax ? ' data-ajax="true"' : '') . '>';
echo csrf_token_field();
if ($object) {
    echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
} else {
    echo '<input type="hidden" name="infoblock_id" value="' . (int) $infoblock['id'] . '">';
}
echo '<input type="hidden" name="section_id" value="' . (int) $sectionId . '">';
foreach ($fields as $field) {
    echo renderFieldInput($field, $data, [
        'context' => 'component',
        'infoblock_id' => (int) $infoblock['id'],
    ]);
}
$isEnabled = $status === 'published';
echo '<div class="form-check mb-3">';
echo '<input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="object-enabled"' . ($isEnabled ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="object-enabled">Включено</label>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
if ($isAjax) {
    echo '<button class="btn btn-link p-0 link-dotted" type="button" data-bs-dismiss="modal">Отмена</button>';
} else {
    echo '<a class="btn btn-link p-0 link-dotted" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']), ENT_QUOTES, 'UTF-8') . '">Отмена</a>';
}
echo '</div>';
echo '</form>';

if (!$isAjax) {
    echo '</div></div></div>';
    AdminLayout::renderFooter();
}
exit;
