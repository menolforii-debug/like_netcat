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

        echo '<ul class="nav nav-tabs mb-3">';
        echo '<li class="nav-item"><a class="nav-link active" href="#">Настройки</a></li>';
        echo '</ul>';
        echo '<div class="d-flex justify-content-between align-items-center mb-3">';
        echo '<h1 class="h5 mb-0">Настройки сайта</h1>';
        if ($isAdmin) {
            $editUrl = buildAdminUrl(['action' => 'site_form', 'id' => (int) $selected['id']]);
            echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Редактировать</a>';
        }
        echo '</div>';
        echo '<dl class="row mb-0">';
        echo '<dt class="col-sm-3">Название</dt><dd class="col-sm-9">' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '</dd>';
        echo '<dt class="col-sm-3">Домен</dt><dd class="col-sm-9">' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '</dd>';
        echo '<dt class="col-sm-3">Зеркала</dt><dd class="col-sm-9"><pre class="mb-0">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</pre></dd>';
        echo '<dt class="col-sm-3">Статус</dt><dd class="col-sm-9">' . ($enabled ? 'Включен' : 'Отключен') . '</dd>';
        echo '<dt class="col-sm-3">Offline HTML</dt><dd class="col-sm-9"><pre class="mb-0">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</pre></dd>';
        echo '</dl>';
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

            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '<h1 class="h5 mb-0">Настройки раздела</h1>';
            if ($isAdmin) {
                $editUrl = buildAdminUrl(['action' => 'section_form', 'id' => (int) $selected['id']]);
                echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Редактировать</a>';
            }
            echo '</div>';
            $extra = decodeExtra($selected);
            $siteExtra = $site ? decodeExtra($site) : [];
            $sectionLayout = isset($extra['layout']) ? (string) $extra['layout'] : '';
            $siteLayout = isset($siteExtra['layout']) ? (string) $siteExtra['layout'] : '';
            $currentLayout = '';
            $layoutNote = '';
            if ($sectionLayout !== '' && Layout::layoutExists($sectionLayout)) {
                $currentLayout = $sectionLayout;
            } elseif ($siteLayout !== '' && Layout::layoutExists($siteLayout)) {
                $currentLayout = $siteLayout;
                $layoutNote = ' (наследуется от сайта)';
            } else {
                $currentLayout = ($selected['parent_id'] ?? null) === null ? 'home' : 'default';
                $layoutNote = ' (по умолчанию)';
            }
            echo '<dl class="row mb-0">';
            echo '<dt class="col-sm-3">Название</dt><dd class="col-sm-9">' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '</dd>';
            echo '<dt class="col-sm-3">English name</dt><dd class="col-sm-9">' . htmlspecialchars((string) ($selected['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</dd>';
            echo '<dt class="col-sm-3">Родитель</dt><dd class="col-sm-9">' . htmlspecialchars((string) ($sectionRepo->findById((int) $selected['parent_id'])['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</dd>';
            echo '<dt class="col-sm-3">Сортировка</dt><dd class="col-sm-9">' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '</dd>';
            echo '<dt class="col-sm-3">Макет дизайна</dt><dd class="col-sm-9">' . htmlspecialchars($currentLayout . $layoutNote, ENT_QUOTES, 'UTF-8') . '</dd>';
            echo '</dl>';
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
                foreach ($infoblocks as $infoblock) {
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
