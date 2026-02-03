<?php

$sites = $sectionRepo->listSitesOnly();
$sections = [];
foreach ($sites as $site) {
    $sections[] = $site;
    $sections = array_merge($sections, collectSections($sectionRepo, (int) $site['id']));
}

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$currentSite = $sectionRepo->findSiteByHost($host);
$currentSiteId = $currentSite ? (int) $currentSite['id'] : null;

if ($selectedId === null && $currentSiteId !== null) {
    $selectedId = $currentSiteId;
}

$selected = null;
if ($selectedId !== null) {
    $selected = $sectionRepo->findById($selectedId);
}

$currentUser = $user ?? Auth::user();
$isAdmin = Auth::isAdmin();
$visualFields = $visualFieldRepo->listAll();

$renderVisualSettings = function (array $visualFields, array $resolvedVisual, array $localVisual, string $scopeId): void {
    if (empty($visualFields)) {
        echo '<div class="text-muted">Визуальные настройки не определены.</div>';
        return;
    }

    echo '<div class="border rounded p-3 mt-4">';
    echo '<h2 class="h6 mb-3">Визуальные настройки</h2>';
    foreach ($visualFields as $field) {
        $name = (string) $field['name'];
        $label = (string) $field['label'];
        $type = (string) ($field['type'] ?? 'text');
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
        $hasLocal = array_key_exists($name, $localVisual);
        $resolvedValue = $resolvedVisual[$name] ?? '';
        $value = $hasLocal ? $localVisual[$name] : $resolvedValue;
        $disabledAttr = '';
        $fieldId = $scopeId . '-' . $name;
        $previewId = 'file-preview-' . $fieldId;
        $clearId = 'file-clear-' . $fieldId;

        echo '<div class="mb-3">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<label class="form-label mb-0">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
        echo '<div></div>';
        echo '</div>';

        echo '<div id="visual-field-' . htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') . '">';
        $valueEscaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        if ($type === 'textarea') {
            echo '<textarea class="form-control" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" rows="3" data-visual-input' . $disabledAttr . '>' . $valueEscaped . '</textarea>';
        } elseif ($type === 'number') {
            echo '<input class="form-control" type="number" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . $valueEscaped . '" data-visual-input' . $disabledAttr . '>';
        } elseif ($type === 'checkbox') {
            echo '<input type="hidden" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="0" data-visual-input' . $disabledAttr . '>';
            $checked = !empty($value) ? ' checked' : '';
            echo '<div class="form-check mt-2">';
            echo '<input class="form-check-input" type="checkbox" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="1" data-visual-input' . $checked . $disabledAttr . '>';
            echo '<label class="form-check-label">Да</label>';
            echo '</div>';
        } elseif ($type === 'select') {
            echo '<select class="form-select" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" data-visual-input' . $disabledAttr . '>';
            echo '<option value="">—</option>';
            foreach ($options as $optKey => $optLabel) {
                $optKey = (string) $optKey;
                $optLabel = (string) $optLabel;
                $selected = (string) $value === $optKey ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'file') {
            $inputId = 'visual-file-' . $fieldId;
            $deleteId = 'visual-file-delete-' . $fieldId;
            echo '<input class="form-control" id="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '" type="file" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" data-visual-input' . $disabledAttr . ' data-file-preview-container="#' . htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') . '" data-file-preview-show-info="true" data-file-btn-clear="#' . htmlspecialchars($clearId, ENT_QUOTES, 'UTF-8') . '">';
            echo '<div id="' . htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') . '" class="mt-2"></div>';
            echo '<button class="btn btn-sm btn-outline-secondary mt-2" type="button" id="' . htmlspecialchars($clearId, ENT_QUOTES, 'UTF-8') . '">Очистить</button>';
            if ($value !== '') {
                echo '<div class="form-text">Текущий файл: <a href="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars(basename((string) $value), ENT_QUOTES, 'UTF-8') . '</a></div>';
                echo '<div class="form-check mt-2">';
                echo '<input class="form-check-input" type="checkbox" name="visual_settings_delete[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="1" id="' . htmlspecialchars($deleteId, ENT_QUOTES, 'UTF-8') . '">';
                echo '<label class="form-check-label" for="' . htmlspecialchars($deleteId, ENT_QUOTES, 'UTF-8') . '">Удалить файл</label>';
                echo '</div>';
            }
        } elseif ($type === 'color') {
            $colorValue = $value !== '' ? $value : '#ffffff';
            echo '<input class="form-control form-control-color" type="color" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . htmlspecialchars((string) $colorValue, ENT_QUOTES, 'UTF-8') . '" data-visual-input' . $disabledAttr . '>';
        } else {
            echo '<input class="form-control" type="text" name="visual_settings[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . $valueEscaped . '" data-visual-input' . $disabledAttr . '>';
        }
        echo '</div>';

        if (!$hasLocal && $resolvedValue !== '') {
            echo '<div class="form-text">Наследуется значение: ' . htmlspecialchars((string) $resolvedValue, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
};

$renderSidebar = function () use ($sections, $selectedId, $currentSiteId): void {
    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-body p-3">';

    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div class="fw-semibold">Разделы</div>';
    echo '</div>';

    echo SectionTree::render($sections, $selectedId, $currentSiteId);

    echo '</div>';
    echo '</div>';
};

$renderContent = function () use ($selected, $selectedId, $tab, $sectionRepo, $infoblockRepo, $componentRepo, $objectRepo, $currentUser, $isAdmin, $visualFields, $renderVisualSettings): void {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';

    // Как в “Компонентах”: без большого заголовка справа
    // echo '<h1 class="h4 mb-3">Сайты и разделы</h1>';

    if ($selected !== null) {
        $isSite = $selected['parent_id'] === null;

        if ($isSite) {
        $extra = Utils::decodeExtra($selected);
        $mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
        $enabled = !empty($extra['site_enabled']);
        $offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';
        $layouts = LayoutCatalog::listLayouts();
        $currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
        if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
            $currentLayout = '';
        }

        $siteTab = isset($_GET['site_tab']) ? (string) $_GET['site_tab'] : 'settings';
        if (!in_array($siteTab, ['settings', 'design'], true)) {
            $siteTab = 'settings';
        }
        $designTab = isset($_GET['design_tab']) ? (string) $_GET['design_tab'] : 'layout';
        if (!in_array($designTab, ['layout', 'visual'], true)) {
            $designTab = 'layout';
        }

        echo '<ul class="nav nav-tabs mb-3">';
        echo '<li class="nav-item"><a class="nav-link' . ($siteTab === 'settings' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'site_tab' => 'settings']), ENT_QUOTES, 'UTF-8') . '">Настройки</a></li>';
        echo '<li class="nav-item"><a class="nav-link' . ($siteTab === 'design' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'site_tab' => 'design', 'design_tab' => $designTab]), ENT_QUOTES, 'UTF-8') . '">Макет дизайна</a></li>';
        echo '</ul>';

        if ($isAdmin) {
            echo '<form method="post" action="/admin.php?action=site_update" enctype="multipart/form-data">';
            echo csrf_token_field();
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
            if ($siteTab !== 'settings') {
                echo '<input type="hidden" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="site_mirrors" value="' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="site_enabled" value="' . ($enabled ? '1' : '0') . '">';
                echo '<input type="hidden" name="site_offline_html" value="' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '">';
            }
            if ($siteTab === 'settings') {
                echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '" required></div>';
                echo '<div class="mb-3"><label class="form-label">Основной домен</label><input class="form-control" type="text" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="mb-3"><label class="form-label">Зеркала домена (по одному в строке)</label><textarea class="form-control" name="site_mirrors" rows="3">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                $checked = $enabled ? ' checked' : '';
                echo '<div class="mb-3 form-check">';
                echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1"' . $checked . '>';
                echo '<label class="form-check-label">Сайт включен</label>';
                echo '</div>';
                echo '<div class="mb-3"><label class="form-label">HTML для отключенного сайта</label><textarea class="form-control" name="site_offline_html" rows="4">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
            } else {
                echo '<ul class="nav nav-tabs mb-3">';
                echo '<li class="nav-item"><a class="nav-link' . ($designTab === 'layout' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'site_tab' => 'design', 'design_tab' => 'layout']), ENT_QUOTES, 'UTF-8') . '">Макет</a></li>';
                echo '<li class="nav-item"><a class="nav-link' . ($designTab === 'visual' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'site_tab' => 'design', 'design_tab' => 'visual']), ENT_QUOTES, 'UTF-8') . '">Визуальные настройки</a></li>';
                echo '</ul>';

                if ($designTab === 'layout') {
                    echo '<div class="mb-3"><label class="form-label">Макет дизайна по умолчанию</label><select class="form-select" name="layout">';
                    echo '<option value="">По умолчанию</option>';
                    foreach ($layouts as $layout) {
                        $selectedAttr = $currentLayout === $layout ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select><div class="form-text">Наследуется разделами, если у них не задан собственный макет.</div></div>';
                } else {
                    echo '<input type="hidden" name="return_site_tab" value="design">';
                    echo '<input type="hidden" name="return_design_tab" value="visual">';
                    echo '<input type="hidden" name="layout" value="' . htmlspecialchars($currentLayout, ENT_QUOTES, 'UTF-8') . '">';
                    $localVisual = isset($extra['visual_settings']) && is_array($extra['visual_settings']) ? $extra['visual_settings'] : [];
                    $resolvedVisual = $sectionRepo->resolveVisualSettings((int) $selected['id']);
                    $renderVisualSettings($visualFields, $resolvedVisual, $localVisual, 'site-' . (int) $selected['id']);
                }
            }

            echo '<div class="d-flex justify-content-end gap-2 mt-3">';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
        }
        } else {
        $contentInfoblockId = isset($_GET['content_infoblock_id']) ? (int) $_GET['content_infoblock_id'] : 0;

        $tabs = [
            'section' => 'Раздел',
            'design' => 'Макет дизайна',
            'seo' => 'SEO',
            'infoblocks' => 'Инфоблоки',
            'content' => 'Контент',
        ];
        echo '<ul class="nav nav-tabs mb-3">';
        foreach ($tabs as $key => $label) {
            $active = $tab === $key ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => $key]), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        echo '</ul>';

        if ($tab === 'section') {
            $siteId = (int) $selected['site_id'];
            $site = $sectionRepo->findById($siteId);
            $options = [];
            if ($site !== null) {
                $site['depth'] = 0;
                $options[] = $site;
                $options = array_merge($options, collectSectionTree($sectionRepo, $siteId, 1));
            }

            echo '<h1 class="h5 mb-3">Настройки раздела</h1>';
            $extra = Utils::decodeExtra($selected);
            $showInMenuInherit = !array_key_exists('show_in_menu_inherit', $extra) ? true : (bool) $extra['show_in_menu_inherit'];
            $showInMenuValue = array_key_exists('show_in_menu', $extra) ? (bool) $extra['show_in_menu'] : true;
            $menuTitle = isset($extra['menu_title']) ? (string) $extra['menu_title'] : '';
            $h1 = isset($extra['h1']) ? (string) $extra['h1'] : '';
            $siteExtra = $site ? Utils::decodeExtra($site) : [];
            $sectionLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
            $siteLayout = isset($siteExtra['layout']) ? (string) $siteExtra['layout'] : '';
            $currentLayout = '';
            $layoutNote = '';
            if ($sectionLayout !== '' && LayoutCatalog::layoutExists($sectionLayout)) {
                $currentLayout = $sectionLayout;
            } elseif ($siteLayout !== '' && LayoutCatalog::layoutExists($siteLayout)) {
                $currentLayout = $siteLayout;
                $layoutNote = ' (наследуется от сайта)';
            } else {
                $currentLayout = ($selected['parent_id'] ?? null) === null ? 'home' : 'default';
                $layoutNote = ' (по умолчанию)';
            }
            if ($isAdmin) {
                $isSystemRoot = in_array($selected['english_name'], ['index', '404'], true);
                echo '<form method="post" action="/admin.php?action=section_update" enctype="multipart/form-data">';
                echo csrf_token_field();
                echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
                echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '" required></div>';
                $englishNameAttributes = $isSystemRoot ? ' disabled' : ' required';
                $englishNameHint = $isSystemRoot ? '<div class="form-text">Системный раздел: English name фиксирован.</div>' : '';
                echo '<div class="mb-3"><label class="form-label">English name (латиница)</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars((string) ($selected['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $englishNameAttributes . '>' . $englishNameHint . '</div>';
                if ($isSystemRoot) {
                    echo '<input type="hidden" name="parent_id" value="' . (int) ($selected['parent_id'] ?? 0) . '">';
                    echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><div class="form-text">Системный раздел нельзя перемещать.</div></div>';
                } else {
                    echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><select class="form-select" name="parent_id" required>';
                    echo '<option value="">Выберите родителя</option>';
                    foreach ($options as $option) {
                        if ((int) $option['id'] === (int) $selected['id']) {
                            continue;
                        }
                        if ((int) $option['site_id'] !== $siteId) {
                            continue;
                        }
                        $selectedAttr = (int) ($selected['parent_id'] ?? 0) === (int) $option['id'] ? ' selected' : '';
                        $depthPrefix = str_repeat('— ', (int) ($option['depth'] ?? 0));
                        $label = $depthPrefix . '[' . (int) $option['id'] . '] ' . (string) $option['title'];
                        echo '<option value="' . (int) $option['id'] . '"' . $selectedAttr . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select></div>';
                }
                echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="mb-3">';
                echo '<div class="fw-semibold mb-2">Публичные настройки</div>';
                echo '<div class="mb-3"><label class="form-label">H1</label><input class="form-control" type="text" name="h1" value="' . htmlspecialchars($h1, ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="mb-3"><label class="form-label">Альтернативное название для меню</label><input class="form-control" type="text" name="menu_title" value="' . htmlspecialchars($menuTitle, ENT_QUOTES, 'UTF-8') . '"></div>';
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

                echo '<div class="d-flex justify-content-end gap-2 mt-3">';
                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</div>';
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
            } else {
                echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
            }
        } elseif ($tab === 'design') {
            $designTab = isset($_GET['design_tab']) ? (string) $_GET['design_tab'] : 'layout';
            if (!in_array($designTab, ['layout', 'visual'], true)) {
                $designTab = 'layout';
            }
            echo '<ul class="nav nav-tabs mb-3">';
            echo '<li class="nav-item"><a class="nav-link' . ($designTab === 'layout' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => 'design', 'design_tab' => 'layout']), ENT_QUOTES, 'UTF-8') . '">Макет</a></li>';
            echo '<li class="nav-item"><a class="nav-link' . ($designTab === 'visual' ? ' active' : '') . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => 'design', 'design_tab' => 'visual']), ENT_QUOTES, 'UTF-8') . '">Визуальные настройки</a></li>';
            echo '</ul>';

            $siteId = (int) $selected['site_id'];
            $site = $sectionRepo->findById($siteId);
            $extra = Utils::decodeExtra($selected);
            $siteExtra = $site ? Utils::decodeExtra($site) : [];
            $sectionLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
            $siteLayout = isset($siteExtra['layout']) ? (string) $siteExtra['layout'] : '';
            $currentLayout = '';
            $layoutNote = '';
            if ($sectionLayout !== '' && LayoutCatalog::layoutExists($sectionLayout)) {
                $currentLayout = $sectionLayout;
            } elseif ($siteLayout !== '' && LayoutCatalog::layoutExists($siteLayout)) {
                $currentLayout = $siteLayout;
                $layoutNote = ' (наследуется от сайта)';
            } else {
                $currentLayout = ($selected['parent_id'] ?? null) === null ? 'home' : 'default';
                $layoutNote = ' (по умолчанию)';
            }
            if ($isAdmin) {
                echo '<form method="post" action="/admin.php?action=section_update" enctype="multipart/form-data">';
                echo csrf_token_field();
                if ($designTab === 'visual') {
                    echo '<input type="hidden" name="return_tab" value="design">';
                    echo '<input type="hidden" name="return_design_tab" value="visual">';
                }
                echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
                echo '<input type="hidden" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="english_name" value="' . htmlspecialchars((string) ($selected['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="parent_id" value="' . (int) ($selected['parent_id'] ?? 0) . '">';
                echo '<input type="hidden" name="sort" value="' . (int) ($selected['sort'] ?? 0) . '">';
                if ($designTab === 'layout') {
                    echo '<div class="mb-3"><label class="form-label">Макет дизайна</label><select class="form-select" name="layout">';
                    echo '<option value="">Наследовать макет сайта</option>';
                    foreach (LayoutCatalog::listLayouts() as $layout) {
                        $selectedAttr = $sectionLayout === $layout ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select><div class="form-text">Текущий: ' . htmlspecialchars($currentLayout . $layoutNote, ENT_QUOTES, 'UTF-8') . '</div></div>';
                } else {
                    echo '<input type="hidden" name="layout" value="' . htmlspecialchars((string) ($extra['layout'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                    $localVisual = isset($extra['visual_settings']) && is_array($extra['visual_settings']) ? $extra['visual_settings'] : [];
                    $resolvedVisual = $sectionRepo->resolveVisualSettings((int) $selected['id']);
                    $renderVisualSettings($visualFields, $resolvedVisual, $localVisual, 'section-' . (int) $selected['id']);
                }
                echo '<div class="d-flex justify-content-end gap-2 mt-3">';
                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</div>';
                echo '</form>';
            } else {
                echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
            }
        } elseif ($tab === 'seo') {
            $extra = Utils::decodeExtra($selected);
            echo '<h1 class="h5">SEO</h1>';
            if ($isAdmin) {
                echo '<form method="post" action="/admin.php?action=seo_update">';
                echo csrf_token_field();
                echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
                echo '<div class="mb-3"><label class="form-label">SEO заголовок</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="mb-3"><label class="form-label">SEO описание</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="mb-3"><label class="form-label">SEO ключевые слова</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</form>';
            } else {
                echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
            }
        } elseif ($tab === 'infoblocks' || $tab === 'content') {
            $infoblocks = $infoblockRepo->listForSection((int) $selected['id']);
            $components = $componentRepo->listAll();
            $componentMap = [];
            foreach ($components as $component) {
                $componentMap[(int) $component['id']] = $component;
            }

            if ($tab === 'content') {
                if (!empty($infoblocks) && $contentInfoblockId <= 0) {
                    $contentInfoblockId = (int) $infoblocks[0]['id'];
                }
                $showDeleted = !empty($_GET['show_deleted']);
                $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
                $perPage = 20;
                $previewToken = ensurePreviewToken();
                $sectionPath = $sectionRepo->buildPath((int) $selected['id']);

                echo '<h2 class="h6">Контент</h2>';
                if (empty($infoblocks)) {
                    echo '<div class="alert alert-light border">В этом разделе нет инфоблоков.</div>';
                } else {
                    if (count($infoblocks) > 1) {
                        echo '<ul class="nav nav-tabs mb-3">';
                        foreach ($infoblocks as $infoblockTabItem) {
                            $tabActive = (int) $infoblockTabItem['id'] === $contentInfoblockId ? ' active' : '';
                            $tabUrl = buildAdminUrl([
                                'section_id' => $selectedId,
                                'tab' => 'content',
                                'content_infoblock_id' => (int) $infoblockTabItem['id'],
                            ]);
                            echo '<li class="nav-item"><a class="nav-link' . $tabActive . '" href="' . htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $infoblockTabItem['name'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                        }
                        echo '</ul>';
                    }

                    foreach ($infoblocks as $infoblock) {
                        if ($contentInfoblockId > 0 && (int) $infoblock['id'] !== $contentInfoblockId) {
                            continue;
                        }
                        $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                        $componentName = $component ? (string) $component['name'] : 'Неизвестно';
                        $totalObjects = $objectRepo->countForInfoblock((int) $infoblock['id'], $showDeleted);
                        $totalPages = max(1, (int) ceil($totalObjects / $perPage));
                        if ($page > $totalPages) {
                            $page = 1;
                        }
                        $offset = ($page - 1) * $perPage;
                        $objects = $objectRepo->listForInfoblockPaged((int) $infoblock['id'], $showDeleted, null, $perPage, $offset);
                        $canCreate = Permission::canAction($currentUser, $infoblock, 'create');
                        $canEdit = Permission::canAction($currentUser, $infoblock, 'edit');
                        $canDelete = Permission::canAction($currentUser, $infoblock, 'delete');
                        $canPublish = Permission::canAction($currentUser, $infoblock, 'publish');
                        $canUnpublish = Permission::canAction($currentUser, $infoblock, 'unpublish');
                        $canRestore = Permission::canAction($currentUser, $infoblock, 'restore');
                        $canPurge = Permission::canAction($currentUser, $infoblock, 'purge');

                        echo '<div class="border rounded p-3 mb-4">';
                        echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                        echo '<h3 class="h6 mb-0">' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . ' <span class="text-muted">(' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . ')</span></h3>';
                        if ($canCreate) {
                            $createUrl = buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'infoblock_id' => $infoblock['id']]);
                            echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '">Добавить объект</button>';
                        }
                        $toggleLabel = $showDeleted ? 'Скрыть удаленные' : 'Показать удаленные';
                        $toggleParams = [
                            'section_id' => $selectedId,
                            'tab' => 'content',
                            'content_infoblock_id' => (int) $infoblock['id'],
                            'show_deleted' => $showDeleted ? null : 1,
                        ];
                        echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl($toggleParams), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                        echo '</div>';

                        if (empty($objects)) {
                            echo '<div class="alert alert-light border">Объекты отсутствуют.</div>';
                        } else {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-sm align-middle">';
                            echo '<thead><tr><th>ID</th><th>Заголовок</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
                            foreach ($objects as $object) {
                                $data = json_decode((string) $object['data_json'], true);
                                if (!is_array($data)) {
                                    $data = [];
                                }
                                $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
                                $status = (string) ($object['status'] ?? 'draft');
                                $isDeleted = !empty($object['is_deleted']);
                                $statusLabel = match ($status) {
                                    'published' => 'Опубликован',
                                    'draft' => 'Черновик',
                                    default => $status,
                                };
                                $previewUrl = $sectionPath . '?object_id=' . (int) $object['id'] . '&preview_token=' . urlencode($previewToken);

                                echo '<tr>';
                                echo '<td>' . (int) $object['id'] . '</td>';
                                echo '<td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>';
                                $statusBadge = $isDeleted ? 'Удален' : $statusLabel;
                                echo '<td>' . htmlspecialchars($statusBadge, ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td class="d-flex flex-wrap gap-2">';
                                if ($canEdit && !$isDeleted) {
                                    $editUrl = buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'id' => $object['id']]);
                                    echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Редактировать</button>';
                                }
                                if (!$isDeleted && $status === 'draft') {
                                    if ($canPublish) {
                                        echo '<form method="post" action="/admin.php?action=object_publish">';
                                        echo csrf_token_field();
                                        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                        echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                        echo '<button class="btn btn-sm btn-success" type="submit">Опубликовать</button>';
                                        echo '</form>';
                                    }
                                } elseif (!$isDeleted) {
                                    if ($canUnpublish) {
                                        echo '<form method="post" action="/admin.php?action=object_unpublish">';
                                        echo csrf_token_field();
                                        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                        echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                        echo '<button class="btn btn-sm btn-warning" type="submit">Снять с публикации</button>';
                                        echo '</form>';
                                    }
                                }
                                if (!$isDeleted && Permission::canView($currentUser, $infoblock)) {
                                    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">Предпросмотр</a>';
                                }
                                if ($canDelete && !$isDeleted) {
                                    echo '<form method="post" action="/admin.php?action=object_delete" data-confirm="Удалить объект?">';
                                    echo csrf_token_field();
                                    echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                    echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                                    echo '</form>';
                                }
                                if ($isDeleted) {
                                    if ($canRestore) {
                                        echo '<form method="post" action="/admin.php?action=object_restore">';
                                        echo csrf_token_field();
                                        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                        echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                        echo '<button class="btn btn-sm btn-outline-success" type="submit">Восстановить</button>';
                                        echo '</form>';
                                    }
                                    if ($canPurge) {
                                        echo '<form method="post" action="/admin.php?action=object_purge" data-confirm="Удалить объект окончательно?">';
                                        echo csrf_token_field();
                                        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                        echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                        echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить навсегда</button>';
                                        echo '</form>';
                                    }
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                        echo '</tbody></table></div>';
                    }

                        $ccEnv = [
                            'current_page' => $page,
                            'total_pages' => $totalPages,
                            'base_url' => '/admin.php',
                            'query_params' => [
                                'section_id' => $selectedId,
                                'tab' => 'content',
                                'content_infoblock_id' => (int) $infoblock['id'],
                                'show_deleted' => $showDeleted ? 1 : null,
                            ],
                            'per_page' => $perPage,
                            'total_items' => $totalObjects,
                        ];
                        echo browse_messages($ccEnv, 10);

                        if ($canDelete) {
                            echo '<div class="d-flex justify-content-end mt-3">';
                            echo '<form method="post" action="/admin.php?action=object_delete_all" data-confirm="Вы уверены? Это удалит все объекты.">';
                            echo csrf_token_field();
                            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                            echo '<input type="hidden" name="infoblock_id" value="' . (int) $infoblock['id'] . '">';
                            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить все</button>';
                            echo '</form>';
                            echo '</div>';
                        }

                    echo '</div>';
                }
                }
            } else {
                $maxSort = 0;
                foreach ($infoblocks as $infoblock) {
                    if ((int) $infoblock['sort'] > $maxSort) {
                        $maxSort = (int) $infoblock['sort'];
                    }
                }
                $defaultSort = $maxSort + 10;

                echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                echo '<h2 class="h6 mb-0">Инфоблоки</h2>';
                if ($isAdmin) {
                    $createUrl = buildAdminUrl(['action' => 'infoblock_form', 'section_id' => $selectedId]);
                    echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '">Добавить</button>';
                }
                echo '</div>';
                if (empty($infoblocks)) {
                    echo '<div class="alert alert-light border">Инфоблоков пока нет.</div>';
                } else {
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-sm align-middle">';
                    echo '<thead><tr><th>ID</th><th>Сортировка</th><th>Название</th><th>Компонент</th><th>Шаблон</th><th>Включен</th><th>Действия</th></tr></thead><tbody>';
                    foreach ($infoblocks as $infoblock) {
                        $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                        $componentName = $component ? (string) $component['name'] : 'Неизвестно';
                        echo '<tr>';
                        echo '<td>' . (int) $infoblock['id'] . '</td>';
                        echo '<td>' . (int) $infoblock['sort'] . '</td>';
                        echo '<td>' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars((string) $infoblock['view_template'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . (!empty($infoblock['is_enabled']) ? 'Да' : 'Нет') . '</td>';
                        echo '<td class="d-flex gap-2">';
                        if ($isAdmin) {
                            $editUrl = buildAdminUrl(['action' => 'infoblock_form', 'id' => (int) $infoblock['id'], 'section_id' => $selectedId]);
                            echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Редактировать</button>';
                            echo '<form method="post" action="/admin.php?action=infoblock_delete" data-confirm="Удалить инфоблок?">';
                            echo csrf_token_field();
                            echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                            echo '<input type="hidden" name="name" value="' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '">';
                            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                            echo '</form>';
                        } else {
                            echo '<span class="text-muted">Недоступно</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                }
            }
        }
        }
    } else {
        echo '<div class="text-muted">Выберите сайт или раздел в дереве.</div>';
    }

    echo '</div>';
    echo '</div>';
};

AdminLayout::renderHeader('Админка');

AdminLayout::openSidebar();
echo '<div id="left-sidebar">';
$renderSidebar();
echo '</div>';
AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div id="content">';
$renderContent();
echo '</div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
