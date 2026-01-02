<?php

require __DIR__ . '/../app/bootstrap.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'dashboard';

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function buildAdminUrl(array $params = []): string
{
    return '/admin.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

function requireEditor(): void
{
    if (!Auth::canEdit()) {
        redirectTo('/admin.php?action=login');
    }
}

function requireAdmin(): void
{
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}

function normalizeInfoblockRow(?array $row): ?array
{
    if ($row === null) {
        return null;
    }

    $extra = json_decode((string) ($row['extra_json'] ?? '{}'), true);
    if (!is_array($extra)) {
        $extra = [];
    }
    $row['extra'] = $extra;

    $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
    if (!is_array($settings)) {
        $settings = [];
    }
    $row['settings'] = $settings;

    $viewTemplate = isset($row['view_template']) ? trim((string) $row['view_template']) : '';
    $row['view_template'] = $viewTemplate !== '' ? $viewTemplate : 'list';

    return $row;
}

function fetchAllSections(): array
{
    return DB::fetchAll('SELECT id, parent_id, slug, title, sort FROM sections ORDER BY sort ASC, id ASC');
}

function fetchSectionById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, parent_id, slug, title, sort, extra_json FROM sections WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
}

function fetchInfoblocksBySection($sectionId): array
{
    $rows = DB::fetchAll(
        'SELECT id, section_id, component_id, name, settings_json, view_template, sort, is_enabled, extra_json
        FROM infoblocks
        WHERE section_id = :section_id
        ORDER BY sort ASC, id ASC',
        ['section_id' => $sectionId]
    );

    foreach ($rows as $index => $row) {
        $rows[$index] = normalizeInfoblockRow($row);
    }

    return $rows;
}

function fetchInfoblockById($id): ?array
{
    $row = DB::fetchOne(
        'SELECT id, section_id, component_id, name, settings_json, view_template, sort, is_enabled, extra_json
        FROM infoblocks
        WHERE id = :id LIMIT 1',
        ['id' => $id]
    );

    return normalizeInfoblockRow($row);
}

function fetchComponents(): array
{
    return DB::fetchAll('SELECT id, name, keyword, fields_json FROM components ORDER BY id ASC');
}

function fetchComponentById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, name, keyword, fields_json FROM components WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
}

function fetchObjectById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, infoblock_id, component_id, data_json, status FROM objects WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
}

function parseComponentFields(array $component): array
{
    $validator = new FieldValidator();

    return $validator->parseFields($component);
}

function extractViewsFromFieldsJson(string $fieldsJson): array
{
    $decoded = json_decode($fieldsJson, true);
    if (!is_array($decoded)) {
        return ['list'];
    }

    $views = $decoded['views'] ?? [];
    if (!is_array($views)) {
        $views = [];
    }

    $normalized = [];
    foreach ($views as $view) {
        if (is_string($view) && $view !== '') {
            $normalized[] = $view;
        }
    }

    if (!in_array('list', $normalized, true)) {
        $normalized[] = 'list';
    }

    return array_values(array_unique($normalized));
}

