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

        try {
            $result = self::executeActionFile($realFile);
        } catch (Throwable $e) {
            self::logActionError($action, $isPost, $user, $e);
            self::renderError(500, 'Внутренняя ошибка');
            return;
        }

        self::auditAction($action, $isPost, $user);
        self::handleActionResult($result);
    }

    /**
     * Выполняет action-файл в изолированном замыкании и возвращает его результат.
     */
    private static function executeActionFile(string $realFile): mixed
    {
        return (static function () use ($realFile) {
            return require $realFile;
        })();
    }

    /**
     * Обрабатывает результат action-файла без изменения поведения редиректов/вывода.
     */
    private static function handleActionResult(mixed $result): void
    {
        if (headers_sent() || self::hasRedirectHeader()) {
            return;
        }

        if (is_string($result)) {
            echo $result;
            return;
        }

        if (is_array($result) && self::isAjaxRequest()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Проверяет, является ли текущий запрос AJAX.
     */
    private static function isAjaxRequest(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower((string) $requestedWith) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }

    /**
     * Проверяет наличие редиректа через Location header.
     */
    private static function hasRedirectHeader(): bool
    {
        foreach (headers_list() as $headerLine) {
            if (stripos($headerLine, 'Location:') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Пишет в лог сведения об ошибке исполнения admin action.
     */
    private static function logActionError(string $action, bool $isPost, ?array $user, Throwable $e): void
    {
        $method = $isPost ? 'POST' : 'GET';
        $trace = self::shortenTrace($e->getTraceAsString());
        $data = [
            'method' => $method,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $trace,
        ];

        try {
            if (class_exists('AdminLog')) {
                $userId = $user['id'] ?? null;
                AdminLog::log($userId, $action, 'admin_error', null, $data);
                return;
            }
        } catch (Throwable $logError) {
            $data['log_error'] = $logError->getMessage();
        }

        $message = sprintf(
            'Admin action error [%s %s]: %s in %s:%d',
            $method,
            $action,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        error_log($message . PHP_EOL . $trace);
    }

    /**
     * Пишет минимальную запись аудита для POST actions (кроме login).
     */
    private static function auditAction(string $action, bool $isPost, ?array $user): void
    {
        if (!$isPost || $action === 'login') {
            return;
        }

        // Если action уже сделал редирект/отправил заголовки — не пишем audit,
        // чтобы не фиксировать "успех" до завершения ответа.
        if (headers_sent() || self::hasRedirectHeader()) {
            return;
        }

        if (!class_exists('AdminLog')) {
            return;
        }

        $userId = $user['id'] ?? null;

        try {
            AdminLog::log($userId, $action, 'admin_action', null, [
                'method' => 'POST',
            ]);
        } catch (Throwable $e) {
            error_log('Admin audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Укорачивает trace для логирования.
     */
    private static function shortenTrace(string $trace, int $maxLines = 6): string
    {
        $lines = explode("\n", $trace);
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[] = '...';
        }

        return implode("\n", $lines);
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
                'logout' => 'guest',
                'dashboard' => 'editor',
                'object_form' => 'editor',
            ],
            'POST' => [
                'login' => 'guest',
                'logout' => 'guest',
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
