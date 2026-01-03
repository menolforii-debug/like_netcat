<?php

final class AdminRouter
{
    public static function run(): void
    {
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        $action = $action !== '' ? $action : 'dashboard';

        if (!preg_match('/^[A-Za-z0-9_]+$/', $action)) {
            http_response_code(400);
            AdminLayout::renderHeader('Bad action');
            renderAlert('Bad action', 'error');
            AdminLayout::renderFooter();
            return;
        }

        if ($action !== 'login' && $action !== 'logout') {
            if (!Auth::canEdit()) {
                redirectTo(buildAdminUrl(['action' => 'login']));
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'login') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                redirectTo(buildAdminUrl(['error' => 'Неверный CSRF-токен']));
            }
        }

        $notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
        $errorMessage = isset($_GET['error']) ? (string) $_GET['error'] : '';
        $selectedId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : null;
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'section';
        $user = Auth::user();

        $sectionRepo = new SectionRepo();
        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();
        $userRepo = new UserRepo();

        $baseDir = __DIR__ . '/actions/' . ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get');
        $path = $baseDir . '/' . $action . '.php';
        $realBase = realpath($baseDir);
        $realPath = $path !== '' ? realpath($path) : false;

        if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase)) {
            http_response_code(404);
            AdminLayout::renderHeader('Action not found');
            renderAlert('Action not found', 'error');
            AdminLayout::renderFooter();
            return;
        }

        require $realPath;
    }
}
