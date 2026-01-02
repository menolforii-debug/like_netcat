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

function parseComponentFields(array $component): array
{
    $decoded = json_decode((string) ($component['fields_json'] ?? '{}'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $fields = $decoded['fields'] ?? $decoded;
    if (!is_array($fields)) {
        return [];
    }

    $normalized = [];
    foreach ($fields as $field) {
        if (is_string($field)) {
            $normalized[] = [
                'name' => $field,
                'type' => 'text',
                'label' => $field,
                'required' => false,
                'options' => [],
            ];
            continue;
        }

        if (!is_array($field) || empty($field['name'])) {
            continue;
        }

        $normalized[] = [
            'name' => (string) $field['name'],
            'type' => isset($field['type']) ? (string) $field['type'] : 'text',
            'label' => isset($field['label']) ? (string) $field['label'] : (string) $field['name'],
            'required' => !empty($field['required']),
            'options' => isset($field['options']) && is_array($field['options']) ? $field['options'] : [],
        ];
    }

    return $normalized;
}

function extractFormData(array $fields): array
{
    $data = [];
    foreach ($fields as $field) {
        $name = $field['name'];
        $type = $field['type'] ?? 'text';
        if ($type === 'checkbox') {
            $data[$name] = isset($_POST[$name]) ? '1' : '0';
            continue;
        }
        if (isset($_POST[$name])) {
            $data[$name] = $_POST[$name];
        }
    }

    return $data;
}

function validateRequiredFields(array $fields, array $data): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (empty($field['required'])) {
            continue;
        }
        $name = $field['name'];
        $value = $data[$name] ?? '';
        if ($value === '' || $value === null) {
            $errors[] = 'Поле "' . $name . '" обязательно.';
        }
    }

    return $errors;
}

function renderFieldInput(array $field, array $data): string
{
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $label = htmlspecialchars((string) ($field['label'] ?? $name), ENT_QUOTES, 'UTF-8');
    $value = isset($data[$name]) ? (string) $data[$name] : '';

    $html = '<label class="form-label">' . $label . '</label>';
    switch ($type) {
        case 'textarea':
            $html .= '<textarea class="form-control" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" rows="4">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
            break;
        case 'number':
            $html .= '<input class="form-control" type="number" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
            break;
        case 'date':
            $html .= '<input class="form-control" type="date" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
            break;
        case 'checkbox':
            $checked = $value !== '' && $value !== '0' ? ' checked' : '';
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input" type="checkbox" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="1"' . $checked . '>';
            $html .= '</div>';
            break;
        case 'select':
            $html .= '<select class="form-select" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
            foreach ($field['options'] ?? [] as $optionValue => $optionLabel) {
                $optionValue = (string) $optionValue;
                $optionLabel = (string) $optionLabel;
                $selected = $optionValue === $value ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select>';
            break;
        default:
            $html .= '<input class="form-control" type="text" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
            break;
    }

    if (!empty($field['required'])) {
        $html .= '<div class="form-text">Обязательное поле</div>';
    }

    return '<div class="mb-3">' . $html . '</div>';
}

function buildSectionPathFromId(SectionRepo $repo, int $sectionId): string
{
    $segments = [];
    $currentId = $sectionId;

    while ($currentId !== null) {
        $section = $repo->findById($currentId);
        if ($section === null) {
            break;
        }

        if (!empty($section['english_name'])) {
            $segments[] = $section['english_name'];
        }

        $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
    }

    if (empty($segments)) {
        return '/';
    }

    return '/' . implode('/', array_reverse($segments)) . '/';
}

function ensurePreviewToken(): string
{
    if (empty($_SESSION['preview_token'])) {
        $_SESSION['preview_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['preview_token'];
}

function parseMirrorLines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value);
    if ($lines === false) {
        return [];
    }

    $mirrors = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $mirrors[] = $line;
        }
    }

    return array_values(array_unique($mirrors));
}

function englishNameIsValid(string $englishName): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $englishName);
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
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'section';

