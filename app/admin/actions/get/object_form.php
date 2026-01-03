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
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

$component = $componentRepo->findById((int) $infoblock['component_id']);
if ($component === null) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$fields = parseComponentFields($component);
$data = [];
if ($object !== null) {
    $data = json_decode((string) $object['data_json'], true);
    if (!is_array($data)) {
        $data = [];
    }
}

AdminLayout::renderHeader('Объект');
echo '<div class="container" style="max-width: 900px">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h5 mb-0">' . ($object ? 'Редактировать объект' : 'Новый объект') . '</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';
echo '<form method="post" action="/admin.php?action=' . ($object ? 'object_update' : 'object_create') . '">';
echo csrfTokenField();
if ($object) {
    echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
} else {
    echo '<input type="hidden" name="infoblock_id" value="' . (int) $infoblock['id'] . '">';
}
echo '<input type="hidden" name="section_id" value="' . (int) $sectionId . '">';
foreach ($fields as $field) {
    echo renderFieldInput($field, $data);
}
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-primary" type="submit" name="save_as" value="draft">Сохранить черновик</button>';
echo '<button class="btn btn-success" type="submit" name="save_as" value="publish">Опубликовать</button>';
echo '</div>';
echo '</form>';
echo '</div></div></div>';
AdminLayout::renderFooter();
