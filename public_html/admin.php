<?php

require __DIR__ . '/../app/bootstrap.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'login';

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

function fetchSectionById($id): ?array
{
    return DB::fetchOne(
        'SELECT id, title, extra_json FROM sections WHERE id = :id LIMIT 1',
        ['id' => $id]
    );
}

$error = '';

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login = isset($_POST['login']) ? trim((string) $_POST['login']) : '';
        $pass = isset($_POST['pass']) ? (string) $_POST['pass'] : '';

        if (Auth::login($login, $pass)) {
            redirectTo('/admin.php?action=trash_list');
        }

        $error = 'Неверный логин или пароль';
    }

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Вход</title></head><body>';
    if ($error !== '') {
        echo '<p style="color:red">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<form method="post" action="/admin.php?action=login">';
    echo '<label>Логин <input type="text" name="login" required></label><br>';
    echo '<label>Пароль <input type="password" name="pass" required></label><br>';
    echo '<button type="submit">Войти</button>';
    echo '</form>';
    echo '</body></html>';
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

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Создать пользователя</title></head><body>';
    echo '<p><a href="/admin.php?action=trash_list">К списку корзины</a> | <a href="/admin.php?action=logout">Выйти</a></p>';
    if ($error !== '') {
        echo '<p style="color:red">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<form method="post" action="/admin.php?action=users_create">';
    echo '<label>Логин <input type="text" name="login" required></label><br>';
    echo '<label>Пароль <input type="password" name="pass" required></label><br>';
    echo '<label>Роль <select name="role">';
    echo '<option value="editor">editor</option>';
    echo '<option value="admin">admin</option>';
    echo '</select></label><br>';
    echo '<button type="submit">Создать</button>';
    echo '</form>';
    echo '</body></html>';
    exit;
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

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>SEO раздела</title></head><body>';
    echo '<p><a href="/admin.php?action=trash_list">К списку корзины</a> | <a href="/admin.php?action=logout">Выйти</a></p>';
    echo '<h1>SEO для раздела: ' . htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<form method="post" action="/admin.php?action=seo_section&id=' . $id . '">';
    echo '<label>SEO title<br><input type="text" name="seo_title" value="' . htmlspecialchars((string) ($extra['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></label><br>';
    echo '<label>SEO description<br><textarea name="seo_description" rows="3" cols="50">' . htmlspecialchars((string) ($extra['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea></label><br>';
    echo '<label>SEO keywords<br><input type="text" name="seo_keywords" value="' . htmlspecialchars((string) ($extra['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></label><br>';
    echo '<button type="submit">Сохранить</button>';
    echo '</form>';
    echo '</body></html>';
    exit;
}

if ($action === 'object_delete') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($id > 0) {
            $repo = new ObjectRepo(core()->events());
            $repo->softDelete($id);
        }
        redirectTo('/admin.php?action=trash_list');
    }

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Удаление</title></head><body>';
    echo '<p>Переместить объект в корзину?</p>';
    echo '<form method="post" action="/admin.php?action=object_delete&id=' . $id . '">';
    echo '<button type="submit">Удалить</button>';
    echo '</form>';
    echo '<p><a href="/admin.php?action=trash_list">Отмена</a></p>';
    echo '</body></html>';
    exit;
}

if ($action === 'object_restore') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($id > 0) {
            $repo = new ObjectRepo(core()->events());
            $repo->restore($id);
        }
        redirectTo('/admin.php?action=trash_list');
    }

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Восстановление</title></head><body>';
    echo '<p>Восстановить объект?</p>';
    echo '<form method="post" action="/admin.php?action=object_restore&id=' . $id . '">';
    echo '<button type="submit">Восстановить</button>';
    echo '</form>';
    echo '<p><a href="/admin.php?action=trash_list">Отмена</a></p>';
    echo '</body></html>';
    exit;
}

if ($action === 'object_purge') {
    requireEditor();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($id > 0) {
            $repo = new ObjectRepo(core()->events());
            $repo->purge($id);
        }
        redirectTo('/admin.php?action=trash_list');
    }

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Удаление навсегда</title></head><body>';
    echo '<p>Удалить объект навсегда?</p>';
    echo '<form method="post" action="/admin.php?action=object_purge&id=' . $id . '">';
    echo '<button type="submit">Удалить навсегда</button>';
    echo '</form>';
    echo '<p><a href="/admin.php?action=trash_list">Отмена</a></p>';
    echo '</body></html>';
    exit;
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

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Корзина</title></head><body>';
    echo '<p><a href="/admin.php?action=logout">Выйти</a>';
    if (Auth::isAdmin()) {
        echo ' | <a href="/admin.php?action=users_create">Создать пользователя</a>';
    }
    echo '</p>';

    echo '<h1>Корзина</h1>';

    if (empty($items)) {
        echo '<p>Корзина пуста.</p>';
    } else {
        echo '<ul>';
        foreach ($items as $item) {
            $data = json_decode((string) $item['data_json'], true);
            $title = isset($data['title']) ? (string) $data['title'] : 'Без заголовка';
            echo '<li>';
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo ' <a href="/admin.php?action=object_restore&id=' . (int) $item['id'] . '">Восстановить</a>';
            echo ' <a href="/admin.php?action=object_purge&id=' . (int) $item['id'] . '">Удалить навсегда</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '</body></html>';
    exit;
}

http_response_code(404);
echo 'Неизвестное действие';
