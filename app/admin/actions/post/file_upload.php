<?php

if (!Auth::canEdit()) {
    jsonResponse(['ok' => false, 'error' => 'Недостаточно прав'], 403);
}

$context = isset($_POST['context']) ? (string) $_POST['context'] : '';
$fieldName = isset($_POST['field_name']) ? (string) $_POST['field_name'] : '';
if ($fieldName === '') {
    jsonResponse(['ok' => false, 'error' => 'Не указано поле файла'], 400);
}

$targetDir = null;
$publicPrefix = null;
$file = null;

if ($context === 'component') {
    $infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
    $infoblock = $infoblockRepo->findById($infoblockId);
    if ($infoblock === null) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден'], 404);
    }
    if (!Permission::canAction($user, $infoblock, 'edit')) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав'], 403);
    }
    if (isset($_FILES[$fieldName]) && is_array($_FILES[$fieldName])) {
        $file = $_FILES[$fieldName];
    }

    $targetDir = dirname(__DIR__, 4) . '/public_html/files/component/' . $infoblockId;
    $publicPrefix = '/files/component/' . $infoblockId;
} elseif ($context === 'layout') {
    if (!Auth::isAdmin()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав'], 403);
    }
    $layoutKey = isset($_POST['layout_key']) ? (string) $_POST['layout_key'] : '';
    $fieldId = isset($_POST['field_id']) ? (int) $_POST['field_id'] : 0;
    if ($layoutKey === '' || !layoutKeyIsValid($layoutKey) || $fieldId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Некорректные параметры'], 400);
    }
    if (isset($_FILES['visual_settings']) && is_array($_FILES['visual_settings'])) {
        $file = extractNestedUpload($_FILES['visual_settings'], $fieldName);
    }

    $targetDir = dirname(__DIR__, 4) . '/public_html/files/layouts/' . $layoutKey . '/' . $fieldId;
    $publicPrefix = '/files/layouts/' . $layoutKey . '/' . $fieldId;
} else {
    jsonResponse(['ok' => false, 'error' => 'Неизвестный контекст загрузки'], 400);
}

if ($file === null) {
    jsonResponse(['ok' => false, 'error' => 'Файл не найден'], 400);
}

$error = null;
$storedPath = saveUploadedFile($file, $targetDir, $publicPrefix, $error);
if ($error !== null) {
    jsonResponse(['ok' => false, 'error' => $error], 400);
}
if ($storedPath === null) {
    jsonResponse(['ok' => false, 'error' => 'Не удалось сохранить файл'], 500);
}

$originalName = isset($file['name']) ? (string) $file['name'] : '';
$fileList = $originalName !== '' ? [$originalName => $storedPath] : [];

jsonResponse([
    'ok' => true,
    'stored_path' => $storedPath,
    'field_name' => $fieldName,
    'file_list' => $fileList,
]);
