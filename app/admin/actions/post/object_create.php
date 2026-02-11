<?php

$infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
$isEnabled = !empty($_POST['is_enabled']);
$uploadTmpKey = isset($_POST['upload_tmp_key']) ? trim((string) $_POST['upload_tmp_key']) : '';

$infoblock = null;
$infoblocks = $infoblockRepo->listForSection($sectionId);
foreach ($infoblocks as $row) {
    if ((int) $row['id'] === $infoblockId) {
        $infoblock = $row;
        break;
    }
}

if ($infoblock === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Инфоблок не найден']);
    }
    adminFlashSet('danger', 'Инфоблок не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден']));
}

if (!Permission::canAction($user, $infoblock, 'create')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав']);
    }
    adminFlashSet('danger', 'Недостаточно прав');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав']));
}

if ($isEnabled && !Permission::canAction($user, $infoblock, 'publish')) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Недостаточно прав для публикации']);
    }
    adminFlashSet('danger', 'Недостаточно прав для публикации');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Недостаточно прав для публикации']));
}

$component = $componentRepo->findById((int) $infoblock['component_id']);
if ($component === null) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Компонент не найден']);
    }
    adminFlashSet('danger', 'Компонент не найден');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден']));
}

$componentKey = trim((string) ($component['keyword'] ?? ''));
if ($componentKey === '' || !componentKeyIsValid($componentKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный ключ компонента']);
    }
    adminFlashSet('danger', 'Некорректный ключ компонента');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Некорректный ключ компонента']));
}

if ($uploadTmpKey === '' || !preg_match('/^[A-Fa-f0-9]{32}$/', $uploadTmpKey)) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => 'Некорректный временный ключ загрузки']);
    }
    adminFlashSet('danger', 'Некорректный временный ключ загрузки');
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Некорректный временный ключ загрузки']));
}

$rootDir = dirname(__DIR__, 4);
$tmpTargetDir = $rootDir . '/public_html/files/component/' . $componentKey . '/_tmp/' . $uploadTmpKey;
$tmpPublicPrefix = '/files/component/' . $componentKey . '/_tmp/' . $uploadTmpKey;

$fields = parseComponentFields($component);
$data = extractFormData($fields);
foreach ($fields as $field) {
    if (($field['type'] ?? '') !== 'file') {
        continue;
    }

    $name = (string) $field['name'];
    if (!isset($_FILES[$name])) {
        continue;
    }

    $error = null;
    $storedPath = saveUploadedFile($_FILES[$name], $tmpTargetDir, $tmpPublicPrefix, $error);
    if ($error !== null) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => $error]);
        }
        adminFlashSet('danger', $error);
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $error]));
    }
    if ($storedPath !== null) {
        $data[$name] = $storedPath;
    }
}
try {
    $data = (new FieldValidator())->validate($component, $data);
} catch (InvalidArgumentException $e) {
    if (isAjaxRequest()) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
    adminFlashSet('danger', $e->getMessage());
    redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
}

$status = $isEnabled ? 'published' : 'draft';
$objectId = $objectRepo->create([
    'site_id' => $infoblock['site_id'],
    'section_id' => $infoblock['section_id'],
    'infoblock_id' => $infoblock['id'],
    'component_id' => $infoblock['component_id'],
    'data' => $data,
    'status' => $status,
]);

$finalTargetDir = $rootDir . '/public_html/files/component/' . $componentKey . '/' . $objectId;
$finalPublicPrefix = '/files/component/' . $componentKey . '/' . $objectId;

if (is_dir($tmpTargetDir)) {
    if (!is_dir(dirname($finalTargetDir))) {
        mkdir(dirname($finalTargetDir), 0770, true);
        @chmod(dirname($finalTargetDir), 0770);
    }

    if (!@rename($tmpTargetDir, $finalTargetDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpTargetDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relative = ltrim(substr($sourcePath, strlen($tmpTargetDir)), DIRECTORY_SEPARATOR);
            $destPath = $finalTargetDir . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0770, true);
                    @chmod($destPath, 0770);
                }
            } else {
                if (!is_dir(dirname($destPath))) {
                    mkdir(dirname($destPath), 0770, true);
                    @chmod(dirname($destPath), 0770);
                }
                @rename($sourcePath, $destPath);
            }
        }
        if (is_dir($tmpTargetDir)) {
            rmTree($tmpTargetDir, $rootDir . '/public_html/files/component/' . $componentKey . '/_tmp');
        }
    }

    $replacePrefix = static function ($value) use (&$replacePrefix, $tmpPublicPrefix, $finalPublicPrefix) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $replacePrefix($v);
            }
            return $value;
        }
        if (is_string($value)) {
            return str_replace($tmpPublicPrefix . '/', $finalPublicPrefix . '/', $value);
        }
        return $value;
    };
    $data = $replacePrefix($data);
    $objectRepo->update($objectId, ['data' => $data]);
}

$actionTemplatePath = componentActionTemplatePath((string) $component['keyword']);
if (is_file($actionTemplatePath)) {
    $message = $objectId;
    $object = $objectRepo->findById($objectId);
    try {
        require $actionTemplatePath;
    } catch (Throwable $e) {
        if (isAjaxRequest()) {
            jsonResponse(['ok' => false, 'error' => 'Ошибка выполнения шаблона действий: ' . $e->getMessage()]);
        }
        adminFlashSet('danger', 'Ошибка выполнения шаблона действий: ' . $e->getMessage());
        redirectTo(buildAdminUrl([
            'section_id' => $sectionId,
            'tab' => 'content',
            'error' => 'Ошибка выполнения шаблона действий: ' . $e->getMessage(),
        ]));
    }
}

if ($user) {
    AdminLog::log($user['id'], 'object_create', 'object', $objectId, [
        'infoblock_id' => $infoblockId,
        'data' => $data,
        'status' => $status,
    ]);
}

if (isAjaxRequest()) {
    adminOk('Объект создан', [], true, [
        'refresh' => ['#content'],
    ]);
}
adminFlashSet('success', 'Объект создан');

redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']));
