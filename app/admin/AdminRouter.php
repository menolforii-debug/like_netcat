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

        self::requireLogin($action, $isPost);

        if (!preg_match('/^[A-Za-z0-9_]+$/', $action)) {
            self::renderError(400, 'Bad action');
            return;
        }

        if ($isPost && $action !== 'login') {
            if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
                self::renderError(400, 'Неверный CSRF-токен');
                return;
            }
        }

        $user = Auth::user();
        self::requirePermission($action, $isPost, $user);
        $selectedId = isset($_GET['section_id']) ? (int) $_GET['section_id'] : null;
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'section';

        $sectionRepo = new SectionRepo();
        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();
        $userRepo = new UserRepo();
        $visualFieldRepo = new VisualFieldRepo();

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

    private static function requireLogin(string $action, bool $isPost): void
    {
        $policy = self::actionPolicy($isPost ? 'POST' : 'GET', $action);
        if ($policy['role'] === 'guest') {
            return;
        }

        if (!Auth::canEdit()) {
            redirectTo('/admin.php?action=login');
        }
    }

    private static function renderError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        AdminLayout::renderHeader('Ошибка');
        echo '<div class="container py-4">';
        echo '<div class="text-danger fw-semibold">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
        AdminLayout::renderFooter();
    }

    private static function requirePermission(string $action, bool $isPost, ?array $user): void
    {
        $policy = self::actionPolicy($isPost ? 'POST' : 'GET', $action);
        $role = $policy['role'];

        if ($role === 'guest') {
            return;
        }

        if ($user === null) {
            return;
        }

        if ($role === 'editor' && Auth::canEdit()) {
            return;
        }

        if ($role === 'admin' && Auth::isAdmin()) {
            return;
        }

        self::renderError(403, 'Недостаточно прав');
        exit;
    }

    private static function actionPolicy(string $method, string $action): array
    {
        $method = strtoupper($method);
        $policies = [
            'GET' => [
                'login' => 'guest',
                'logout' => 'editor',
                'dashboard' => 'editor',
                'object_form' => 'editor',
            ],
            'POST' => [
                'login' => 'guest',
                'logout' => 'editor',
                'object_create' => 'editor',
                'object_update' => 'editor',
                'object_delete' => 'editor',
                'object_delete_all' => 'editor',
                'object_publish' => 'editor',
                'object_unpublish' => 'editor',
                'object_restore' => 'editor',
                'object_purge' => 'editor',
            ],
        ];

        $role = $policies[$method][$action] ?? 'admin';

        return [
            'role' => $role,
        ];
    }
}
