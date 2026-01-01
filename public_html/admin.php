<?php

require __DIR__ . '/../app/bootstrap.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'object_list';

function redirectTo($url): void
{
    header('Location: ' . $url);
    exit;
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

function renderHeader(string $title): void
{
    $user = Auth::user();
    $login = $user ? (string) ($user['login'] ?? '') : '';

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>';
    echo '<nav class="navbar navbar-expand-lg navbar-light bg-light">';
    echo '<div class="container">';
    echo '<span class="navbar-brand">CMS</span>';
    echo '<div class="ms-auto">';
    if ($login !== '') {
        echo '<span class="me-3">' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<a class="btn btn-outline-secondary btn-sm" href="/admin.php?action=logout">Выйти</a>';
    }
    echo '</div></div></nav>';
    echo '<main class="container mt-4">';
}

function renderFooter(): void
{
    echo '</main></body></html>';
}

function statusBadge(string $status): string
{
    $label = $status === 'draft' ? 'Черновик' : ($status === 'archived' ? 'Архив' : 'Опубликован');
    $class = $status === 'draft' ? 'bg-warning text-dark' : ($status === 'archived' ? 'bg-secondary' : 'bg-success');

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function fetchInfoblockById($id): ?array
{
    $row = DB::fetchOne(
        'SELECT id, section_id, component_id, name, view_template, extra_json FROM infoblocks WHERE id = :id LIMIT 1',
        ['id' => $id]
    );

    return normalizeInfoblockRow($row);
}

function fetchObjectById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, infoblock_id, component_id, data_json, status FROM objects WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
}

function fetchSectionById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, title, extra_json FROM sections WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
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

    $viewTemplate = isset($row['view_template']) ? trim((string) $row['view_template']) : '';
    $row['view_template'] = $viewTemplate !== '' ? $viewTemplate : 'list';

    return $row;
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

function parseComponentFields(array $component): array
{
    $validator = new FieldValidator();

    return $validator->parseFields($component);
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

$error = '';
$errors = [];

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';

        if (Auth::login($login, $pass)) {
            redirectTo('/admin.php?action=object_list');
        }

        $error = 'Неверный логин или пароль';
    }

    renderHeader('Вход');
    if ($error !== '') {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<form method="post" action="/admin.php?action=login" class="card card-body">';
    echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
    echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
    echo '<button class="btn btn-primary" type="submit">Войти</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'logout') {
    Auth::logout();
    redirectTo('/admin.php?action=login');
}

if ($action === 'users_create') {
    requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';
        $role = isset($_POST['role']) ? (string) $_POST['role'] : 'editor';

        if ($login !== '' && $pass !== '' && in_array($role, ['admin', 'editor'], true)) {
            $stmt = DB::pdo()->prepare('INSERT INTO users (login, pass_hash, role) VALUES (:login, :pass_hash, :role)');
            $stmt->execute([
                'login' => $login,
                'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
            redirectTo('/admin.php?action=users_create');
        }

        $error = 'Заполните все поля корректно';
    }

    renderHeader('Создать пользователя');
    if ($error !== '') {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<form method="post" action="/admin.php?action=users_create" class="card card-body">';
    echo '<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" type="text" name="login" required></div>';
    echo '<div class="mb-3"><label class="form-label">Пароль</label><input class="form-control" type="password" name="pass" required></div>';
    echo '<div class="mb-3"><label class="form-label">Роль</label><select class="form-select" name="role">';
    echo '<option value="editor">editor</option>';
    echo '<option value="admin">admin</option>';
    echo '</select></div>';
    echo '<button class="btn btn-primary" type="submit">Создать</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'object_list') {
    requireEditor();

    $rows = DB::fetchAll(
        'SELECT o.id, o.section_id, o.infoblock_id, o.data_json, o.status,
            i.name AS infoblock_name, i.view_template, i.extra_json, i.component_id
        FROM objects o
        JOIN infoblocks i ON i.id = o.infoblock_id
        WHERE o.is_deleted = 0 AND o.status IN ("draft", "published")
        ORDER BY o.id DESC'
    );

    renderHeader('Объекты');
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<h1 class="h3 mb-0">Объекты</h1>';
    if (Auth::isAdmin()) {
        echo '<a class="btn btn-outline-secondary btn-sm" href="/admin.php?action=users_create">Пользователи</a>';
    }
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="alert alert-info">Нет объектов.</div>';
        renderFooter();
        exit;
    }

    echo '<table class="table table-striped">';
    echo '<thead><tr><th>ID</th><th>Инфоблок</th><th>Заголовок</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $infoblock = normalizeInfoblockRow([
            'id' => $row['infoblock_id'],
            'component_id' => $row['component_id'],
            'name' => $row['infoblock_name'],
            'view_template' => $row['view_template'],
            'extra_json' => $row['extra_json'],
        ]);
        $user = Auth::user();
        $canEdit = $infoblock ? Permission::canAction($user, $infoblock, 'edit') : false;
        $canDelete = $infoblock ? Permission::canAction($user, $infoblock, 'delete') : false;
        $canView = $infoblock ? Permission::canAction($user, $infoblock, 'view') : false;
        $canConfigure = $infoblock ? Permission::canAction($user, $infoblock, 'edit') : false;
        $canPublish = $infoblock ? Permission::canAction($user, $infoblock, 'publish') : false;
        $canUnpublish = $infoblock ? Permission::canAction($user, $infoblock, 'unpublish') : false;
        $canArchive = $infoblock ? Permission::canAction($user, $infoblock, 'archive') : false;
        $status = (string) $row['status'];
        $workflowPublish = $infoblock ? workflowAllowsAction($user, $infoblock, $status, 'publish') : false;
        $workflowUnpublish = $infoblock ? workflowAllowsAction($user, $infoblock, $status, 'unpublish') : false;
        $workflowArchive = $infoblock ? workflowAllowsAction($user, $infoblock, $status, 'archive') : false;
        $data = json_decode((string) $row['data_json'], true);
        $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
        $sectionPath = buildSectionPathFromId((int) $row['section_id']);
        $previewUrl = $sectionPath . '?preview=1&object_id=' . (int) $row['id'] . '&edit=1';

        echo '<tr>';
        echo '<td>' . (int) $row['id'] . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['infoblock_name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . statusBadge((string) $row['status']) . '</td>';
        echo '<td class="d-flex gap-2">';
        if ($status === 'draft') {
            if ($canPublish && $workflowPublish) {
                echo '<form method="post" action="/admin.php?action=object_publish&id=' . (int) $row['id'] . '">';
                echo '<button class="btn btn-sm btn-success" type="submit">Опубликовать</button>';
                echo '</form>';
            }
        } else {
            if ($canUnpublish && $workflowUnpublish) {
                echo '<form method="post" action="/admin.php?action=object_unpublish&id=' . (int) $row['id'] . '">';
                echo '<button class="btn btn-sm btn-warning" type="submit">Снять</button>';
                echo '</form>';
            }
            if ($canArchive && $workflowArchive) {
                echo '<form method="post" action="/admin.php?action=object_archive&id=' . (int) $row['id'] . '">';
                echo '<button class="btn btn-sm btn-outline-secondary" type="submit">Архивировать</button>';
                echo '</form>';
            }
        }
        if ($canEdit) {
            echo '<a class="btn btn-sm btn-outline-primary" href="/admin.php?action=object_edit&id=' . (int) $row['id'] . '">Редактировать</a>';
        }
        if ($canView) {
            echo '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '">Предпросмотр</a>';
        }
        if ($canDelete) {
            echo '<form method="post" action="/admin.php?action=object_delete&id=' . (int) $row['id'] . '">';
            echo '<button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
        }
        if ($canConfigure && $infoblock) {
            echo '<a class="btn btn-sm btn-outline-dark" href="/admin.php?action=infoblock_edit&id=' . (int) $infoblock['id'] . '">Вид</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    renderFooter();
    exit;
}

if ($action === 'object_create') {
    requireEditor();

    $infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
    $infoblock = $infoblockId > 0 ? fetchInfoblockById($infoblockId) : null;

    if ($infoblock === null) {
        http_response_code(404);
        echo 'Инфоблок не найден';
        exit;
    }

    requireInfoblockAction($infoblock, 'create');

    $componentRepo = new ComponentRepo();
    $component = $componentRepo->findById((int) $infoblock['component_id']);
    if ($component === null) {
        http_response_code(404);
        echo 'Компонент не найден';
        exit;
    }

    $fields = parseComponentFields($component);
    $data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = extractFormData($fields);
        try {
            $repo = new ObjectRepo(core()->events());
            $repo->insert($component, (int) $infoblock['section_id'], (int) $infoblock['id'], $data, 'draft');
            redirectTo('/admin.php?action=object_list');
        } catch (Throwable $e) {
            $errors = explode("\n", $e->getMessage());
        }
    }

    renderHeader('Создать объект');
    echo '<h1 class="h4">Создать объект: ' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</h1>';
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            echo '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<form method="post" action="/admin.php?action=object_create&infoblock_id=' . $infoblockId . '" class="card card-body">';
    foreach ($fields as $field) {
        echo renderFieldInput($field, $data);
    }
    echo '<button class="btn btn-primary" type="submit">Сохранить черновик</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'object_edit') {
    requireEditor();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $object = $id > 0 ? fetchObjectById($id) : null;

    if ($object === null) {
        http_response_code(404);
        echo 'Объект не найден';
        exit;
    }

    $infoblock = fetchInfoblockById((int) $object['infoblock_id']);
    if ($infoblock === null) {
        http_response_code(404);
        echo 'Инфоблок не найден';
        exit;
    }

    requireInfoblockAction($infoblock, 'edit');

    $componentRepo = new ComponentRepo();
    $component = $componentRepo->findById((int) $object['component_id']);
    if ($component === null) {
        http_response_code(404);
        echo 'Компонент не найден';
        exit;
    }

    $fields = parseComponentFields($component);
    $data = json_decode((string) $object['data_json'], true);
    if (!is_array($data)) {
        $data = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = extractFormData($fields);
        try {
            $repo = new ObjectRepo(core()->events());
            $repo->update($component, $id, $data);
            redirectTo('/admin.php?action=object_edit&id=' . $id);
        } catch (Throwable $e) {
            $errors = explode("\n", $e->getMessage());
        }
    }

    renderHeader('Редактировать объект');
    echo '<h1 class="h4">Редактировать объект</h1>';
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            echo '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }
    echo '<form method="post" action="/admin.php?action=object_edit&id=' . $id . '" class="card card-body">';
    foreach ($fields as $field) {
        echo renderFieldInput($field, $data);
    }
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'infoblock_edit') {
    requireEditor();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $infoblock = $id > 0 ? fetchInfoblockById($id) : null;

    if ($infoblock === null) {
        http_response_code(404);
        echo 'Инфоблок не найден';
        exit;
    }

    requireInfoblockAction($infoblock, 'edit');

    $componentRepo = new ComponentRepo();
    $component = $componentRepo->findById((int) $infoblock['component_id']);
    if ($component === null) {
        http_response_code(404);
        echo 'Компонент не найден';
        exit;
    }

    $views = isset($component['views']) && is_array($component['views']) ? $component['views'] : ['list'];
    $currentView = $infoblock['view_template'] ?? 'list';
    if (!in_array($currentView, $views, true)) {
        $currentView = 'list';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedView = isset($_POST['view_template']) ? trim((string) $_POST['view_template']) : '';
        if (!in_array($selectedView, $views, true)) {
            $selectedView = 'list';
        }

        $stmt = DB::pdo()->prepare('UPDATE infoblocks SET view_template = :view_template WHERE id = :id');
        $stmt->execute([
            'view_template' => $selectedView,
            'id' => $id,
        ]);

        redirectTo('/admin.php?action=infoblock_edit&id=' . $id);
    }

    renderHeader('Шаблон инфоблока');
    echo '<h1 class="h4">Шаблон инфоблока: ' . htmlspecialchars((string) $infoblock['name'], ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<form method="post" action="/admin.php?action=infoblock_edit&id=' . $id . '" class="card card-body">';
    echo '<div class="mb-3"><label class="form-label">Вариант отображения</label>';
    echo '<select class="form-select" name="view_template">';
    foreach ($views as $view) {
        $selected = $view === $currentView ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'object_publish') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'publish');
        try {
            $repo = new ObjectRepo(core()->events());
            $repo->publish($id);
        } catch (Throwable $e) {
            http_response_code(403);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }
    }
    redirectTo('/admin.php?action=object_list');
}

