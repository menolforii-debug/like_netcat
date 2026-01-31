<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$site = $id > 0 ? $sectionRepo->findById($id) : null;
if ($id > 0 && ($site === null || $site['parent_id'] !== null)) {
    echo '<div class="text-danger">Сайт не найден.</div>';
    exit;
}

$extra = $site ? Utils::decodeExtra($site) : [];
$mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
$enabled = $site ? !empty($extra['site_enabled']) : true;
$offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';
$currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
$layouts = Layout::listLayouts();
if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
    $currentLayout = '';
}

echo '<span data-modal-title="' . ($site ? 'Редактировать сайт' : 'Новый сайт') . '"></span>';
echo '<form method="post" action="/admin.php?action=' . ($site ? 'site_update' : 'site_create') . '" data-ajax="true">';
echo csrf_token_field();
if ($site) {
    echo '<input type="hidden" name="id" value="' . (int) $site['id'] . '">';
}
echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($site['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
echo '<div class="mb-3"><label class="form-label">Основной домен</label><input class="form-control" type="text" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="mb-3"><label class="form-label">Зеркала домена (по одному в строке)</label><textarea class="form-control" name="site_mirrors" rows="3">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
$checked = $enabled ? ' checked' : '';
echo '<div class="mb-3 form-check">';
echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1"' . $checked . '>';
echo '<label class="form-check-label">Сайт включен</label>';
echo '</div>';
echo '<div class="mb-3"><label class="form-label">HTML для отключенного сайта</label><textarea class="form-control" name="site_offline_html" rows="4">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
echo '<div class="mb-3"><label class="form-label">Макет дизайна по умолчанию</label><select class="form-select" name="layout">';
echo '<option value="">По умолчанию</option>';
foreach ($layouts as $layout) {
    $selectedAttr = $currentLayout === $layout ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select><div class="form-text">Наследуется разделами, если у них не задан собственный макет.</div></div>';
echo '<div class="d-flex justify-content-end gap-2">';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</div>';
echo '</form>';
