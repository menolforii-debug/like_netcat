<?php

$infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$saveAs = isset($_POST['save_as']) ? (string) $_POST['save_as'] : 'draft';

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
$data = extractFormData($fields);
try {
    $data = (new FieldValidator())->validate($component, $data);
} catch (Throwable $e) {
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
}

$status = $saveAs === 'publish' ? 'published' : 'draft';
$objectId = $objectRepo->create([
    'site_id' => $infoblock['site_id'],
    'section_id' => $infoblock['section_id'],
    'infoblock_id' => $infoblock['id'],
    'component_id' => $infoblock['component_id'],
    'data' => $data,
    'status' => $status,
]);

if ($user) {
    AdminLog::log($user['id'], 'object_create', 'object', $objectId, [
        'infoblock_id' => $infoblockId,
        'data' => $data,
        'status' => $status,
    ]);
}

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект создан']));