if ($action === 'object_unpublish') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'unpublish');
        try {
            $repo = new ObjectRepo(core()->events());
            $repo->unpublish($id);
        } catch (Throwable $e) {
            http_response_code(403);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }
    }
    redirectTo('/admin.php?action=object_list');
}

if ($action === 'object_archive') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'archive');
        try {
            $repo = new ObjectRepo(core()->events());
            $repo->archive($id);
        } catch (Throwable $e) {
            http_response_code(403);
            echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            exit;
        }
    }
    redirectTo('/admin.php?action=object_list');
}

if ($action === 'seo_section') {
    requireEditor();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $section = $id > 0 ? fetchSectionById($id) : null;

    if ($section === null) {
        http_response_code(404);
        echo 'Раздел не найден';
        exit;
    }

    $extra = json_decode((string) ($section['extra_json'] ?? '{}'), true);
    if (!is_array($extra)) {
        $extra = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $extra['seo_title'] = isset($_POST['seo_title']) ? trim((string) $_POST['seo_title']) : '';
        $extra['seo_description'] = isset($_POST['seo_description']) ? trim((string) $_POST['seo_description']) : '';
        $extra['seo_keywords'] = isset($_POST['seo_keywords']) ? trim((string) $_POST['seo_keywords']) : '';

        $stmt = DB::pdo()->prepare('UPDATE sections SET extra_json = :extra_json WHERE id = :id');
        $stmt->execute([
            'extra_json' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);

        redirectTo('/admin.php?action=seo_section&id=' . $id);
    }

    renderHeader('SEO раздела');
    echo '<h1 class="h4">SEO для раздела: ' . htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<form method="post" action="/admin.php?action=seo_section&id=' . $id . '" class="card card-body">';
    echo '<div class="mb-3"><label class="form-label">SEO title</label><input class="form-control" type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<div class="mb-3"><label class="form-label">SEO description</label><textarea class="form-control" name="seo_description" rows="3">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></div>';
    echo '<div class="mb-3"><label class="form-label">SEO keywords</label><input class="form-control" type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
    echo '</form>';
    renderFooter();
    exit;
}

if ($action === 'object_delete') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'delete');
        $repo = new ObjectRepo(core()->events());
        $repo->softDelete($id);
    }
    redirectTo('/admin.php?action=object_list');
}

