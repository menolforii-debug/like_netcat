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

$renderSidebar = function () use ($sections, $selectedId, $currentSiteId, $isAdmin): void {
    echo '<div class="card shadow-sm border-0">';
    echo '<div class="card-body p-3">';

    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div class="fw-semibold">Сайты и разделы</div>';
    if ($isAdmin) {
        $createUrl = buildAdminUrl(['action' => 'site_new']);
        echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '" title="Добавить сайт" aria-label="Добавить сайт">';
        echo '<i class="fi fi-plus"></i>';
        echo '</a>';
    }
    echo '</div>';

    echo SectionTree::render($sections, $selectedId, $currentSiteId);

    echo '</div>';
    echo '</div>';
};

$renderContent = function () use ($selected, $selectedId, $tab, $sectionRepo, $infoblockRepo, $componentRepo, $objectRepo, $currentUser, $isAdmin): void {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';

    // Как в “Компонентах”: без большого заголовка справа
    // echo '<h1 class="h4 mb-3">Сайты и разделы</h1>';

    if ($selected !== null) {
        $isSite = $selected['parent_id'] === null;

        if ($isSite) {
        $extra = decodeExtra($selected);
        $mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
        $enabled = !empty($extra['site_enabled']);
        $offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';
        $layouts = Layout::listLayouts();
        $currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
        if ($currentLayout !== '' && !in_array($currentLayout, $layouts, true)) {
            $currentLayout = '';
        }

        $siteTab = isset($_GET['site_tab']) ? (string) $_GET['site_tab'] : 'settings';
        if (!in_array($siteTab, ['settings', 'visual'], true)) {
            $siteTab = 'settings';
        }

        echo '<ul class="nav nav-tabs mb-3">';
        foreach (['settings' => 'Настройки', 'visual' => 'Визуальные настройки'] as $key => $label) {
            $active = $siteTab === $key ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'site_tab' => $key]), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        echo '</ul>';
        echo '<div class="d-flex justify-content-between align-items-center mb-3">';
        echo '<h1 class="h5 mb-0">Настройки сайта</h1>';
        echo '</div>';

        if ($isAdmin) {
            echo '<form method="post" action="/admin.php?action=site_update">';
            echo csrfTokenField();
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';

            if ($siteTab === 'settings') {
                echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($selected['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
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
            } else {
                echo '<input type="hidden" name="title" value="' . htmlspecialchars((string) ($selected['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="site_mirrors" value="' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '">';
                if ($enabled) {
                    echo '<input type="hidden" name="site_enabled" value="1">';
                }
                echo '<input type="hidden" name="site_offline_html" value="' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="layout" value="' . htmlspecialchars($currentLayout, ENT_QUOTES, 'UTF-8') . '">';
            }

            $layoutFields = $currentLayout !== '' ? readLayoutFields($currentLayout) : [];
            $layoutValues = [];
            if (!empty($extra['layout_fields']) && is_array($extra['layout_fields']) && $currentLayout !== '') {
                $valuesForLayout = $extra['layout_fields'][$currentLayout] ?? null;
                if (is_array($valuesForLayout)) {
                    $layoutValues = $valuesForLayout;
                }
            }

            if ($siteTab === 'visual') {
                if ($currentLayout === '') {
                    echo '<div class="alert alert-light border">Выберите макет дизайна во вкладке «Настройки», чтобы заполнить визуальные настройки.</div>';
                } elseif (empty($layoutFields)) {
                    echo '<div class="alert alert-light border">Для выбранного макета нет визуальных полей.</div>';
                } else {
                    echo '<h2 class="h6 mt-4">Визуальные настройки</h2>';
                    echo '<input type="hidden" name="layout_fields_key" value="' . htmlspecialchars($currentLayout, ENT_QUOTES, 'UTF-8') . '">';
                    foreach ($layoutFields as $field) {
                        $name = (string) $field['name'];
                        $label = (string) ($field['label'] ?? $name);
                        $type = (string) ($field['type'] ?? 'text');
                        $value = isset($layoutValues[$name]) ? (string) $layoutValues[$name] : '';
                        echo '<div class="mb-3">';
                        echo '<label class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
                        if ($type === 'textarea') {
                            echo '<textarea class="form-control" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" rows="3">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
                        } elseif ($type === 'select' && !empty($field['options']) && is_array($field['options'])) {
                            echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
                            echo '<option value="">—</option>';
                            foreach ($field['options'] as $option) {
                                if (!is_array($option)) {
                                    continue;
                                }
                                $optKey = (string) ($option['key'] ?? '');
                                $optLabel = (string) ($option['label'] ?? $optKey);
                                if ($optKey === '') {
                                    continue;
                                }
                                $selectedAttr = $optKey === $value ? ' selected' : '';
                                echo '<option value="' . htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            echo '</select>';
                        } elseif ($type === 'checkbox') {
                            echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
                            echo '<option value="">—</option>';
                            $yesSelected = $value === '1' ? ' selected' : '';
                            $noSelected = $value === '0' ? ' selected' : '';
                            echo '<option value="1"' . $yesSelected . '>Да</option>';
                            echo '<option value="0"' . $noSelected . '>Нет</option>';
                            echo '</select>';
                        } else {
                            $inputType = $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text');
                            echo '<input class="form-control" type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                        }
                        echo '</div>';
                    }
                }
            }

            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
        } else {
            echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
        }
        } else {
        $tabs = [
            'section' => 'Раздел',
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
                $options[] = $site;
                $options = array_merge($options, collectSections($sectionRepo, $siteId));
            }

            $sectionTab = isset($_GET['section_tab']) ? (string) $_GET['section_tab'] : 'settings';
            if (!in_array($sectionTab, ['settings', 'visual'], true)) {
                $sectionTab = 'settings';
            }

            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '<h1 class="h5 mb-0">Настройки раздела</h1>';
            echo '</div>';
            echo '<ul class="nav nav-tabs mb-3">';
            foreach (['settings' => 'Настройки', 'visual' => 'Визуальные настройки'] as $key => $label) {
                $active = $sectionTab === $key ? ' active' : '';
                echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => 'section', 'section_tab' => $key]), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
            echo '</ul>';
            $extra = decodeExtra($selected);
            $siteExtra = $site ? decodeExtra($site) : [];
            $sectionLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
            $siteLayout = isset($siteExtra['layout']) ? (string) $siteExtra['layout'] : '';
            $layouts = Layout::listLayouts();
            $currentLayout = '';
            if ($sectionLayout !== '' && Layout::layoutExists($sectionLayout)) {
                $currentLayout = $sectionLayout;
            }
            $layoutForFields = $currentLayout !== '' ? $currentLayout : $siteLayout;

            if ($isAdmin) {
                echo '<form method="post" action="/admin.php?action=section_update">';
                echo csrfTokenField();
                echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';

                $englishNameDisabled = $selected['parent_id'] === null && in_array($selected['english_name'] ?? '', ['index', '404'], true);
                $englishAttrs = $englishNameDisabled ? ' disabled' : ' required';
                $englishHint = $englishNameDisabled ? '<div class="form-text">Системный раздел: English name фиксирован.</div>' : '';
                $currentEnglishName = (string) ($selected['english_name'] ?? '');

                if ($sectionTab === 'settings') {
                    echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) ($selected['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
                    echo '<div class="mb-3"><label class="form-label">English name (латиница)</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars($currentEnglishName, ENT_QUOTES, 'UTF-8') . '"' . $englishAttrs . '>' . $englishHint . '</div>';
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
                        echo '<option value="' . (int) $option['id'] . '"' . $selectedAttr . '>' . htmlspecialchars((string) $option['title'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select></div>';
                    echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
                    echo '<div class="mb-3"><label class="form-label">Макет дизайна</label><select class="form-select" name="layout">';
                    echo '<option value="">Наследовать макет сайта</option>';
                    foreach ($layouts as $layout) {
                        $selectedAttr = $currentLayout === $layout ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select></div>';
                } else {
                    echo '<input type="hidden" name="title" value="' . htmlspecialchars((string) ($selected['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
                    echo '<input type="hidden" name="english_name" value="' . htmlspecialchars($currentEnglishName, ENT_QUOTES, 'UTF-8') . '">';
                    echo '<input type="hidden" name="parent_id" value="' . (int) ($selected['parent_id'] ?? 0) . '">';
                    echo '<input type="hidden" name="sort" value="' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '">';
                    echo '<input type="hidden" name="layout" value="' . htmlspecialchars($currentLayout, ENT_QUOTES, 'UTF-8') . '">';
                }

                $layoutFields = $layoutForFields !== '' ? readLayoutFields($layoutForFields) : [];
                $layoutValues = [];
                if (!empty($extra['layout_fields']) && is_array($extra['layout_fields']) && $layoutForFields !== '') {
                    $valuesForLayout = $extra['layout_fields'][$layoutForFields] ?? null;
                    if (is_array($valuesForLayout)) {
                        $layoutValues = $valuesForLayout;
                    }
                }

                if ($sectionTab === 'visual') {
                    if ($layoutForFields === '') {
                        echo '<div class="alert alert-light border">Выберите макет дизайна во вкладке «Настройки», чтобы заполнить визуальные настройки.</div>';
                    } elseif (empty($layoutFields)) {
                        echo '<div class="alert alert-light border">Для выбранного макета нет визуальных полей.</div>';
                    } else {
                        echo '<h2 class="h6 mt-4">Визуальные настройки</h2>';
                        echo '<input type="hidden" name="layout_fields_key" value="' . htmlspecialchars($layoutForFields, ENT_QUOTES, 'UTF-8') . '">';
                        if ($currentLayout === '' && $siteLayout !== '') {
                            echo '<div class="form-text mb-2">Используется макет сайта: ' . htmlspecialchars($siteLayout, ENT_QUOTES, 'UTF-8') . '.</div>';
                        }
                        foreach ($layoutFields as $field) {
                            $name = (string) $field['name'];
                            $label = (string) ($field['label'] ?? $name);
                            $type = (string) ($field['type'] ?? 'text');
                            $value = isset($layoutValues[$name]) ? (string) $layoutValues[$name] : '';
                            echo '<div class="mb-3">';
                            echo '<label class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
                            if ($type === 'textarea') {
                                echo '<textarea class="form-control" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" rows="3">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
                            } elseif ($type === 'select' && !empty($field['options']) && is_array($field['options'])) {
                                echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
                                echo '<option value="">—</option>';
                                foreach ($field['options'] as $option) {
                                    if (!is_array($option)) {
                                        continue;
                                    }
                                    $optKey = (string) ($option['key'] ?? '');
                                    $optLabel = (string) ($option['label'] ?? $optKey);
                                    if ($optKey === '') {
                                        continue;
                                    }
                                    $selectedAttr = $optKey === $value ? ' selected' : '';
                                    echo '<option value="' . htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                echo '</select>';
                            } elseif ($type === 'checkbox') {
                                echo '<select class="form-select" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']">';
                                echo '<option value="">—</option>';
                                $yesSelected = $value === '1' ? ' selected' : '';
                                $noSelected = $value === '0' ? ' selected' : '';
                                echo '<option value="1"' . $yesSelected . '>Да</option>';
                                echo '<option value="0"' . $noSelected . '>Нет</option>';
                                echo '</select>';
                            } else {
                                $inputType = $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text');
                                echo '<input class="form-control" type="' . htmlspecialchars($inputType, ENT_QUOTES, 'UTF-8') . '" name="layout_fields[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                            }
                            echo '</div>';
                        }
                    }
                }

                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</form>';
            } else {
                echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
            }
        } elseif ($tab === 'seo') {
            $extra = decodeExtra($selected);
            echo '<h1 class="h5">SEO</h1>';
            if ($isAdmin) {
                echo '<form method="post" action="/admin.php?action=seo_update">';
                echo csrfTokenField();
                echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
                echo '<div class="mb-3"><label class="form-label">SEO заголовок</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="mb-3"><label class="form-label">SEO описание</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="mb-3"><label class="form-label">SEO ключевые слова</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</form>';
            } else {
                echo '<div class="alert alert-light border">Редактирование доступно только для администратора.</div>';
            }
        } elseif ($tab === 'infoblocks') {
            // ... оставлено без изменений ...
            // твой исходный код ниже без правок
            $infoblocks = $infoblockRepo->listForSection((int) $selected['id']);
            $components = $componentRepo->listAll();
            $componentMap = [];
            foreach ($components as $component) {
                $componentMap[(int) $component['id']] = $component;
            }

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
                echo '<thead><tr><th>Сортировка</th><th>Название</th><th>Компонент</th><th>Шаблон</th><th>Включен</th><th>Действия</th></tr></thead><tbody>';
                foreach ($infoblocks as $infoblock) {
                    $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                    $componentName = $component ? (string) $component['name'] : 'Неизвестно';
                    echo '<tr>';
                    echo '<td>' . (int) $infoblock['sort'] . '</td>';
                    echo '<td>' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) $infoblock['view_template'], ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . (!empty($infoblock['is_enabled']) ? 'Да' : 'Нет') . '</td>';
                    echo '<td class="d-flex gap-2">';
                    if ($isAdmin) {
                        $editUrl = buildAdminUrl(['action' => 'infoblock_form', 'id' => (int) $infoblock['id'], 'section_id' => $selectedId]);
                        echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Редактировать</button>';
                        echo '<form method="post" action="/admin.php?action=infoblock_delete" data-ajax="true" data-confirm="Удалить инфоблок?">';
                        echo csrfTokenField();
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
        } elseif ($tab === 'content') {
            // ... оставлено без изменений ...
            // твой исходный код ниже без правок
            $infoblocks = $infoblockRepo->listForSection((int) $selected['id']);
            $components = $componentRepo->listAll();
            $componentMap = [];
            foreach ($components as $component) {
                $componentMap[(int) $component['id']] = $component;
            }
            $previewToken = ensurePreviewToken();
            $sectionPath = buildSectionPathFromId($sectionRepo, (int) $selected['id']);

            echo '<h2 class="h6">Контент</h2>';
            if (empty($infoblocks)) {
                echo '<div class="alert alert-light border">В этом разделе нет инфоблоков.</div>';
            } else {
                $activeInfoblockId = isset($_GET['content_infoblock']) ? (int) $_GET['content_infoblock'] : 0;
                $knownIds = array_map(static fn(array $infoblock): int => (int) $infoblock['id'], $infoblocks);
                if ($activeInfoblockId <= 0 || !in_array($activeInfoblockId, $knownIds, true)) {
                    $activeInfoblockId = (int) $infoblocks[0]['id'];
                }

                if (count($infoblocks) > 1) {
                    echo '<ul class="nav nav-tabs mb-3">';
                    foreach ($infoblocks as $infoblock) {
                        $active = (int) $infoblock['id'] === $activeInfoblockId ? ' active' : '';
                        $tabLink = buildAdminUrl([
                            'section_id' => $selectedId,
                            'tab' => 'content',
                            'content_infoblock' => (int) $infoblock['id'],
                        ]);
                        echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars($tabLink, ENT_QUOTES, 'UTF-8') . '">';
                        echo htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8');
                        echo '</a></li>';
                    }
                    echo '</ul>';
                }

                foreach ($infoblocks as $infoblock) {
                    if ((int) $infoblock['id'] !== $activeInfoblockId) {
                        continue;
                    }
                    $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                    $componentName = $component ? (string) $component['name'] : 'Неизвестно';
                    $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);
                    $canCreate = Permission::canAction($currentUser, $infoblock, 'create');
                    $canEdit = Permission::canAction($currentUser, $infoblock, 'edit');
                    $canDelete = Permission::canAction($currentUser, $infoblock, 'delete');
                    $canPublish = Permission::canAction($currentUser, $infoblock, 'publish');
                    $canUnpublish = Permission::canAction($currentUser, $infoblock, 'unpublish');

                    echo '<div class="border rounded p-3 mb-4">';
                    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                    echo '<h3 class="h6 mb-0">' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . ' <span class="text-muted">(' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . ')</span></h3>';
                    if ($canCreate) {
                        $createUrl = buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'infoblock_id' => $infoblock['id']]);
                        echo '<button class="btn btn-sm btn-outline-primary" data-modal-url="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '">Добавить объект</button>';
                    }
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
                            $statusLabel = match ($status) {
                                'published' => 'Опубликован',
                                'draft' => 'Черновик',
                                default => $status,
                            };
                            $previewUrl = $sectionPath . '?object_id=' . (int) $object['id'] . '&preview_token=' . urlencode($previewToken);

                            echo '<tr>';
                            echo '<td>' . (int) $object['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '<td>' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '<td class="d-flex flex-wrap gap-2">';
                            if ($canEdit) {
                                echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'id' => $object['id']]), ENT_QUOTES, 'UTF-8') . '">Редактировать</a>';
                            }
                            if ($status === 'draft') {
                                if ($canPublish) {
                                    echo '<form method="post" action="/admin.php?action=object_publish">';
                                    echo csrfTokenField();
                                    echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                    echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                    echo '<button class="btn btn-sm btn-success" type="submit">Опубликовать</button>';
                                    echo '</form>';
                                }
                            } else {
                                if ($canUnpublish) {
                                    echo '<form method="post" action="/admin.php?action=object_unpublish">';
                                    echo csrfTokenField();
                                    echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                    echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                    echo '<button class="btn btn-sm btn-warning" type="submit">Снять с публикации</button>';
                                    echo '</form>';
                                }
                            }
                            if (Permission::canView($currentUser, $infoblock)) {
                                echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">Предпросмотр</a>';
                            }
                            if ($canDelete) {
                                echo '<form method="post" action="/admin.php?action=object_delete" onsubmit="return confirm(\"Удалить объект?\")">';
                                echo csrfTokenField();
                                echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                                echo '</form>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table></div>';
                    }

                    echo '</div>';
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

$partial = isset($_GET['partial']) ? (string) $_GET['partial'] : '';
if ($partial === 'sidebar') {
    $renderSidebar();
    return;
}
if ($partial === 'content') {
    $renderContent();
    return;
}

AdminLayout::renderHeader('Админка');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

AdminLayout::openSidebar();
echo '<div id="sidebarTree" data-refresh-url="' . htmlspecialchars(buildAdminUrl(['partial' => 'sidebar', 'section_id' => $selectedId]), ENT_QUOTES, 'UTF-8') . '">';
$renderSidebar();
echo '</div>';
AdminLayout::closeSidebar();

AdminLayout::openContent();
echo '<div id="contentPane" data-refresh-url="' . htmlspecialchars(buildAdminUrl(['partial' => 'content', 'section_id' => $selectedId, 'tab' => $tab]), ENT_QUOTES, 'UTF-8') . '">';
$renderContent();
echo '</div>';
AdminLayout::closeContent();

AdminLayout::renderFooter();
