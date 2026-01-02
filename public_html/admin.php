<?php

require __DIR__ . '/../app/bootstrap.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function buildAdminUrl(array $params = []): string
{
    return '/admin.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

function requireLogin(): void
{
    if (!Auth::canEdit()) {
        redirectTo('/admin.php?action=login');
    }
}

function renderAlert(?string $message, string $type = 'info'): void
{
    if ($message === null || $message === '') {
        return;
    }

    $class = $type === 'error' ? 'danger' : $type;
    echo '<div class="alert alert-' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
}

function parseJsonField(string $value, string $errorMessage): array
{
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException($errorMessage);
    }

    return $decoded;
}

function collectSections(SectionRepo $repo, int $parentId): array
{
    $items = [];
    $children = $repo->listChildren($parentId);
    foreach ($children as $child) {
        $items[] = $child;
        $items = array_merge($items, collectSections($repo, (int) $child['id']));
    }

    return $items;
}

function decodeExtra(array $row): array
{
    if (isset($row['extra']) && is_array($row['extra'])) {
        return $row['extra'];
    }

    $decoded = json_decode((string) ($row['extra_json'] ?? '{}'), true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function decodeSettings(array $row): array
{
    $decoded = json_decode((string) ($row['settings_json'] ?? '{}'), true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function componentViews(array $component): array
{
    if (isset($component['views_json'])) {
        $decoded = json_decode((string) $component['views_json'], true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
    }

    return ['list'];
}

if ($action === 'login') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';

        if (Auth::login($login, $pass)) {
            redirectTo('/admin.php');
        }

        $error = 'Неверный логин или пароль';
    }

    Layout::renderDocumentStart('Вход');
    echo '<div class="container py-5" style="max-width: 420px">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h1 class="h4 mb-3">Вход в админку</h1>';
    renderAlert($error, 'error');
    echo '<form method="post" action="/admin.php?action=login">';
    echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
    echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
    echo '<button class="btn btn-primary w-100" type="submit">Войти</button>';
    echo '</form>';
    echo '</div></div></div>';
    Layout::renderDocumentEnd();
    exit;
}

if ($action === 'logout') {
    Auth::logout();
    redirectTo('/admin.php?action=login');
}

requireLogin();
$user = Auth::user();

$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
$errorMessage = isset($_GET['error']) ? (string) $_GET['error'] : '';
$selectedId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : null;
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'settings';

$sectionRepo = new SectionRepo();
$infoblockRepo = new InfoblockRepo();
$componentRepo = new ComponentRepo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'site_create') {
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : 'New Site';
        $siteId = $sectionRepo->createSite($title);
        if ($user) {
            AdminLog::log($user['id'], 'site_create', 'site', $siteId, [
                'title' => $title,
            ]);
        }
        redirectTo(buildAdminUrl(['section_id' => $siteId, 'notice' => 'Site created']));
    }

    if ($action === 'section_create') {
        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        if ($parentId > 0) {
            $parent = $sectionRepo->findById($parentId);
            if ($parent === null) {
                redirectTo(buildAdminUrl(['error' => 'Parent not found']));
            }

            $title = 'New section';
            $englishName = 'section-' . date('YmdHis');
            $sectionId = $sectionRepo->createSection($parentId, (int) $parent['site_id'], $englishName, $title);
            if ($user) {
                AdminLog::log($user['id'], 'section_create', 'section', $sectionId, [
                    'title' => $title,
                    'parent_id' => $parentId,
                ]);
            }
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'notice' => 'Section created']));
        }

        redirectTo(buildAdminUrl(['error' => 'Parent not found']));
    }

    if ($action === 'section_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $section = $sectionRepo->findById($id);
            if ($section === null) {
                redirectTo(buildAdminUrl(['error' => 'Node not found']));
            }

            try {
                $sectionRepo->delete($id);
                $entityType = $section['parent_id'] === null ? 'site' : 'section';
                $actionName = $section['parent_id'] === null ? 'site_delete' : 'section_delete';
                if ($user) {
                    AdminLog::log($user['id'], $actionName, $entityType, $id, [
                        'title' => $section['title'],
                        'parent_id' => $section['parent_id'],
                    ]);
                }
                redirectTo(buildAdminUrl(['notice' => 'Node deleted']));
            } catch (Throwable $e) {
                redirectTo(buildAdminUrl(['section_id' => $selectedId, 'error' => $e->getMessage()]));
            }
        }

        redirectTo(buildAdminUrl(['error' => 'Node not found']));
    }

    if ($action === 'infoblock_create') {
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

        $section = $sectionRepo->findById($sectionId);
        if ($section === null || $componentId === 0 || $name === '') {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Missing required fields']));
        }

        $component = $componentRepo->findById($componentId);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Component not found']));
        }

        if ($viewTemplate === '') {
            $views = componentViews($component);
            $viewTemplate = $views[0] ?? 'list';
        }

        $infoblockId = $infoblockRepo->create([
            'site_id' => $section['site_id'],
            'section_id' => $sectionId,
            'component_id' => $componentId,
            'name' => $name,
            'view_template' => $viewTemplate,
            'settings' => [],
            'extra' => [],
            'sort' => $sort,
            'is_enabled' => $isEnabled,
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'infoblock_create', 'infoblock', $infoblockId, [
                'section_id' => $sectionId,
                'component_id' => $componentId,
                'name' => $name,
                'view_template' => $viewTemplate,
                'sort' => $sort,
                'is_enabled' => $isEnabled,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Infoblock created']));
    }

    if ($action === 'infoblock_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $settingsJson = isset($_POST['settings_json']) ? (string) $_POST['settings_json'] : '{}';
        $beforeHtml = isset($_POST['before_html']) ? (string) $_POST['before_html'] : '';
        $afterHtml = isset($_POST['after_html']) ? (string) $_POST['after_html'] : '';
        $beforeImage = isset($_POST['before_image']) ? (string) $_POST['before_image'] : '';
        $afterImage = isset($_POST['after_image']) ? (string) $_POST['after_image'] : '';

        if ($id === 0 || $name === '') {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Missing required fields']));
        }

        try {
            $settings = parseJsonField($settingsJson, 'settings_json must be valid JSON');
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => $e->getMessage()]));
        }

        $extra = [
            'before_html' => $beforeHtml,
            'after_html' => $afterHtml,
            'before_image' => $beforeImage,
            'after_image' => $afterImage,
        ];

        $infoblockRepo->update($id, [
            'name' => $name,
            'view_template' => $viewTemplate !== '' ? $viewTemplate : 'list',
            'settings' => $settings,
            'extra' => $extra,
            'sort' => $sort,
            'is_enabled' => $isEnabled,
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'infoblock_update', 'infoblock', $id, [
                'name' => $name,
                'view_template' => $viewTemplate,
                'sort' => $sort,
                'is_enabled' => $isEnabled,
                'settings' => $settings,
                'extra' => $extra,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Infoblock updated']));
    }

    if ($action === 'infoblock_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $name = isset($_POST['name']) ? (string) $_POST['name'] : '';
        if ($id === 0) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Infoblock not found']));
        }

        $infoblockRepo->delete($id);

        if ($user) {
            AdminLog::log($user['id'], 'infoblock_delete', 'infoblock', $id, [
                'section_id' => $sectionId,
                'name' => $name,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Infoblock deleted']));
    }
}

$sites = $sectionRepo->listSites();
$sections = [];
foreach ($sites as $site) {
    $sections[] = $site;
    $sections = array_merge($sections, collectSections($sectionRepo, (int) $site['id']));
}

$selected = null;
if ($selectedId !== null) {
    $selected = $sectionRepo->findById($selectedId);
}

AdminLayout::renderHeader('Admin');
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

echo '<div class="d-flex gap-4">';

echo '<div style="width:260px;">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<h2 class="h6 mb-0">Sites & Sections</h2>';
echo '</div>';
echo '<form method="post" action="/admin.php?action=site_create" class="mb-3">';
echo '<button class="btn btn-sm btn-outline-primary w-100" type="submit">+ Add site</button>';
echo '</form>';
echo SectionTree::render($sections, $selectedId);
echo '</div>';

echo '<div class="flex-grow-1">';
if ($selected === null) {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h1 class="h5">Select a site or section from the left tree.</h1>';
    echo '</div></div>';
} else {
    $isSite = $selected['parent_id'] === null;
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';

    if ($isSite) {
        echo '<h1 class="h5">Site settings</h1>';
        echo '<p class="text-muted mb-0">Site settings UI will be implemented later.</p>';
    } else {
        $tabs = [
            'settings' => 'Settings',
            'infoblocks' => 'Infoblocks',
        ];
        echo '<ul class="nav nav-tabs mb-3">';
        foreach ($tabs as $key => $label) {
            $active = $tab === $key ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => $key]), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        echo '</ul>';

        if ($tab === 'infoblocks') {
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

            echo '<h2 class="h6">Infoblocks</h2>';
            if (empty($infoblocks)) {
                echo '<div class="alert alert-light border">No infoblocks yet.</div>';
            } else {
                echo '<div class="table-responsive">';
                echo '<table class="table table-sm align-middle">';
                echo '<thead><tr><th>Sort</th><th>Name</th><th>Component</th><th>View</th><th>Enabled</th><th>Actions</th></tr></thead><tbody>';
                foreach ($infoblocks as $infoblock) {
                    $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                    $componentName = $component ? (string) $component['name'] : 'Unknown';
                    echo '<tr>';
                    echo '<td>' . (int) $infoblock['sort'] . '</td>';
                    echo '<td>' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) $infoblock['view_template'], ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . (!empty($infoblock['is_enabled']) ? 'Yes' : 'No') . '</td>';
                    echo '<td class="d-flex gap-2">';
                    echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $selectedId, 'tab' => 'infoblocks', 'edit_infoblock_id' => (int) $infoblock['id']]), ENT_QUOTES, 'UTF-8') . '">Edit</a>';
                    echo '<form method="post" action="/admin.php?action=infoblock_delete" onsubmit="return confirm(\"Delete infoblock?\")">';
                    echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
                    echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                    echo '<input type="hidden" name="name" value="' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '">';
                    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>';
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
                echo '<h3 class="h6">Edit infoblock</h3>';
                echo '<form method="post" action="/admin.php?action=infoblock_update">';
                echo '<input type="hidden" name="id" value="' . (int) $editInfoblock['id'] . '">';
                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Name</label><input class="form-control" type="text" name="name" value="' . htmlspecialchars((string) $editInfoblock['name'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">View template</label><select class="form-select" name="view_template">';
                foreach ($views as $view) {
                    $selectedView = $view === $editInfoblock['view_template'] ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '"' . $selectedView . '>' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                echo '</select></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Sort</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) $editInfoblock['sort'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '</div>';
                $checked = !empty($editInfoblock['is_enabled']) ? ' checked' : '';
                echo '<div class="mb-3 form-check">';
                echo '<input class="form-check-input" type="checkbox" name="is_enabled" value="1"' . $checked . '>'; 
                echo '<label class="form-check-label">Enabled</label>';
                echo '</div>';
                echo '<div class="mb-3"><label class="form-label">settings_json</label><textarea class="form-control" name="settings_json" rows="4">' . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Before HTML</label><textarea class="form-control" name="before_html" rows="3">' . htmlspecialchars((string) ($extra['before_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="col-md-6 mb-3"><label class="form-label">After HTML</label><textarea class="form-control" name="after_html" rows="3">' . htmlspecialchars((string) ($extra['after_html'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Before image</label><input class="form-control" type="text" name="before_image" value="' . htmlspecialchars((string) ($extra['before_image'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="col-md-6 mb-3"><label class="form-label">After image</label><input class="form-control" type="text" name="after_image" value="' . htmlspecialchars((string) ($extra['after_image'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '</div>';
                echo '<button class="btn btn-primary" type="submit">Save</button>';
                echo '</form>';
            }

            echo '<hr class="my-4">';
            echo '<h3 class="h6">Add infoblock</h3>';
            echo '<form method="post" action="/admin.php?action=infoblock_create">';
            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
            echo '<div class="row">';
            echo '<div class="col-md-4 mb-3"><label class="form-label">Component</label><select class="form-select" name="component_id">';
            foreach ($components as $component) {
                echo '<option value="' . (int) $component['id'] . '">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select></div>';
            echo '<div class="col-md-4 mb-3"><label class="form-label">Name</label><input class="form-control" type="text" name="name" required></div>';
            echo '<div class="col-md-4 mb-3"><label class="form-label">View template</label><input class="form-control" type="text" name="view_template" value="list"></div>';
            echo '</div>';
            echo '<div class="row">';
            echo '<div class="col-md-3 mb-3"><label class="form-label">Sort</label><input class="form-control" type="number" name="sort" value="' . (int) $defaultSort . '"></div>';
            echo '<div class="col-md-3 mb-3">';
            echo '<label class="form-label">Enabled</label>';
            echo '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" checked></div>';
            echo '</div>';
            echo '</div>';
            echo '<button class="btn btn-success" type="submit">Add</button>';
            echo '</form>';
        } else {
            echo '<h1 class="h5">Section settings</h1>';
            echo '<p class="text-muted mb-0">Section editor will be implemented later.</p>';
        }
    }

    echo '</div></div>';
}

echo '</div>';

echo '</div>';

AdminLayout::renderFooter();