if ($action === 'object_restore') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'restore');
        $repo = new ObjectRepo(core()->events());
        $repo->restore($id);
    }
    redirectTo('/admin.php?action=trash_list');
}

if ($action === 'object_purge') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $object = fetchObjectById($id);
        $infoblock = $object ? fetchInfoblockById((int) $object['infoblock_id']) : null;
        requireInfoblockAction($infoblock, 'purge');
        $repo = new ObjectRepo(core()->events());
        $repo->purge($id);
    }
    redirectTo('/admin.php?action=trash_list');
}

if ($action === 'trash_list') {
    requireEditor();

    $infoblockId = isset($_GET['infoblock_id']) ? (int) $_GET['infoblock_id'] : 0;
    $repo = new ObjectRepo(core()->events());

    if ($infoblockId > 0) {
        $items = $repo->listTrash($infoblockId, 50);
    } else {
        $items = $repo->listTrashAll(50);
    }

    renderHeader('Корзина');
    echo '<h1 class="h4">Корзина</h1>';

    if (empty($items)) {
        echo '<div class="alert alert-info">Корзина пуста.</div>';
    } else {
        echo '<ul class="list-group">';
        foreach ($items as $item) {
            $data = json_decode((string) $item['data_json'], true);
            $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo '<span>';
            echo '<a class="btn btn-sm btn-outline-primary me-2" href="/admin.php?action=object_restore&id=' . (int) $item['id'] . '">Восстановить</a>';
            echo '<a class="btn btn-sm btn-outline-danger" href="/admin.php?action=object_purge&id=' . (int) $item['id'] . '">Удалить навсегда</a>';
            echo '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    renderFooter();
    exit;
}

http_response_code(404);
renderHeader('Ошибка');
echo '<div class="alert alert-danger">Неизвестное действие</div>';
renderFooter();