function renderFieldInput(array $field, array $data): string
{
    $name = $field['name'];
    $type = $field['type'];
    $value = isset($data[$name]) ? (string) $data[$name] : '';
    $label = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $html = '<label class="form-label">' . $label . '</label>';

    if ($type === 'text') {
        $html .= '<textarea class="form-control" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" rows="4">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
    } elseif ($type === 'bool') {
        $checked = $value !== '' && $value !== '0' ? ' checked' : '';
        $html .= '<div class="form-check">';
        $html .= '<input class="form-check-input" type="checkbox" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="1"' . $checked . '>';
        $html .= '</div>';
    } elseif ($type === 'int' || $type === 'float') {
        $step = $type === 'float' ? ' step="0.01"' : '';
        $html .= '<input class="form-control" type="number" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $step . '>';
    } elseif ($type === 'date') {
        $html .= '<input class="form-control" type="date" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    } else {
        $html .= '<input class="form-control" type="text" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    }

    if (!empty($field['required'])) {
        $html .= '<div class="form-text">Обязательное поле</div>';
    }

    return '<div class="mb-3">' . $html . '</div>';
}

function extractFormData(array $fields): array
{
    $data = [];
    foreach ($fields as $field) {
        $name = $field['name'];
        if ($field['type'] === 'bool') {
            $data[$name] = isset($_POST[$name]) ? '1' : '0';
            continue;
        }
        if (isset($_POST[$name])) {
            $data[$name] = $_POST[$name];
        }
    }

    return $data;
}

function requireInfoblockAction(?array $infoblock, string $action): void
{
    $user = Auth::user();
    if ($infoblock === null || !Permission::canAction($user, $infoblock, $action)) {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}

function workflowAllowsAction(?array $user, array $infoblock, string $fromStatus, string $action): bool
{
    if ($user && ($user['role'] ?? null) === 'admin') {
        return true;
    }

    if (!$user || ($user['role'] ?? null) !== 'editor') {
        return false;
    }

    $workflow = [];
    if (isset($infoblock['extra']['workflow']) && is_array($infoblock['extra']['workflow'])) {
        $workflow = $infoblock['extra']['workflow'];
    }

    return Workflow::canTransition($fromStatus, $action, $workflow);
}

function statusBadge(string $status): string
{
    $label = $status === 'draft' ? 'Черновик' : ($status === 'archived' ? 'Архив' : 'Опубликован');
    $class = $status === 'draft' ? 'bg-warning text-dark' : ($status === 'archived' ? 'bg-secondary' : 'bg-success');

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function parseViewsInput(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    if (str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $parts = array_map('trim', explode(',', $raw));
    return array_filter($parts, static fn($item) => $item !== '');
}

function buildSectionPathFromId($sectionId): string
{
    $repo = new SectionRepo();
    $segments = [];
    $currentId = $sectionId;

    while ($currentId !== null) {
        $section = $repo->findById($currentId);
        if ($section === null) {
            break;
        }

        if ($section['slug'] !== '') {
            $segments[] = $section['slug'];
        }

        $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
    }

    if (empty($segments)) {
        return '/';
    }

    return '/' . implode('/', array_reverse($segments)) . '/';
}

function renderAlert(?string $message, string $type = 'info'): void
{
    if ($message === null || $message === '') {
        return;
    }

    $class = $type === 'error' ? 'danger' : $type;
    echo '<div class="alert alert-' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
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

requireEditor();
$user = Auth::user();

$sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'section';
$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
$errorMessage = isset($_GET['error']) ? (string) $_GET['error'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'section_create') {
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        $title = 'Новый раздел';
        $slug = 'section-' . date('YmdHis');

        $stmt = DB::pdo()->prepare('INSERT INTO sections (parent_id, slug, title, sort) VALUES (:parent_id, :slug, :title, :sort)');
        $stmt->execute([
            'parent_id' => $parentId,
            'slug' => $slug,
            'title' => $title,
            'sort' => 0,
        ]);
        $newId = (int) DB::pdo()->lastInsertId();
        redirectTo(buildAdminUrl(['section_id' => $newId, 'tab' => 'section', 'notice' => 'Раздел создан']));
    }

    if ($action === 'section_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $slug = isset($_POST['slug']) ? trim((string) $_POST['slug']) : '';
        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;

        if ($title === '' || $slug === '') {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Заполните название и slug.']));
        }

        if ($parentId === $id) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Нельзя выбрать текущий раздел в качестве родителя.']));
        }

        $stmt = DB::pdo()->prepare('UPDATE sections SET parent_id = :parent_id, slug = :slug, title = :title, sort = :sort WHERE id = :id');
        try {
            $stmt->execute([
                'parent_id' => $parentId,
                'slug' => $slug,
                'title' => $title,
                'sort' => $sort,
                'id' => $id,
            ]);
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Ошибка сохранения: ' . $e->getMessage()]));
        }

        redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'notice' => 'Раздел сохранён']));
    }

    if ($action === 'section_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $children = DB::fetchOne('SELECT 1 FROM sections WHERE parent_id = :id LIMIT 1', ['id' => $id]);
            $infoblock = DB::fetchOne('SELECT 1 FROM infoblocks WHERE section_id = :id LIMIT 1', ['id' => $id]);
            if ($children || $infoblock) {
                redirectTo(buildAdminUrl(['section_id' => $id, 'tab' => 'section', 'error' => 'Нельзя удалить раздел с дочерними разделами или инфоблоками.']));
            }

            DB::pdo()->prepare('DELETE FROM sections WHERE id = :id')->execute(['id' => $id]);
        }
        redirectTo(buildAdminUrl(['notice' => 'Раздел удалён']));
    }

    if ($action === 'infoblock_create') {
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 0;
        $extraJson = isset($_POST['extra_json']) ? (string) $_POST['extra_json'] : '{}';

        if ($name === '' || $componentId === 0) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Укажите компонент и имя инфоблока.']));
        }

        $component = fetchComponentById($componentId);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Компонент не найден.']));
        }

        $views = extractViewsFromFieldsJson((string) $component['fields_json']);
        if (!in_array($viewTemplate, $views, true)) {
            $viewTemplate = 'list';
        }

        $decodedExtra = json_decode((string) $extraJson, true);
        if (!is_array($decodedExtra)) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'extra_json должен быть валидным JSON.']));
        }

        $settings = ['limit' => $limit];

        $stmt = DB::pdo()->prepare(
            'INSERT INTO infoblocks (section_id, component_id, name, settings_json, view_template, sort, is_enabled, extra_json)
            VALUES (:section_id, :component_id, :name, :settings_json, :view_template, :sort, :is_enabled, :extra_json)'
        );
        $stmt->execute([
            'section_id' => $sectionId,
            'component_id' => $componentId,
            'name' => $name,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'view_template' => $viewTemplate,
            'sort' => $sort,
            'is_enabled' => $isEnabled,
            'extra_json' => json_encode($decodedExtra, JSON_UNESCAPED_UNICODE),
        ]);

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок добавлен']));
    }

    if ($action === 'infoblock_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $viewTemplate = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
        $sort = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 0;
        $extraJson = isset($_POST['extra_json']) ? (string) $_POST['extra_json'] : '{}';

        $infoblock = fetchInfoblockById($id);
        if ($infoblock === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'Инфоблок не найден.']));
        }
        requireInfoblockAction($infoblock, 'edit');

        $component = fetchComponentById((int) $infoblock['component_id']);
        $views = $component ? extractViewsFromFieldsJson((string) $component['fields_json']) : ['list'];
        if (!in_array($viewTemplate, $views, true)) {
            $viewTemplate = 'list';
        }

        $decodedExtra = json_decode((string) $extraJson, true);
        if (!is_array($decodedExtra)) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'error' => 'extra_json должен быть валидным JSON.']));
        }

        $settings = ['limit' => $limit];

        $stmt = DB::pdo()->prepare(
            'UPDATE infoblocks SET name = :name, settings_json = :settings_json, view_template = :view_template, sort = :sort, is_enabled = :is_enabled, extra_json = :extra_json
            WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'view_template' => $viewTemplate,
            'sort' => $sort,
            'is_enabled' => $isEnabled,
            'extra_json' => json_encode($decodedExtra, JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок обновлён']));
    }

    if ($action === 'infoblock_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $infoblock = fetchInfoblockById($id);
        if ($infoblock !== null) {
            requireInfoblockAction($infoblock, 'delete');
        }

        DB::pdo()->prepare('DELETE FROM infoblocks WHERE id = :id')->execute(['id' => $id]);
        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'infoblocks', 'notice' => 'Инфоблок удалён']));
    }

    if ($action === 'seo_update') {
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $section = fetchSectionById($sectionId);
        if ($section === null) {
            redirectTo(buildAdminUrl(['error' => 'Раздел не найден.']));
        }

        $extra = json_decode((string) ($section['extra_json'] ?? '{}'), true);
        if (!is_array($extra)) {
            $extra = [];
        }

        $extra['seo_title'] = isset($_POST['seo_title']) ? trim((string) $_POST['seo_title']) : '';
        $extra['seo_description'] = isset($_POST['seo_description']) ? trim((string) $_POST['seo_description']) : '';
        $extra['seo_keywords'] = isset($_POST['seo_keywords']) ? trim((string) $_POST['seo_keywords']) : '';

        $stmt = DB::pdo()->prepare('UPDATE sections SET extra_json = :extra_json WHERE id = :id');
        $stmt->execute([
            'extra_json' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'id' => $sectionId,
        ]);

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'seo', 'notice' => 'SEO сохранено']));
    }

    if ($action === 'object_create') {
        $infoblockId = isset($_POST['infoblock_id']) ? (int) $_POST['infoblock_id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
        $status = isset($_POST['save_as']) && $_POST['save_as'] === 'publish' ? 'published' : 'draft';

        $infoblock = fetchInfoblockById($infoblockId);
        if ($infoblock === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Инфоблок не найден.']));
        }
        requireInfoblockAction($infoblock, 'create');

        $component = fetchComponentById((int) $infoblock['component_id']);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден.']));
        }

        $fields = parseComponentFields($component);
        $data = extractFormData($fields);

        try {
            $repo = new ObjectRepo(core()->events());
            $repo->insert($component, (int) $infoblock['section_id'], (int) $infoblock['id'], $data, $status);
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект создан']));
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
        }
    }

    if ($action === 'object_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $object = fetchObjectById($id);
        if ($object === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Объект не найден.']));
        }

        $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
        requireInfoblockAction($infoblock, 'edit');

        $component = fetchComponentById((int) $object['component_id']);
        if ($component === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Компонент не найден.']));
        }

        $fields = parseComponentFields($component);
        $data = extractFormData($fields);

        try {
            $repo = new ObjectRepo(core()->events());
            $repo->update($component, $id, $data);
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект сохранён']));
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
        }
    }

    if ($action === 'object_publish' || $action === 'object_unpublish' || $action === 'object_archive') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $object = fetchObjectById($id);
        if ($object === null) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => 'Объект не найден.']));
        }

        $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
        $actionName = $action === 'object_publish' ? 'publish' : ($action === 'object_unpublish' ? 'unpublish' : 'archive');
        requireInfoblockAction($infoblock, $actionName);

        try {
            $repo = new ObjectRepo(core()->events());
            if ($action === 'object_publish') {
                $repo->publish($id);
            } elseif ($action === 'object_unpublish') {
                $repo->unpublish($id);
            } else {
                $repo->archive($id);
            }
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Статус обновлён']));
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'error' => $e->getMessage()]));
        }
    }

    if ($action === 'object_delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $object = fetchObjectById($id);
        if ($object !== null) {
            $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
            requireInfoblockAction($infoblock, 'delete');
            $repo = new ObjectRepo(core()->events());
            $repo->softDelete($id);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект удалён']));
    }

    if ($action === 'object_restore') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $object = fetchObjectById($id);
        if ($object !== null) {
            $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
            requireInfoblockAction($infoblock, 'restore');
            $repo = new ObjectRepo(core()->events());
            $repo->restore($id);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект восстановлен']));
    }

    if ($action === 'object_purge') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

        $object = fetchObjectById($id);
        if ($object !== null) {
            $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
            requireInfoblockAction($infoblock, 'purge');
            $repo = new ObjectRepo(core()->events());
            $repo->purge($id);
        }

        redirectTo(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content', 'notice' => 'Объект удалён навсегда']));
    }

    if ($action === 'component_create') {
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
        $fieldsJson = isset($_POST['fields_json']) ? trim((string) $_POST['fields_json']) : '';
        $viewsRaw = isset($_POST['views']) ? (string) $_POST['views'] : '';

        if ($name === '' || $keyword === '' || $fieldsJson === '') {
            redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Заполните все поля.']));
        }

        $decoded = json_decode($fieldsJson, true);
        if (!is_array($decoded)) {
            redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'fields_json должен быть валидным JSON.']));
        }

        $views = parseViewsInput($viewsRaw);
        if (!empty($views)) {
            $decoded['views'] = array_values($views);
        }

        $stmt = DB::pdo()->prepare('INSERT INTO components (name, keyword, fields_json) VALUES (:name, :keyword, :fields_json)');
        try {
            $stmt->execute([
                'name' => $name,
                'keyword' => $keyword,
                'fields_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['action' => 'components', 'error' => 'Ошибка сохранения: ' . $e->getMessage()]));
        }

        redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент создан']));
    }

    if ($action === 'component_update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
        $fieldsJson = isset($_POST['fields_json']) ? trim((string) $_POST['fields_json']) : '';
        $viewsRaw = isset($_POST['views']) ? (string) $_POST['views'] : '';

        if ($name === '' || $keyword === '' || $fieldsJson === '') {
            redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $id, 'error' => 'Заполните все поля.']));
        }

        $decoded = json_decode($fieldsJson, true);
        if (!is_array($decoded)) {
            redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $id, 'error' => 'fields_json должен быть валидным JSON.']));
        }

        $views = parseViewsInput($viewsRaw);
        if (!empty($views)) {
            $decoded['views'] = array_values($views);
        }

        $stmt = DB::pdo()->prepare('UPDATE components SET name = :name, keyword = :keyword, fields_json = :fields_json WHERE id = :id');
        try {
            $stmt->execute([
                'name' => $name,
                'keyword' => $keyword,
                'fields_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'id' => $id,
            ]);
        } catch (Throwable $e) {
            redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => $id, 'error' => 'Ошибка сохранения: ' . $e->getMessage()]));
        }

        redirectTo(buildAdminUrl(['action' => 'components', 'notice' => 'Компонент обновлён']));
    }

    if ($action === 'user_create') {
        requireAdmin();
        $login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';
        $role = isset($_POST['role']) ? (string) $_POST['role'] : 'editor';

        if ($login === '' || $pass === '' || !in_array($role, ['admin', 'editor'], true)) {
            redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Заполните поля корректно.']));
        }

        $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash, role) VALUES (:login, :pass_hash, :role)');
        $stmt->execute([
            'login' => $login,
            'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пользователь создан']));
    }

    if ($action === 'user_update_role') {
        requireAdmin();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $role = isset($_POST['role']) ? (string) $_POST['role'] : 'editor';
        if (!in_array($role, ['admin', 'editor'], true)) {
            redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Некорректная роль.']));
        }

        DB::pdo()->prepare('UPDATE users SET role = :role WHERE id = :id')->execute([
            'role' => $role,
            'id' => $id,
        ]);

        redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Роль обновлена']));
    }

    if ($action === 'user_reset_password') {
        requireAdmin();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';
        if ($pass === '') {
            redirectTo(buildAdminUrl(['action' => 'users', 'error' => 'Пароль не задан.']));
        }

        DB::pdo()->prepare('UPDATE users SET pass_hash = :pass_hash WHERE id = :id')->execute([
            'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'id' => $id,
        ]);

        redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пароль сброшен']));
    }

    if ($action === 'user_delete') {
        requireAdmin();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        DB::pdo()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        redirectTo(buildAdminUrl(['action' => 'users', 'notice' => 'Пользователь удалён']));
    }
}

