<?php

final class AdminRouter
{
    public static function run(): void
    {
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        if ($action === '') {
            $action = 'dashboard';
        }

        $isPost = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';

        if ($action !== 'login' && $action !== 'logout') {
            self::requireLogin();
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $action)) {
            self::renderError(400, 'Bad action');
            return;
        }

        if ($isPost && $action !== 'login') {
            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                redirectTo(buildAdminUrl(['error' => 'Неверный CSRF-токен']));
            }
        }

        $user = Auth::user();
        self::requirePermission($action, $isPost, $user);
        $notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
        $errorMessage = isset($_GET['error']) ? (string) $_GET['error'] : '';
        $selectedId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : null;
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'section';

        $sectionRepo = new SectionRepo();
        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();
        $userRepo = null;

        $baseDir = __DIR__ . '/actions/' . ($isPost ? 'post' : 'get');
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            self::renderError(500, 'Router misconfigured');
            return;
        }

        $actionFile = $baseDir . '/' . $action . '.php';
        $realFile = realpath($actionFile);
        if ($realFile === false) {
            self::renderError(404, 'Action not found');
            return;
        }

        $realBase = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($realFile, $realBase) !== 0) {
            self::renderError(400, 'Bad action');
            return;
        }

        require $realFile;
    }

    private static function requireLogin(): void
    {
        if (!Auth::canEdit()) {
            redirectTo('/admin.php?action=login');
        }
    }

    private static function renderError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        AdminLayout::renderHeader('Ошибка');
        renderAlert($message, 'error');
        AdminLayout::renderFooter();
    }

    private static function requirePermission(string $action, bool $isPost, ?array $user): void
    {
        if (!$isPost) {
            return;
        }

        if (Auth::isAdmin()) {
            return;
        }

        $editorActions = [
            'object_create',
            'object_update',
            'object_delete',
            'object_publish',
            'object_unpublish',
        ];

        if (!in_array($action, $editorActions, true)) {
            self::renderError(403, 'Недостаточно прав');
            exit;
        }
    }
}