$sectionRepo = new SectionRepo();
$infoblockRepo = new InfoblockRepo();
$componentRepo = new ComponentRepo();
$objectRepo = new ObjectRepo();

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

    if ($action === 'site_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $site = $sectionRepo->findById($id);
        if ($site === null || $site['parent_id'] !== null) {
            redirectTo(buildAdminUrl(['error' => 'Site not found']));
        }

        $before = [
            'title' => $site['title'],
            'extra' => decodeExtra($site),
        ];

        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $siteDomain = isset($_POST['site_domain']) ? trim((string) $_POST['site_domain']) : '';
        $siteMirrorsRaw = isset($_POST['site_mirrors']) ? (string) $_POST['site_mirrors'] : '';
        $siteEnabled = isset($_POST['site_enabled']) ? true : false;
        $offlineHtml = isset($_POST['site_offline_html']) ? (string) $_POST['site_offline_html'] : '';

        $extra = decodeExtra($site);
        $extra['site_domain'] = $siteDomain;
        $extra['site_mirrors'] = parseMirrorLines($siteMirrorsRaw);
        $extra['site_enabled'] = $siteEnabled;
        $extra['site_offline_html'] = $offlineHtml;

        $sectionRepo->update($id, [
            'parent_id' => null,
            'site_id' => $site['site_id'],
            'english_name' => null,
            'title' => $title !== '' ? $title : $site['title'],
            'sort' => $site['sort'] ?? 0,
            'extra' => $extra,
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'site_update', 'site', $id, [
                'before' => $before,
                'after' => [
                    'title' => $title !== '' ? $title : $site['title'],
                    'extra' => $extra,
                ],
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $id, 'notice' => 'Site updated']));
    }

    if ($action === 'section_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $section = $sectionRepo->findById($id);
        if ($section === null || $section['parent_id'] === null) {
            redirectTo(buildAdminUrl(['error' => 'Section not found']));
        }

        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $englishName = isset($_POST['english_name']) ? trim((string) $_POST['english_name']) : '';
        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;

        if ($title === '' || $englishName === '') {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Title and english_name are required']));
        }

        if (!englishNameIsValid($englishName)) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name must be URL-safe']));
        }

        $siteId = (int) $section['site_id'];
        if ($parentId <= 0) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Parent is required']));
        }

        $parent = $sectionRepo->findById($parentId);
        if ($parent === null || (int) $parent['site_id'] !== $siteId) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Parent must belong to the same site']));
        }

        $existing = $sectionRepo->findByEnglishName($siteId, $englishName, $id);
        if ($existing !== null) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'English name must be unique within site']));
        }

        $before = [
            'title' => $section['title'],
            'english_name' => $section['english_name'],
            'parent_id' => $section['parent_id'],
            'sort' => $section['sort'],
        ];

        $sectionRepo->update($id, [
            'parent_id' => $parentId,
            'site_id' => $siteId,
            'english_name' => $englishName,
            'title' => $title,
            'sort' => $sort,
            'extra' => decodeExtra($section),
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'section_update', 'section', $id, [
                'before' => $before,
                'after' => [
                    'title' => $title,
                    'english_name' => $englishName,
                    'parent_id' => $parentId,
                    'sort' => $sort,
                ],
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'notice' => 'Section updated']));
    }

    if ($action === 'seo_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $section = $sectionRepo->findById($id);
        if ($section === null || $section['parent_id'] === null) {
            redirectTo(buildAdminUrl(['error' => 'Section not found']));
        }

        $before = decodeExtra($section);

        $extra = $before;
        $extra['seo_title'] = isset($_POST['seo_title']) ? trim((string) $_POST['seo_title']) : '';
        $extra['seo_description'] = isset($_POST['seo_description']) ? trim((string) $_POST['seo_description']) : '';
        $extra['seo_keywords'] = isset($_POST['seo_keywords']) ? trim((string) $_POST['seo_keywords']) : '';

        $sectionRepo->update($id, [
            'parent_id' => $section['parent_id'],
            'site_id' => $section['site_id'],
            'english_name' => $section['english_name'],
            'title' => $section['title'],
            'sort' => $section['sort'] ?? 0,
            'extra' => $extra,
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'seo_update', 'section', $id, [
                'before' => $before,
                'after' => $extra,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'seo', 'notice' => 'SEO updated']));
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

    if ($action === 'object_create') {
        $infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $saveAs = isset($_POST['save_as']) ? (string) $_POST['save_as'] : 'draft';

        $infoblock = null;
        $infoblocks = $infoblockRepo->listForSection($sectionId);
        foreach ($infoblocks as $row) {
            if ((int) $row['id'] === $infoblockId) {
                $infoblock = $row;
                break;
            }
        }

        if ($infoblock === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Infoblock not found']));
        }

        $component = $componentRepo->findById((int) $infoblock['component_id']);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Component not found']));
        }

        $fields = parseComponentFields($component);
        $data = extractFormData($fields);
        $errors = validateRequiredFields($fields, $data);
        if (!empty($errors)) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => implode(' ', $errors)]));
        }

        $status = $saveAs === 'publish' ? 'published' : 'draft';
        $objectId = $objectRepo->create([
            'site_id' => $infoblock['site_id'],
            'section_id' => $infoblock['section_id'],
            'infoblock_id' => $infoblock['id'],
            'component_id' => $infoblock['component_id'],
            'data' => $data,
            'status' => $status,
        ]);

        if ($user) {
            AdminLog::log($user['id'], 'object_create', 'object', $objectId, [
                'infoblock_id' => $infoblockId,
                'data' => $data,
                'status' => $status,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Object created']));
    }

    if ($action === 'object_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $saveAs = isset($_POST['save_as']) ? (string) $_POST['save_as'] : '';

        $object = $objectRepo->findById($id);
        if ($object === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Object not found']));
        }

        $component = $componentRepo->findById((int) $object['component_id']);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Component not found']));
        }

        $fields = parseComponentFields($component);
        $data = extractFormData($fields);
        $errors = validateRequiredFields($fields, $data);
        if (!empty($errors)) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => implode(' ', $errors)]));
        }

        $objectRepo->update($id, ['data' => $data]);

        if ($saveAs === 'publish') {
            $objectRepo->publish($id);
        } elseif ($saveAs === 'draft') {
            $objectRepo->unpublish($id);
        }

        if ($user) {
            AdminLog::log($user['id'], 'object_update', 'object', $id, [
                'data' => $data,
            ]);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Object updated']));
    }

    if ($action === 'object_publish') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        if ($id > 0) {
            $objectRepo->publish($id);
            if ($user) {
                AdminLog::log($user['id'], 'object_publish', 'object', $id, []);
            }
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Object published']));
    }

    if ($action === 'object_unpublish') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        if ($id > 0) {
            $objectRepo->unpublish($id);
            if ($user) {
                AdminLog::log($user['id'], 'object_unpublish', 'object', $id, []);
            }
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Object unpublished']));
    }

    if ($action === 'object_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        if ($id > 0) {
            $objectRepo->softDelete($id);
            if ($user) {
                AdminLog::log($user['id'], 'object_delete', 'object', $id, []);
            }
        }
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Object deleted']));
    }
}

