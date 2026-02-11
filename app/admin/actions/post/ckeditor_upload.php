<?php

$callbackNum = isset($_GET['CKEditorFuncNum']) ? (int) $_GET['CKEditorFuncNum'] : 0;
if ($callbackNum <= 0) {
    $callbackNum = 1;
}

$respond = static function (string $url, string $message = '') use ($callbackNum): void {
    header('Content-Type: text/html; charset=UTF-8');
    $urlJs = json_encode($url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $messageJs = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    echo '<script>window.parent.CKEDITOR.tools.callFunction(' . $callbackNum . ', ' . $urlJs . ', ' . $messageJs . ');</script>';
    exit;
};

if (!Auth::canEdit()) {
    $respond('', 'Недостаточно прав');
}

$uploadFile = $_FILES['upload'] ?? null;
if (!is_array($uploadFile)) {
    $respond('', 'Файл не передан');
}

$objectId = isset($_GET['object_id']) ? (int) $_GET['object_id'] : 0;
$infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
$tmpKey = isset($_GET['tmp_key']) ? trim((string) $_GET['tmp_key']) : '';
$requestedComponentKey = isset($_GET['component_key']) ? trim((string) $_GET['component_key']) : '';

if ($requestedComponentKey === '' || !componentKeyIsValid($requestedComponentKey)) {
    $respond('', 'Некорректный ключ компонента');
}

$infoblock = null;
$component = null;

if ($objectId > 0) {
    $object = $objectRepo->findById($objectId);
    if ($object === null) {
        $respond('', 'Объект не найден');
    }

    $infoblock = $infoblockRepo->findById((int) $object['infoblock_id']);
    if ($infoblock === null) {
        $respond('', 'Инфоблок не найден');
    }

    if (!Permission::canAction($user, $infoblock, 'edit')) {
        $respond('', 'Недостаточно прав');
    }

    $component = $componentRepo->findById((int) $object['component_id']);
} else {
    if ($infoblockId <= 0) {
        $respond('', 'Инфоблок не найден');
    }

    $infoblock = $infoblockRepo->findById($infoblockId);
    if ($infoblock === null) {
        $respond('', 'Инфоблок не найден');
    }

    if (!Permission::canAction($user, $infoblock, 'create')) {
        $respond('', 'Недостаточно прав');
    }

    if ($tmpKey === '' || !preg_match('/^[A-Fa-f0-9]{32}$/', $tmpKey)) {
        $respond('', 'Некорректный временный ключ загрузки');
    }

    $component = $componentRepo->findById((int) $infoblock['component_id']);
}

if ($component === null) {
    $respond('', 'Компонент не найден');
}

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey === '' || !componentKeyIsValid($componentKey)) {
    $respond('', 'Некорректный ключ компонента');
}
if ($componentKey !== $requestedComponentKey) {
    $respond('', 'Ключ компонента не совпадает с инфоблоком');
}

$rootDir = dirname(__DIR__, 4);
if ($objectId > 0) {
    $targetDir = $rootDir . '/public_html/files/component/' . $componentKey . '/' . $objectId;
    $publicPrefix = '/files/component/' . $componentKey . '/' . $objectId;
} else {
    $targetDir = $rootDir . '/public_html/files/component/' . $componentKey . '/_tmp/' . $tmpKey;
    $publicPrefix = '/files/component/' . $componentKey . '/_tmp/' . $tmpKey;
}

$error = null;
$storedPath = saveUploadedFile($uploadFile, $targetDir, $publicPrefix, $error);
if ($error !== null || $storedPath === null) {
    $respond('', $error ?: 'Не удалось сохранить файл');
}

$respond($storedPath, '');
