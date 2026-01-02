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

$sectionRepo = new SectionRepo();

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
    echo '<h1 class="h5">' . ($isSite ? 'Site settings' : 'Section settings') . '</h1>';
    echo '<p class="text-muted mb-0">' . ($isSite ? 'Site settings UI will be implemented later.' : 'Section editor will be implemented later.') . '</p>';
    echo '</div></div>';
}

echo '</div>';

echo '</div>';

AdminLayout::renderFooter();

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