if ($action === 'object_form') {
    $objectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
    $sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;

    $object = $objectId > 0 ? $objectRepo->findById($objectId) : null;
    if ($object !== null) {
        $infoblockId = (int) $object['infoblock_id'];
    }

    $infoblock = null;
    $infoblocks = $infoblockRepo->listForSection($sectionId);
    foreach ($infoblocks as $row) {
        if ((int) $row['id'] === $infoblockId) {
            $infoblock = $row;
            break;
        }
    }

    if ($infoblock === null) {
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Infoblock not found']));
    }

    $component = $componentRepo->findById((int) $infoblock['component_id']);
    if ($component === null) {
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Component not found']));
    }

    $fields = parseComponentFields($component);
    $data = [];
    if ($object !== null) {
        $data = json_decode((string) $object['data_json'], true);
        if (!is_array($data)) {
            $data = [];
        }
    }

    AdminLayout::renderHeader('Object');
    echo '<div class="container" style="max-width: 900px">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h1 class="h5 mb-0">' . ($object ? 'Edit object' : 'New object') . '</h1>';
    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']), ENT_QUOTES, 'UTF-8') . '">Back</a>';
    echo '</div>';
    echo '<form method="post" action="/admin.php?action=' . ($object ? 'object_update' : 'object_create') . '">';
    if ($object) {
        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
    } else {
        echo '<input type="hidden" name="infoblock_id" value="' . (int) $infoblock['id'] . '">';
    }
    echo '<input type="hidden" name="section_id" value="' . (int) $sectionId . '">';
    foreach ($fields as $field) {
        echo renderFieldInput($field, $data);
    }
    echo '<div class="d-flex gap-2">';
    echo '<button class="btn btn-primary" type="submit" name="save_as" value="draft">Save draft</button>';
    echo '<button class="btn btn-success" type="submit" name="save_as" value="publish">Publish</button>';
    echo '</div>';
    echo '</form>';
    echo '</div></div></div>';
    AdminLayout::renderFooter();
    exit;
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
        $extra = decodeExtra($selected);
        $mirrorsText = isset($extra['site_mirrors']) && is_array($extra['site_mirrors']) ? implode("\n", $extra['site_mirrors']) : '';
        $enabled = !empty($extra['site_enabled']);
        $offlineHtml = isset($extra['site_offline_html']) ? (string) $extra['site_offline_html'] : '';

        echo '<ul class="nav nav-tabs mb-3">';
        echo '<li class="nav-item"><a class="nav-link active" href="#">Settings</a></li>';
        echo '</ul>';
        echo '<h1 class="h5">Site settings</h1>';
        echo '<form method="post" action="/admin.php?action=site_update">';
        echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
        echo '<div class="mb-3"><label class="form-label">Site title</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '"></div>';
        echo '<div class="mb-3"><label class="form-label">Main domain</label><input class="form-control" type="text" name="site_domain" value="' . htmlspecialchars((string) ($extra['site_domain'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
        echo '<div class="mb-3"><label class="form-label">Domain mirrors (one per line)</label><textarea class="form-control" name="site_mirrors" rows="3">' . htmlspecialchars($mirrorsText, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        $checked = $enabled ? ' checked' : '';
        echo '<div class="mb-3 form-check">';
        echo '<input class="form-check-input" type="checkbox" name="site_enabled" value="1"' . $checked . '>';
        echo '<label class="form-check-label">Site enabled</label>';
        echo '</div>';
        echo '<div class="mb-3"><label class="form-label">Offline HTML</label><textarea class="form-control" name="site_offline_html" rows="4">' . htmlspecialchars($offlineHtml, ENT_QUOTES, 'UTF-8') . '</textarea></div>';
        echo '<button class="btn btn-primary" type="submit">Save</button>';
        echo '</form>';
    } else {
        $tabs = [
            'section' => 'Section',
            'seo' => 'SEO',
            'infoblocks' => 'Infoblocks',
            'content' => 'Content',
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

            echo '<h1 class="h5">Section settings</h1>';
            echo '<form method="post" action="/admin.php?action=section_update">';
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">Title</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $selected['title'], ENT_QUOTES, 'UTF-8') . '" required></div>';
            echo '<div class="mb-3"><label class="form-label">English name</label><input class="form-control" type="text" name="english_name" value="' . htmlspecialchars((string) ($selected['english_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
            echo '<div class="mb-3"><label class="form-label">Parent section</label><select class="form-select" name="parent_id" required>';
            echo '<option value="">Select parent</option>';
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
            echo '<div class="mb-3"><label class="form-label">Sort</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) ($selected['sort'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<button class="btn btn-primary" type="submit">Save</button>';
            echo '</form>';
        } elseif ($tab === 'seo') {
            $extra = decodeExtra($selected);
            echo '<h1 class="h5">SEO</h1>';
            echo '<form method="post" action="/admin.php?action=seo_update">';
            echo '<input type="hidden" name="id" value="' . (int) $selected['id'] . '">';
            echo '<div class="mb-3"><label class="form-label">SEO title</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<div class="mb-3"><label class="form-label">SEO description</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
            echo '<div class="mb-3"><label class="form-label">SEO keywords</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
            echo '<button class="btn btn-primary" type="submit">Save</button>';
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
        } elseif ($tab === 'content') {
            $infoblocks = $infoblockRepo->listForSection((int) $selected['id']);
            $components = $componentRepo->listAll();
            $componentMap = [];
            foreach ($components as $component) {
                $componentMap[(int) $component['id']] = $component;
            }
            $previewToken = ensurePreviewToken();
            $sectionPath = buildSectionPathFromId($sectionRepo, (int) $selected['id']);

            echo '<h2 class="h6">Content</h2>';
            if (empty($infoblocks)) {
                echo '<div class="alert alert-light border">No infoblocks in this section.</div>';
            } else {
                foreach ($infoblocks as $infoblock) {
                    $component = $componentMap[(int) $infoblock['component_id']] ?? null;
                    $componentName = $component ? (string) $component['name'] : 'Unknown';
                    $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);

                    echo '<div class="border rounded p-3 mb-4">';
                    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                    echo '<h3 class="h6 mb-0">' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . ' <span class="text-muted">(' . htmlspecialchars($componentName, ENT_QUOTES, 'UTF-8') . ')</span></h3>';
                    echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'infoblock_id' => $infoblock['id']]), ENT_QUOTES, 'UTF-8') . '">Add object</a>';
                    echo '</div>';

                    if (empty($objects)) {
                        echo '<div class="alert alert-light border">Objects отсутствуют.</div>';
                    } else {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-sm align-middle">';
                        echo '<thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                        foreach ($objects as $object) {
                            $data = json_decode((string) $object['data_json'], true);
                            if (!is_array($data)) {
                                $data = [];
                            }
                            $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
                            $status = (string) ($object['status'] ?? 'draft');
                            $previewUrl = $sectionPath . '?object_id=' . (int) $object['id'] . '&preview_token=' . urlencode($previewToken);

                            echo '<tr>';
                            echo '<td>' . (int) $object['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '<td>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '<td class="d-flex flex-wrap gap-2">';
                            echo '<a class="btn btn-sm btn-outline-primary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'object_form', 'section_id' => $selected['id'], 'id' => $object['id']]), ENT_QUOTES, 'UTF-8') . '">Edit</a>';
                            if ($status === 'draft') {
                                echo '<form method="post" action="/admin.php?action=object_publish">';
                                echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                echo '<button class="btn btn-sm btn-success" type="submit">Publish</button>';
                                echo '</form>';
                            } else {
                                echo '<form method="post" action="/admin.php?action=object_unpublish">';
                                echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                                echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                                echo '<button class="btn btn-sm btn-warning" type="submit">Unpublish</button>';
                                echo '</form>';
                            }
                            echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">Preview</a>';
                            echo '<form method="post" action="/admin.php?action=object_delete" onsubmit="return confirm(\"Delete object?\")">';
                            echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $selected['id'] . '">';
                            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>';
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
