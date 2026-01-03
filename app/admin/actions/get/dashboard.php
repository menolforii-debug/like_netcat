<?php

$sites = $sectionRepo->listSitesOnly();
$sections = [];
foreach ($sites as $site) {
    $sections[] = $site;
    $sections = array_merge($sections, collectSections($sectionRepo, (int) $site['id']));
}

$selected = null;
if ($selectedId !== null) {
    $selected = $sectionRepo->findById($selectedId);
}

AdminLayout::renderHeader('Админка');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="d-flex gap-4">';

echo '<div style="width:260px;">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<h2 class="h6 mb-0">Сайты и разделы</h2>';
echo '</div>';
echo '<form method="post" action="/admin.php?action=site_create" class="mb-3">';
echo csrfTokenField();
echo '<button class="btn btn-sm btn-outline-primary w-100" type="submit">+ Добавить сайт</button>';
echo '</form>';
echo SectionTree::render($sections, $selectedId, csrfToken());
echo '</div>';

echo '<div class="flex-grow-1">';
if ($selected === null) {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h1 class="h5">Выберите сайт или раздел в дереве.</h1>';
    echo '</div></div>';
} else {
    $isSite = $selected['parent_id'] === null;
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';

    if ($isSite) {
        $extra = decodeExtra($selected);
        $mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
        $enabled = !empty($extra['site_enabled']);
        $offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';

        echo '<ul class="nav nav-tabs mb-3">';
        echo '<li class="nav-item"><a class="nav-link active" href="#">Настройки</a></li>';
        echo '</ul>';
        echo '<h1 class="h5">Настройки сайта</h1>';
        echo '<form method="post" action="/admin.php?action=site_update">';
        echo csrfTokenField();
        echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
        echo '<div class="mb-3"><label class="form-label">Название сайта</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '"></div>';
        echo '<div class="mb-3"><label class="form-label">Основной домен</label><input class="form-control" type="text" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
        echo '<div class="mb-3"><label class="form-label">Зеркала домена (по одному в строке)</label><textarea class="form-control" name="site_mirrors" rows="3">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        $checked = $enabled ? ' checked' : '';
        echo '<div class="mb-3 form-check">';
        echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1"' . $checked . '>';
        echo '<label class="form-check-label">Сайт включен</label>';
        echo '</div>';
        echo '<div class="mb-3"><label class="form-label">HTML для отключенного сайта</label><textarea class="form-control" name="site_offline_html" rows="4">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
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

            echo '<h1 class="h5">Настройки раздела</h1>';
            echo '<form method="post" action="/admin.php?action=section_update">';
            echo csrfTokenField();
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '" required></div>';
            $isSystemRoot = (int) $selected['parent_id'] === (int) $selected['site_id'] && in_array($selected['english_name'], ['index', '404'], true);
            $englishNameAttributes = $isSystemRoot ? ' disabled' : ' required';
            $englishNameHint = $isSystemRoot ? '<div class="form-text">Системный раздел: English name фиксирован.</div>' : '';
            echo '<div class="mb-3"><label class="form-label">English name (латиница)</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars((string) ($selected['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $englishNameAttributes . '>' . $englishNameHint . '</div>';
            echo '<div class="mb-3"><label class="form-label">Родительский раздел</label><select class="form-select" name="parent_id" required>';
            echo '<option value="">Выберите родителя</option>';
            foreach ($options as $option) {
                if ((int) $option['id'] === (int) $selected['id']) {
                    continue;
                }
                if ((int) $option['site_id'] !== $siteId) {
                    continue;
                }
                $selectedAttr = (int) $selected['parent_id'] === (int) $option['id'] ? ' selected' : '';
                echo '<option value="' . (int) $option['id'] . '"' . $selectedAttr . '>' . htmlspecialchars((string) $option['title'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select></div>';
            $extra = decodeExtra($selected);
            $currentLayout = isset($extra['layout']) ? (string) $extra['layout'] : 'default';
            if (!in_array($currentLayout, ['default', 'home'], true)) {
                $currentLayout = 'default';
            }
            echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<div class="mb-3"><label class="form-label">Шаблон</label><select class="form-select" name="layout">';
            foreach (['default' => 'По умолчанию', 'home' => 'Главная'] as $value => $label) {
                $selectedAttr = $currentLayout === $value ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selectedAttr . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
        } elseif ($tab === 'seo') {
            $extra = decodeExtra($selected);
            echo '<h1 class="h5">SEO</h1>';
            echo '<form method="post" action="/admin.php?action=seo_update">';
            echo csrfTokenField();
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">SEO заголовок</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<div class="mb-3"><label class="form-label">SEO описание</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">SEO ключевые слова</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
            echo '</form>';
        } elseif ($tab === 'infoblocks') {
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

            echo '<h2 class="h6">Инфоблоки</h2>';
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
                    echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => 'infoblocks', 'edit_infoblock_id' => (int) $infoblock['id']]), ENT_QUOTES, 'UTF-8') . '">Редактировать</a>';
                    echo '<form method="post" action="/admin.php?action=infoblock_delete" onsubmit="return confirm(\"Удалить инфоблок?\")">';
                    echo csrfTokenField();
                    echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
                    echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                    echo '<input type="hidden" name="name" value="' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '">';
                    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            $editId = isset($_GET['edit_infoblock_id']) ? (int) $_GET['edit_infoblock_id'] : 0;
            $editInfoblock = null;
            foreach ($infoblocks as $infoblock) {
                if ((int) $infoblock['id'] === $editId) {
                    $editInfoblock = $infoblock;
                    break;
                }
            }

            if ($editInfoblock !== null) {
                $settings = decodeSettings($editInfoblock);
                $extra = decodeExtra($editInfoblock);
                $component = $componentMap[(int) $editInfoblock['component_id']] ?? null;
                $views = $component ? componentViews($component) : ['list'];
                echo '<hr class="my-4">';
                echo '<h3 class="h6">Редактирование инфоблока</h3>';
                echo '<form method="post" action="/admin.php?action=infoblock_update">';
                echo csrfTokenField();
                echo '<input type="hidden" name="id" value="' . (int) $editInfoblock['id'] . '">';
                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="name" value="' . htmlspecialchars((string) $editInfoblock['name'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Шаблон</label><select class="form-select" name="view_template">';
                foreach ($views as $view) {
                    $selectedView = $view === $editInfoblock['view_template'] ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '"' . $selectedView . '>' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                echo '</select></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) $editInfoblock['sort'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '</div>';
                $checked = !empty($editInfoblock['is_enabled']) ? ' checked' : '';
                echo '<div class="mb-3 form-check">';
                echo '<input class="form-check-input" type="checkbox" name="is_enabled" value="1"' . $checked . '>';
                echo '<label class="form-check-label">Включен</label>';
                echo '</div>';
                echo '<div class="mb-3"><label class="form-label">Настройки (JSON)</label><textarea class="form-control" name="settings_json" rows="4">' . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">HTML до</label><textarea class="form-control" name="before_html" rows="3">' . htmlspecialchars((string) ($extra['before_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="col-md-6 mb-3"><label class="form-label">HTML после</label><textarea class="form-control" name="after_html" rows="3">' . htmlspecialchars((string) ($extra['after_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Изображение до</label><input class="form-control" type="text" name="before_image" value="' . htmlspecialchars((string) ($extra['before_image'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Изображение после</label><input class="form-control" type="text" name="after_image" value="' . htmlspecialchars((string) ($extra['after_image'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '</div>';
                echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
                echo '</form>';
            }

            echo '<hr class="my-4">';
            echo '<h3 class="h6">Добавить инфоблок</h3>';
            echo '<form method="post" action="/admin.php?action=infoblock_create">';
            echo csrfTokenField();
            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
            echo '<div class="row">';
            echo '<div class="col-md-4 mb-3"><label class="form-label">Компонент</label><select class="form-select" name="component_id">';
            foreach ($components as $component) {
                echo '<option value="' . (int) $component['id'] . '">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select></div>';
            echo '<div class="col-md-4 mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="name" required></div>';
            echo '<div class="col-md-4 mb-3"><label class="form-label">Шаблон</label><input class="form-control" type="text" name="view_template" value="list"></div>';
            echo '</div>';
            echo '<div class="row">';
            echo '<div class="col-md-3 mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . (int) $defaultSort . '"></div>';
            echo '<div class="col-md-3 mb-3">';
            echo '<label class="form-label">Включен</label>';
            echo '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" checked></div>';
            echo '</div>';
            echo '</div>';
            echo '<button class="btn btn-success" type="submit">Добавить</button>';
            echo '</form>';
        } elseif ($tab === 'content') {
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
                foreach ($infoblocks as $infoblock) {
                    $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                    $componentName = $component ? (string) $component['name'] : 'Неизвестно';
                    $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);

                    echo '<div class="border rounded p-3 mb-4">';
                    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                    echo '<h3 class="h6 mb-0">' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . ' <span class="text-muted">(' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . ')</span></h3>';
                    echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'infoblock_id' => $infoblock['id']]), ENT_QUOTES, 'UTF-8') . '">Добавить объект</a>';
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
                            echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'id' => $object['id']]), ENT_QUOTES, 'UTF-8') . '">Редактировать</a>';
                            if ($status === 'draft') {
                                echo '<form method="post" action="/admin.php?action=object_publish">';
                                echo csrfTokenField();
                                echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                echo '<button class="btn btn-sm btn-success" type="submit">Опубликовать</button>';
                                echo '</form>';
                            } else {
                                echo '<form method="post" action="/admin.php?action=object_unpublish">';
                                echo csrfTokenField();
                                echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                echo '<button class="btn btn-sm btn-warning" type="submit">Снять с публикации</button>';
                                echo '</form>';
                            }
                            echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">Предпросмотр</a>';
                            echo '<form method="post" action="/admin.php?action=object_delete" onsubmit="return confirm(\"Удалить объект?\")">';
                            echo csrfTokenField();
                            echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                            echo '</form>';
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

    echo '</div></div>';
}

echo '</div>';

echo '</div>';

AdminLayout::renderFooter();
