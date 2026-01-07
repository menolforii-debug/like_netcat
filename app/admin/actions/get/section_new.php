<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;
$parent = $parentId > 0 ? $sectionRepo->findById($parentId) : null;
if ($parent === null) {
    redirectTo(buildAdminUrl(['error' => 'Родитель не найден']));
}

AdminLayout::renderHeader('Новый раздел');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Новый раздел</h1>';
echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $parentId]), ENT_QUOTES, 'UTF-8') . '">Назад</a>';
echo '</div>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form method="post" action="/admin.php?action=section_create">';
echo csrfTokenField();
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
echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
echo '</form>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
