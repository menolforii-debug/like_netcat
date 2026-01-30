<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;
$parent = $parentId > 0 ? $sectionRepo->findById($parentId) : null;
if ($parent === null) {
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}
$showInMenuInherit = true;
$showInMenuValue = true;

AdminLayout::renderHeader('Новый раздел');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый раздел</h1>';
echo '<a class="btn btn-link p-0 link-dotted" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $parentId]), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=section_create">';
echo csrf_token_field();
echo '<input type="hidden" name="parent_id" value="' . (int) $parentId . '">';
echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" required></div>';
echo '<div class="mb-3"><label class="form-label">English name</label><input class="form-control" type="text" name="english_name" required></div>';
echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="0"></div>';
$layouts = Layout::listLayouts();
echo '<div class="mb-3"><label class="form-label">Макет дизайна</label><select class="form-select" name="layout">';
echo '<option value="">Наследовать макет сайта</option>';
foreach ($layouts as $layout) {
    echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="mb-3">';
echo '<div class="fw-semibold mb-2">Публичные настройки</div>';
echo '<div class="mb-3"><label class="form-label">H1</label><input class="form-control" type="text" name="h1"></div>';
echo '<div class="mb-3"><label class="form-label">Альтернативное название для меню</label><input class="form-control" type="text" name="menu_title"></div>';
echo '<div><label class="form-label">Показывать в меню</label>';
echo '<input type="hidden" name="show_in_menu_inherit" value="0">';
echo '<div class="form-check">';
echo '<input class="form-check-input js-show-in-menu-inherit" type="checkbox" name="show_in_menu_inherit" value="1"' . ($showInMenuInherit ? ' checked' : '') . '>';
echo '<label class="form-check-label">Наследовать</label>';
echo '</div>';
echo '<input type="hidden" name="show_in_menu" value="0">';
echo '<div class="form-check mt-2">';
echo '<input class="form-check-input js-show-in-menu" type="checkbox" name="show_in_menu" value="1"' . ($showInMenuValue ? ' checked' : '') . ($showInMenuInherit ? ' disabled' : '') . '>';
echo '<label class="form-check-label">Показывать в меню</label>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '<script>';
echo 'document.querySelectorAll(\'.js-show-in-menu-inherit\').forEach(function (checkbox) {';
echo '  var form = checkbox.closest(\'form\');';
echo '  if (!form) return;';
echo '  var target = form.querySelector(\'.js-show-in-menu\');';
echo '  if (!target) return;';
echo '  var applyState = function () {';
echo '    target.disabled = checkbox.checked;';
echo '  };';
echo '  checkbox.addEventListener(\'change\', applyState);';
echo '  applyState();';
echo '});';
echo '</script>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