$navLinks = [
    ['label' => 'Разделы', 'href' => '/admin.php', 'active' => $action === 'dashboard'],
    ['label' => 'Компоненты', 'href' => '/admin.php?action=components', 'active' => $action === 'components'],
    ['label' => 'Пользователи', 'href' => '/admin.php?action=users', 'active' => $action === 'users'],
    ['label' => 'На сайт', 'href' => '/', 'active' => false],
];

AdminLayout::renderHeader('Админка', $user ?? [], $navLinks);
renderAlert($notice, 'success');
renderAlert($errorMessage, 'error');

if ($action === 'components') {
    $components = fetchComponents();
    $editId = isset($_GET['component_id']) ? (int) $_GET['component_id'] : 0;
    $editComponent = $editId > 0 ? fetchComponentById($editId) : null;
    $editViews = $editComponent ? extractViewsFromFieldsJson((string) $editComponent['fields_json']) : [];

    echo '<div class="row">';
    echo '<div class="col-lg-5">';
    echo '<div class="card shadow-sm mb-4">';
    echo '<div class="card-body">';
    echo '<h2 class="h5">' . ($editComponent ? 'Редактировать компонент' : 'Новый компонент') . '</h2>';
    $actionUrl = $editComponent ? '/admin.php?action=component_update' : '/admin.php?action=component_create';
    echo '<form method="post" action="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '">';
    if ($editComponent) {
        echo '<input type="hidden" name="id" value="' . (int) $editComponent['id'] . '">';
    }
    echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="name" value="' . htmlspecialchars((string) ($editComponent['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">Ключевое слово</label><input class="form-control" type="text" name="keyword" value="' . htmlspecialchars((string) ($editComponent['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">fields_json</label><textarea class="form-control" name="fields_json" rows="6" required>' . htmlspecialchars((string) ($editComponent['fields_json'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
    echo '<div class="mb-3"><label class="form-label">Views (json или через запятую)</label><input class="form-control" type="text" name="views" value="' . htmlspecialchars(implode(', ', $editViews), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<button class="btn btn-primary" type="submit">' . ($editComponent ? 'Сохранить' : 'Создать') . '</button>';
    echo '</form>';
    echo '</div></div></div>';

    echo '<div class="col-lg-7">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h2 class="h5">Компоненты</h2>';
    if (empty($components)) {
        echo '<div class="alert alert-light border">Компоненты не найдены.</div>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm align-middle">';
        echo '<thead><tr><th>ID</th><th>Название</th><th>Keyword</th><th></th></tr></thead><tbody>';
        foreach ($components as $component) {
            echo '<tr>';
            echo '<td>' . (int) $component['id'] . '</td>';
            echo '<td>' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $component['keyword'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><a class="btn btn-sm btn-outline-primary" href="/admin.php?action=components&component_id=' . (int) $component['id'] . '">Редактировать</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div></div></div>';
    echo '</div>';

    AdminLayout::renderFooter();
    exit;
}

if ($action === 'users') {
    requireAdmin();

    $users = DB::fetchAll('SELECT id, login, role FROM users ORDER BY id ASC');

    echo '<div class="row">';
    echo '<div class="col-lg-4">';
    echo '<div class="card shadow-sm mb-4">';
    echo '<div class="card-body">';
    echo '<h2 class="h5">Новый пользователь</h2>';
    echo '<form method="post" action="/admin.php?action=user_create">';
    echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
    echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
    echo '<div class="mb-3"><label class="form-label">Роль</label><select class="form-select" name="role">';
    echo '<option value="editor">editor</option>';
    echo '<option value="admin">admin</option>';
    echo '</select></div>';
    echo '<button class="btn btn-primary" type="submit">Создать</button>';
    echo '</form>';
    echo '</div></div></div>';

    echo '<div class="col-lg-8">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h2 class="h5">Пользователи</h2>';
    if (empty($users)) {
        echo '<div class="alert alert-light border">Пользователи не найдены.</div>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm align-middle">';
        echo '<thead><tr><th>ID</th><th>Логин</th><th>Роль</th><th>Действия</th></tr></thead><tbody>';
        foreach ($users as $row) {
            echo '<tr>';
            echo '<td>' . (int) $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['login'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['role'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>';
            echo '<form class="d-inline-flex gap-2" method="post" action="/admin.php?action=user_update_role">';
            echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
            echo '<select class="form-select form-select-sm" name="role">';
            $selectedAdmin = $row['role'] === 'admin' ? ' selected' : '';
            $selectedEditor = $row['role'] === 'editor' ? ' selected' : '';
            echo '<option value="editor"' . $selectedEditor . '>editor</option>';
            echo '<option value="admin"' . $selectedAdmin . '>admin</option>';
            echo '</select>';
            echo '<button class="btn btn-sm btn-outline-primary" type="submit">Сохранить роль</button>';
            echo '</form>';
            echo '<form class="d-inline-flex gap-2 ms-2" method="post" action="/admin.php?action=user_reset_password">';
            echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
            echo '<input class="form-control form-control-sm" type="text" name="pass" placeholder="Новый пароль">';
            echo '<button class="btn btn-sm btn-outline-secondary" type="submit">Сбросить пароль</button>';
            echo '</form>';
            echo '<form class="d-inline ms-2" method="post" action="/admin.php?action=user_delete" onsubmit="return confirm(\"Удалить пользователя?\")">';
            echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div></div></div>';
    echo '</div>';

    AdminLayout::renderFooter();
    exit;
}

if ($action === 'object_form') {
    $objectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
    $sectionId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0;

    $object = $objectId > 0 ? fetchObjectById($objectId) : null;
    if ($object !== null) {
        $infoblockId = (int) $object['infoblock_id'];
    }

    $infoblock = $infoblockId > 0 ? fetchInfoblockById($infoblockId) : null;
    if ($infoblock === null) {
        renderAlert('Инфоблок не найден.', 'error');
        AdminLayout::renderFooter();
        exit;
    }

    $sectionId = $sectionId > 0 ? $sectionId : (int) $infoblock['section_id'];
    $section = fetchSectionById($sectionId);

    if ($object) {
        requireInfoblockAction($infoblock, 'edit');
    } else {
        requireInfoblockAction($infoblock, 'create');
    }

    $component = fetchComponentById((int) $infoblock['component_id']);
    if ($component === null) {
        renderAlert('Компонент не найден.', 'error');
        AdminLayout::renderFooter();
        exit;
    }

    $fields = parseComponentFields($component);
    $data = $object ? json_decode((string) $object['data_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }

    echo '<div class="row">'; 
    echo '<div class="col-lg-8">'; 
    echo '<div class="card shadow-sm">'; 
    echo '<div class="card-body">'; 
    echo '<div class="d-flex justify-content-between align-items-center mb-3">'; 
    echo '<h1 class="h5 mb-0">' . ($object ? 'Редактировать объект' : 'Новый объект') . '</h1>'; 
    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => 'content']), ENT_QUOTES, 'UTF-8') . '">Назад</a>'; 
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
    if ($object) { 
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>'; 
    } else { 
        echo '<button class="btn btn-success" type="submit" name="save_as" value="publish">Опубликовать</button>'; 
        echo '<button class="btn btn-outline-secondary" type="submit" name="save_as" value="draft">Сохранить черновик</button>'; 
    } 
    echo '</div>'; 
    echo '</form>'; 
    echo '</div></div></div>'; 
    echo '</div>'; 

    AdminLayout::renderFooter();
    exit;
}

$sections = fetchAllSections();
$section = $sectionId > 0 ? fetchSectionById($sectionId) : null;
$infoblocks = $section ? fetchInfoblocksBySection((int) $section['id']) : [];
$components = fetchComponents();

echo '<div class="row">';

// Left column: tree
echo '<div class="col-lg-3">';
echo '<div class="card shadow-sm mb-3">';
echo '<div class="card-body">';
echo '<h2 class="h6 mb-3">Разделы</h2>';
if (!empty($sections)) {
    echo SectionTree::render($sections, $sectionId > 0 ? $sectionId : null);
}

echo '<div class="d-grid gap-2 mt-3">';
$parentId = $sectionId > 0 ? $sectionId : '';
echo '<form method="post" action="/admin.php?action=section_create">';
if ($parentId !== '') {
    echo '<input type="hidden" name="parent_id" value="' . (int) $parentId . '">';
}
echo '<button class="btn btn-sm btn-primary" type="submit">+ Раздел</button>';
echo '</form>';
if ($section) {
    echo '<form method="post" action="/admin.php?action=section_delete" onsubmit="return confirm(\"Удалить раздел?\")">';
    echo '<input type="hidden" name="id" value="' . (int) $section['id'] . '">';
    echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить раздел</button>';
    echo '</form>';
}
echo '</div>';

echo '</div></div>';
echo '</div>';

// Right column: tabs
echo '<div class="col-lg-9">';
if (!$section) {
    echo '<div class="card shadow-sm">';
    echo '<div class="card-body">';
    echo '<h1 class="h5">Выберите раздел в левом меню</h1>';
    echo '<p class="text-muted mb-0">Для редактирования параметров раздела выберите его в дереве.</p>';
    echo '</div></div>';
    echo '</div></div>';
    AdminLayout::renderFooter();
    exit;
}

$tabs = [
    'section' => 'Раздел',
    'infoblocks' => 'Инфоблоки',
    'seo' => 'SEO',
    'content' => 'Контент',
];

echo '<ul class="nav nav-tabs mb-3">';
foreach ($tabs as $key => $label) {
    $active = $tab === $key ? ' active' : '';
    echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars(buildAdminUrl(['section_id' => $sectionId, 'tab' => $key]), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
}
echo '</ul>';

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';

if ($tab === 'section') {
    echo '<h2 class="h5 mb-3">Настройки раздела</h2>';
    echo '<form method="post" action="/admin.php?action=section_update">';
    echo '<input type="hidden" name="id" value="' . (int) $section['id'] . '">';
    echo '<div class="mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="title" value="' . htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8') . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">Slug</label><input class="form-control" type="text" name="slug" value="' . htmlspecialchars((string) $section['slug'], ENT_QUOTES, 'UTF-8') . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">Родитель</label><select class="form-select" name="parent_id">';
    echo '<option value="">(корень)</option>';
    foreach ($sections as $row) {
        if ((int) $row['id'] === (int) $section['id']) {
            continue;
        }
        $selected = $section['parent_id'] !== null && (int) $section['parent_id'] === (int) $row['id'] ? ' selected' : '';
        echo '<option value="' . (int) $row['id'] . '"' . $selected . '>' . htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
    echo '<div class="mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) $section['sort'], ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
}

if ($tab === 'infoblocks') {
    echo '<h2 class="h5 mb-3">Инфоблоки</h2>';
    if (empty($infoblocks)) {
        echo '<div class="alert alert-light border">Инфоблоки не найдены.</div>';
    } else {
        foreach ($infoblocks as $infoblock) {
            $component = fetchComponentById((int) $infoblock['component_id']);
            $views = $component ? extractViewsFromFieldsJson((string) $component['fields_json']) : ['list'];
            $canEdit = Permission::canAction($user, $infoblock, 'edit');
            $canDelete = Permission::canAction($user, $infoblock, 'delete');

            echo '<div class="border rounded p-3 mb-3">';
            if (!$canEdit) {
                echo '<div class="alert alert-warning">Нет прав на редактирование инфоблока.</div>';
            } else {
                echo '<form method="post" action="/admin.php?action=infoblock_update">';
                echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
                echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                echo '<div class="row">';
                echo '<div class="col-md-6 mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="name" value="' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Шаблон</label><select class="form-select" name="view_template">';
                foreach ($views as $view) {
                    $selected = $view === $infoblock['view_template'] ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                echo '</select></div>';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="' . htmlspecialchars((string) $infoblock['sort'], ENT_QUOTES, 'UTF-8') . '"></div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Лимит</label><input class="form-control" type="number" name="limit" value="' . htmlspecialchars((string) ($infoblock['settings']['limit'] ?? 0), ENT_QUOTES, 'UTF-8') . '"></div>';
                $checked = !empty($infoblock['is_enabled']) ? ' checked' : '';
                echo '<div class="col-md-3 mb-3"><label class="form-label">Включён</label><div class="form-check mt-2">';
                echo '<input class="form-check-input" type="checkbox" name="is_enabled" value="1"' . $checked . '> ';
                echo '<label class="form-check-label">Да</label></div></div>';
                echo '</div>';
                echo '<div class="mb-3"><label class="form-label">extra_json (permissions/workflow)</label><textarea class="form-control" name="extra_json" rows="4">' . htmlspecialchars(json_encode($infoblock['extra'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
                echo '<div class="d-flex gap-2">';
                echo '<button class="btn btn-primary btn-sm" type="submit">Сохранить</button>';
                echo '</form>';
                if ($canDelete) {
                    echo '<form method="post" action="/admin.php?action=infoblock_delete" onsubmit="return confirm(\"Удалить инфоблок?\")">';
                    echo '<input type="hidden" name="id" value="' . (int) $infoblock['id'] . '">';
                    echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                    echo '<button class="btn btn-outline-danger btn-sm" type="submit">Удалить</button>';
                    echo '</form>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }

    echo '<hr class="my-4">';
    echo '<h3 class="h6">Добавить инфоблок</h3>';
    echo '<form method="post" action="/admin.php?action=infoblock_create" id="infoblockCreateForm">';
    echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
    echo '<div class="row">';
    echo '<div class="col-md-4 mb-3"><label class="form-label">Компонент</label><select class="form-select" name="component_id" data-view-selector="component" required>';
    foreach ($components as $component) {
        $views = extractViewsFromFieldsJson((string) $component['fields_json']);
        echo '<option value="' . (int) $component['id'] . '" data-views="' . htmlspecialchars(json_encode($views), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $component['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
    echo '<div class="col-md-4 mb-3"><label class="form-label">Название</label><input class="form-control" type="text" name="name" required></div>';
    echo '<div class="col-md-4 mb-3"><label class="form-label">Шаблон</label><select class="form-select" name="view_template" data-view-selector="view"></select></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="col-md-3 mb-3"><label class="form-label">Сортировка</label><input class="form-control" type="number" name="sort" value="0"></div>';
    echo '<div class="col-md-3 mb-3"><label class="form-label">Лимит</label><input class="form-control" type="number" name="limit" value="0"></div>';
    echo '<div class="col-md-3 mb-3"><label class="form-label">Включён</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" checked></div></div>';
    echo '</div>';
    echo '<div class="mb-3"><label class="form-label">extra_json (permissions/workflow)</label><textarea class="form-control" name="extra_json" rows="4">{}</textarea></div>';
    echo '<button class="btn btn-success" type="submit">Добавить</button>';
    echo '</form>';
}

if ($tab === 'seo') {
    $extra = json_decode((string) ($section['extra_json'] ?? '{}'), true);
    if (!is_array($extra)) {
        $extra = [];
    }

    echo '<h2 class="h5 mb-3">SEO раздела</h2>';
    echo '<form method="post" action="/admin.php?action=seo_update">';
    echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
    echo '<div class="mb-3"><label class="form-label">SEO title</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<div class="mb-3"><label class="form-label">SEO description</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
    echo '<div class="mb-3"><label class="form-label">SEO keywords</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
}

if ($tab === 'content') {
    echo '<h2 class="h5 mb-3">Контент</h2>';
    if (empty($infoblocks)) {
        echo '<div class="alert alert-light border">Нет инфоблоков для этого раздела.</div>';
    } else {
        $repo = new ObjectRepo(core()->events());
        foreach ($infoblocks as $infoblock) {
            $component = fetchComponentById((int) $infoblock['component_id']);
            $objects = $repo->listForInfoblockEdit((int) $infoblock['id']);
            $userRef = Auth::user();

            echo '<div class="border rounded p-3 mb-4">';
            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '<h3 class="h6 mb-0">' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</h3>';
            if (Permission::canAction($userRef, $infoblock, 'create')) {
                echo '<a class="btn btn-sm btn-outline-primary" href="/admin.php?action=object_form&section_id=' . (int) $section['id'] . '&infoblock_id=' . (int) $infoblock['id'] . '">Добавить объект</a>';
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
                    $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
                    $status = (string) ($object['status'] ?? 'draft');
                    $sectionPath = buildSectionPathFromId((int) $section['id']);
                    $previewUrl = $sectionPath . '?preview=1&object_id=' . (int) $object['id'] . '&edit=1';
                    $canEdit = Permission::canAction($userRef, $infoblock, 'edit');
                    $canDelete = Permission::canAction($userRef, $infoblock, 'delete');
                    $canPublish = Permission::canAction($userRef, $infoblock, 'publish');
                    $canUnpublish = Permission::canAction($userRef, $infoblock, 'unpublish');
                    $canArchive = Permission::canAction($userRef, $infoblock, 'archive');

                    echo '<tr>';
                    echo '<td>' . (int) $object['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . statusBadge($status) . '</td>';
                    echo '<td class="d-flex flex-wrap gap-2">';
                    if ($canEdit) {
                        echo '<a class="btn btn-sm btn-outline-primary" href="/admin.php?action=object_form&section_id=' . (int) $section['id'] . '&id=' . (int) $object['id'] . '">Редактировать</a>';
                    }
                    if ($status === 'draft') {
                        if ($canPublish && workflowAllowsAction($userRef, $infoblock, $status, 'publish')) {
                            echo '<form method="post" action="/admin.php?action=object_publish">';
                            echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                            echo '<button class="btn btn-sm btn-success" type="submit">Опубликовать</button>';
                            echo '</form>';
                        }
                    } else {
                        if ($canUnpublish && workflowAllowsAction($userRef, $infoblock, $status, 'unpublish')) {
                            echo '<form method="post" action="/admin.php?action=object_unpublish">';
                            echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                            echo '<button class="btn btn-sm btn-warning" type="submit">Снять</button>';
                            echo '</form>';
                        }
                        if ($canArchive && workflowAllowsAction($userRef, $infoblock, $status, 'archive')) {
                            echo '<form method="post" action="/admin.php?action=object_archive">';
                            echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                            echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                            echo '<button class="btn btn-sm btn-outline-secondary" type="submit">Архивировать</button>';
                            echo '</form>';
                        }
                    }
                    echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '">Предпросмотр</a>';
                    if ($canDelete) {
                        echo '<form method="post" action="/admin.php?action=object_delete" onsubmit="return confirm(\"Удалить объект?\")">';
                        echo '<input type="hidden" name="id" value="' . (int) $object['id'] . '">';
                        echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                        echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            $trashItems = $repo->listTrash((int) $infoblock['id'], 20);
            if (!empty($trashItems)) {
                echo '<div class="mt-3">';
                echo '<h4 class="h6">Корзина</h4>';
                echo '<div class="table-responsive">';
                echo '<table class="table table-sm align-middle">';
                echo '<thead><tr><th>ID</th><th>Заголовок</th><th>Действия</th></tr></thead><tbody>';
                foreach ($trashItems as $trash) {
                    $trashData = json_decode((string) $trash['data_json'], true);
                    $trashTitle = isset($trashData['title']) ? (string) $trashData['title'] : 'Без заголовка';
                    $canRestore = Permission::canAction($userRef, $infoblock, 'restore');
                    $canPurge = Permission::canAction($userRef, $infoblock, 'purge');
                    echo '<tr>';
                    echo '<td>' . (int) $trash['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($trashTitle, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td class="d-flex gap-2">';
                    if ($canRestore) {
                        echo '<form method="post" action="/admin.php?action=object_restore">';
                        echo '<input type="hidden" name="id" value="' . (int) $trash['id'] . '">';
                        echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                        echo '<button class="btn btn-sm btn-outline-primary" type="submit">Восстановить</button>';
                        echo '</form>';
                    }
                    if ($canPurge) {
                        echo '<form method="post" action="/admin.php?action=object_purge" onsubmit="return confirm(\"Удалить навсегда?\")">';
                        echo '<input type="hidden" name="id" value="' . (int) $trash['id'] . '">';
                        echo '<input type="hidden" name="section_id" value="' . (int) $section['id'] . '">';
                        echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить навсегда</button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div></div>';
            }
            echo '</div>';
        }
    }
}

echo '</div></div>';

echo '</div>';

echo '</div>';

// infoblock view selector script
if ($tab === 'infoblocks') {
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function () {';
    echo '  var componentSelect = document.querySelector("[data-view-selector=component]");';
    echo '  var viewSelect = document.querySelector("[data-view-selector=view]");';
    echo '  if (!componentSelect || !viewSelect) return;';
    echo '  function updateViews() {';
    echo '    var option = componentSelect.options[componentSelect.selectedIndex];';
    echo '    var views = [];';
    echo '    try { views = JSON.parse(option.dataset.views || "[]"); } catch (e) { views = []; }';
    echo '    if (!Array.isArray(views)) { views = []; }';
    echo '    if (views.indexOf("list") === -1) { views.unshift("list"); }';
    echo '    viewSelect.innerHTML = "";';
    echo '    views.forEach(function (view) {';
    echo '      var opt = document.createElement("option");';
    echo '      opt.value = view; opt.textContent = view;';
    echo '      viewSelect.appendChild(opt);';
    echo '    });';
    echo '  }';
    echo '  componentSelect.addEventListener("change", updateViews);';
    echo '  updateViews();';
    echo '});';
    echo '</script>';
}

AdminLayout::renderFooter();
