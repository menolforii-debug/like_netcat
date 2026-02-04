<?php

require_once __DIR__ . '/../shared/runtime_guard.php';
assert_runtime('admin');

// ---------------- Flash messages (admin) ----------------
function adminFlashSet(string $type, string $message): void
{
    if (!isset($_SESSION)) {
        return;
    }
    if (!isset($_SESSION['admin_flash']) || !is_array($_SESSION['admin_flash'])) {
        $_SESSION['admin_flash'] = [];
    }
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

function adminFlashConsume(): array
{
    if (!isset($_SESSION) || empty($_SESSION['admin_flash']) || !is_array($_SESSION['admin_flash'])) {
        return [];
    }
    $items = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return is_array($items) ? $items : [];
}

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function buildAdminUrl(array $params = []): string
{
    return '/admin.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_token_field(): string
{
    $token = csrf_token();

    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function is_valid_csrf_token(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
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

function collectSectionTree(SectionRepo $repo, int $parentId, int $depth = 0): array
{
    $items = [];
    $children = $repo->listChildren($parentId);
    foreach ($children as $child) {
        $child['depth'] = $depth;
        $items[] = $child;
        $items = array_merge($items, collectSectionTree($repo, (int) $child['id'], $depth + 1));
    }

    return $items;
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

function extractNestedUpload(array $files, string $key): ?array
{
    if (!isset($files['name'][$key])) {
        return null;
    }

    return [
        'name' => $files['name'][$key] ?? '',
        'type' => $files['type'][$key] ?? '',
        'tmp_name' => $files['tmp_name'][$key] ?? '',
        'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
        'size' => $files['size'][$key] ?? 0,
    ];
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

function renderFieldInput(array $field, array $data, array $uploadContext = []): string
{
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $label = htmlspecialchars((string) ($field['label'] ?? $name), ENT_QUOTES, 'UTF-8');
    $value = isset($data[$name]) ? (string) $data[$name] : '';
    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $name);
    $previewId = 'file-preview-' . $safeId;
    $clearId = 'file-clear-' . $safeId;

    $html = '<label class="form-label">' . $label . '</label>';
    switch ($type) {
        case 'textarea':
            $textareaClass = 'form-control';
            if (($uploadContext['context'] ?? '') === 'component') {
                $textareaClass .= ' js-ckeditor';
            }
            $html .= '<textarea class="' . $textareaClass . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" rows="4">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</textarea>';
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
        case 'file':
            $inputId = 'file-input-' . $safeId;
            $deleteId = 'file-delete-' . $safeId;
            $html .= '<input class="form-control" id="' . $inputId . '" type="file" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" data-file-preview-container="#' . $previewId . '" data-file-preview-show-info="true" data-file-btn-clear="#' . $clearId . '">';
            $html .= '<div id="' . $previewId . '" class="mt-2"></div>';
            $html .= '<button class="btn btn-sm btn-outline-secondary mt-2" type="button" id="' . $clearId . '">Очистить</button>';
            if ($value !== '') {
                $html .= '<div class="form-text">Текущий файл: <a href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars(basename($value), ENT_QUOTES, 'UTF-8') . '</a></div>';
                $html .= '<div class="form-check mt-2">';
                $html .= '<input class="form-check-input" type="checkbox" name="delete_files[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']" value="1" id="' . $deleteId . '">';
                $html .= '<label class="form-check-label" for="' . $deleteId . '">Удалить файл</label>';
                $html .= '</div>';
            }
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

function ensurePreviewToken(): string
{
    if (empty($_SESSION['preview_token'])) {
        $_SESSION['preview_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['preview_token'];
}

function deleteUploadedFile(string $publicPath): void
{
    $path = trim($publicPath);
    if ($path === '') {
        error_log('deleteUploadedFile: empty path');
        return;
    }

    // Разбираем абсолютные URL и приводим относительный путь к виду /files/...
    error_log('deleteUploadedFile: original=' . $path);
    $parsed = parse_url($path);
    if (is_array($parsed) && isset($parsed['path'])) {
        $path = (string) $parsed['path'];
    }
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    if (!str_starts_with($path, '/files/')) {
        // Старые данные могли быть без ведущего слеша.
        $path = '/files/' . ltrim($path, '/');
    }
    if (!str_starts_with($path, '/files/')) {
        error_log('deleteUploadedFile: not in /files/ path=' . $path);
        return;
    }
    error_log('deleteUploadedFile: normalized=' . $path);

    // Поднимаемся из app/admin в корень проекта, чтобы удалить файл из public_html.
    $root = dirname(__DIR__, 3);
    $fullPath = $root . '/public_html' . $path;
    if (!file_exists($fullPath)) {
        error_log('deleteUploadedFile: not found fullPath=' . $fullPath);
        return;
    }

    // Удаляем только внутри public_html/files.
    rmTree($fullPath, $root . '/public_html/files');
    error_log('deleteUploadedFile: deleted fullPath=' . $fullPath);
}

function parseMirrorLines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value);
    if ($lines === false) {
        return [];
    }

    $mirrors = [];
    foreach ($lines as $line) {
        $line = Utils::normalizeHost(trim($line));
        if ($line !== '') {
            $mirrors[] = $line;
        }
    }

    return array_values(array_unique($mirrors));
}

function englishNameIsValid(string $englishName): bool
{
    return Utils::isUrlSafe($englishName);
}

function componentKeyIsValid(string $componentKey): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $componentKey)) {
        return false;
    }

    if (str_contains($componentKey, '..') || str_contains($componentKey, '/') || str_contains($componentKey, '\\')) {
        return false;
    }

    return true;
}

function snippetKeyIsValid(string $snippetKey): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $snippetKey)) {
        return false;
    }

    if (str_contains($snippetKey, '..') || str_contains($snippetKey, '/') || str_contains($snippetKey, '\\')) {
        return false;
    }

    return true;
}

function parseVisualFieldOptions(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value);
    if ($lines === false) {
        return [];
    }

    $options = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $key = $line;
        $label = $line;
        if (str_contains($line, ':')) {
            [$key, $label] = explode(':', $line, 2);
        } elseif (str_contains($line, '=')) {
            [$key, $label] = explode('=', $line, 2);
        }

        $key = trim($key);
        $label = trim($label);
        if ($key === '') {
            continue;
        }

        $options[$key] = $label !== '' ? $label : $key;
    }

    return $options;
}

function layoutKeyIsValid(string $layoutKey): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $layoutKey)) {
        return false;
    }

    if (str_contains($layoutKey, '..') || str_contains($layoutKey, '/') || str_contains($layoutKey, '\\')) {
        return false;
    }

    return true;
}

function saveUploadedFile(array $file, string $targetDir, string $publicPrefix, ?string &$error = null): ?string
{
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла.';
        return null;
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = 'Невозможно прочитать загруженный файл.';
        return null;
    }

    $filename = sanitizeUploadedFilename((string) ($file['name'] ?? ''));
    if ($filename === '') {
        $filename = 'file';
    }

    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $allowedExtensions = allowedUploadExtensions();
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        $error = 'Недопустимый тип файла.';
        return null;
    }

    $mimeType = detectUploadedMimeType($file['tmp_name']);
    $allowedMimeTypes = allowedUploadMimeTypes();
    if ($mimeType !== null && !in_array($mimeType, $allowedMimeTypes, true)) {
        $error = 'Недопустимый MIME-тип файла.';
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0770, true);
        @chmod($targetDir, 0770);
    }

    $finalName = $filename;
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $finalName;
    if (is_file($fullPath)) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = date('YmdHis') . '_' . bin2hex(random_bytes(3));
        $finalName = $base . '_' . $suffix . ($ext !== '' ? '.' . $ext : '');
        $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $finalName;
    }

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        $error = 'Не удалось сохранить файл.';
        return null;
    }
    @chmod($fullPath, 0660);

    return rtrim($publicPrefix, '/') . '/' . $finalName;
}

function sanitizeUploadedFilename(string $filename): string
{
    $filename = basename($filename);
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    return $filename ?? '';
}

function detectUploadedMimeType(string $path): ?string
{
    if (!class_exists('finfo')) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $result = $finfo->file($path);
    return is_string($result) ? $result : null;
}

function allowedUploadExtensions(): array
{
    return [
        // Изображения
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
        // Архивы
        'zip', 'rar', '7z', 'tar', 'gz', 'tgz',
        // Документы
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'txt', 'csv', 'odt', 'ods', 'odp',
    ];
}

function allowedUploadMimeTypes(): array
{
    return [
        // Изображения
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/avif',
        // Архивы
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/vnd.rar',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip',
        'application/x-gzip',
        // Документы
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        'text/plain',
        'text/csv',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
    ];
}

function rmTree(string $path, string $allowedRoot): void
{
    if (!is_dir($path) && !is_file($path)) {
        return;
    }

    $message = 'Запрошено удаление пути: ' . $path . ' (root: ' . $allowedRoot . ')';
    error_log($message);

    $realPath = realpath($path);
    if ($realPath === false) {
        $message = 'Не удалось определить реальный путь для удаления: ' . $path;
        error_log($message);
        return;
    }

    $realRoot = realpath($allowedRoot);
    if ($realRoot === false) {
        $message = 'Разрешенная директория не найдена: ' . $allowedRoot;
        error_log($message);
        throw new RuntimeException($message);
    }
    $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath . DIRECTORY_SEPARATOR, $realRoot)) {
        $message = 'Запрещено удалять путь вне разрешенных директорий: ' . $realPath;
        error_log($message);
        throw new RuntimeException($message);
    }

    if (is_file($realPath)) {
        if (!unlink($realPath)) {
            $message = 'Не удалось удалить файл: ' . $realPath;
            error_log($message);
            throw new RuntimeException($message);
        }
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            if (!rmdir($itemPath)) {
                $message = 'Не удалось удалить директорию: ' . $itemPath;
                error_log($message);
                throw new RuntimeException($message);
            }
        } else {
            if (!unlink($itemPath)) {
                $message = 'Не удалось удалить файл: ' . $itemPath;
                error_log($message);
                throw new RuntimeException($message);
            }
        }
    }

    if (!rmdir($realPath)) {
        $message = 'Не удалось удалить директорию: ' . $realPath;
        error_log($message);
        throw new RuntimeException($message);
    }
}

function isAjaxRequest(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function adminOk(string $message = '', array $focus = [], bool $withSidebar = true, array $extra = []): void
{
    $payload = [
        'ok' => true,
    ];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    if (!empty($focus)) {
        $payload['focus'] = $focus;
    }
    $payload['refresh'] = $withSidebar ? ['#left-sidebar', '#content'] : ['#content'];

    $payload = array_merge($payload, $extra);

    jsonResponse($payload);
}

function normalizeComponentFieldsInput(array $fieldsInput): array
{
    $normalized = [];

    foreach ($fieldsInput as $row) {
        if (is_string($row)) {
            $normalized[] = ['name' => $row];
            continue;
        }

        if (!is_array($row)) {
            continue;
        }

        if (isset($row['options']) && is_array($row['options'])) {
            $options = [];
            foreach ($row['options'] as $key => $value) {
                if (is_array($value)) {
                    $options[] = $value;
                } else {
                    $options[] = ['key' => $key, 'label' => $value];
                }
            }
            $row['options'] = $options;
        }

        $normalized[] = $row;
    }

    return $normalized;
}

function componentActionTemplatePath(string $componentKey): string
{
    return dirname(__DIR__, 3) . '/templates/component/' . $componentKey . '/actions.php';
}

function readComponentActionTemplate(string $componentKey): string
{
    $path = componentActionTemplatePath($componentKey);
    if (!is_file($path)) {
        return '';
    }

    $content = file_get_contents($path);
    if ($content === false) {
        return '';
    }

    return Utils::stripSystemTemplateTags($content);
}

function writeComponentActionTemplate(string $componentKey, string $template, ?string &$error = null): bool
{
    $template = trim(Utils::stripSystemTemplateTags($template));
    $dir = dirname(componentActionTemplatePath($componentKey));
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
        @chmod($dir, 0770);
    }

    $path = componentActionTemplatePath($componentKey);
    if ($template === '') {
        if (is_file($path)) {
            @unlink($path);
        }
        return true;
    }

    $content = "<?php\n" . rtrim($template) . "\n";
    if (file_put_contents($path, $content) === false) {
        $error = 'Не удалось сохранить шаблон действий.';
        return false;
    }
    @chmod($path, 0660);

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($path));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        $error = 'Синтаксическая ошибка в шаблоне действий: ' . trim((string) $lintOutput);
        return false;
    }

    return true;
}

function layoutTemplatesDir(): string
{
    return dirname(__DIR__, 2) . '/templates/layouts';
}

function layoutNavTemplatesDir(): string
{
    return dirname(__DIR__, 2) . '/templates/layouts';
}

function readLayoutTemplate(string $layoutKey): ?string
{
    if (!layoutKeyIsValid($layoutKey)) {
        return null;
    }

    $path = layoutTemplatesDir() . '/' . $layoutKey . '.php';
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    return $content === false ? null : $content;
}

function readLayoutNavTemplate(string $layoutKey): ?string
{
    if (!layoutKeyIsValid($layoutKey)) {
        return null;
    }

    $path = layoutNavTemplatesDir() . '/' . $layoutKey . '.nav.php';
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    return $content === false ? null : $content;
}

function readDefaultLayoutTemplateFile(): ?string
{
    $path = layoutTemplatesDir() . '/default/default.php';
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    return $content === false ? null : $content;
}

function readDefaultLayoutNavTemplateFile(): ?string
{
    $path = layoutNavTemplatesDir() . '/default/default.nav.php';
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    return $content === false ? null : $content;
}

function writeLayoutTemplate(string $layoutKey, string $content, ?string &$error = null): bool
{
    if (!layoutKeyIsValid($layoutKey)) {
        $error = 'Некорректный ключ макета.';
        return false;
    }

    $templatesDir = layoutTemplatesDir();
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0775, true);
        @chmod($templatesDir, 0775);
    }

    // Надёжная проверка записи (is_writable может врать из-за open_basedir/ACL/FS)
    $probe = rtrim($templatesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.write_probe';
    $ok = @file_put_contents($probe, '1');
    if ($ok === false) {
        $last = error_get_last();
        $msg = is_array($last) && isset($last['message']) ? $last['message'] : 'unknown';
        $ob = ini_get('open_basedir') ?: '';
        $error = 'Папка макетов недоступна для записи: ' . $templatesDir .
            ($ob !== '' ? ' (open_basedir=' . $ob . ')' : '') .
            ' (' . $msg . ')';
        return false;
    }
    @unlink($probe);

    $finalPath = $templatesDir . '/' . $layoutKey . '.php';
    $tmpPath = $finalPath . '.tmp';

    $bytes = @file_put_contents($tmpPath, $content);
    if ($bytes === false) {
        $permsDir = @substr(sprintf('%o', @fileperms($templatesDir)), -4);
        $ownerDir = function_exists('posix_getpwuid') ? @posix_getpwuid(@fileowner($templatesDir)) : null;
        $ownerName = is_array($ownerDir) && isset($ownerDir['name']) ? $ownerDir['name'] : 'unknown';
        $error = 'Не удалось сохранить макет. Проверь права на запись. ' .
            'dir_perms=' . ($permsDir ?: '????') . ', dir_owner=' . $ownerName . ', dir=' . $templatesDir;
        return false;
    }
    @chmod($tmpPath, 0664);

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($tmpPath));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        @unlink($tmpPath);
        $error = 'Синтаксическая ошибка в макете: ' . trim((string) $lintOutput);
        return false;
    }

    if (is_file($finalPath)) {
        $backupDir = dirname(__DIR__, 3) . '/var/backups/layouts';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0770, true);
            @chmod($backupDir, 0770);
        }
        $backupPath = $backupDir . '/' . $layoutKey . '.php.' . date('YmdHis') . '.bak';
        if (@copy($finalPath, $backupPath)) {
            @chmod($backupPath, 0660);
        }
    }

    if (!rename($tmpPath, $finalPath)) {
        @unlink($tmpPath);
        $error = 'Не удалось обновить макет.';
        return false;
    }
    @chmod($finalPath, 0664);

    return true;
}

function writeLayoutNavTemplate(string $layoutKey, string $content, ?string &$error = null): bool
{
    if (!layoutKeyIsValid($layoutKey)) {
        $error = 'Некорректный ключ макета.';
        return false;
    }

    $templatesDir = layoutNavTemplatesDir();
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0775, true);
        @chmod($templatesDir, 0775);
    }

    if (!is_writable($templatesDir)) {
        $error = 'Папка макетов недоступна для записи: ' . $templatesDir;
        return false;
    }

    $finalPath = $templatesDir . '/' . $layoutKey . '.nav.php';
    $tmpPath = $finalPath . '.tmp';

    $bytes = @file_put_contents($tmpPath, $content);
    if ($bytes === false) {
        $permsDir = @substr(sprintf('%o', @fileperms($templatesDir)), -4);
        $ownerDir = function_exists('posix_getpwuid') ? @posix_getpwuid(@fileowner($templatesDir)) : null;
        $ownerName = is_array($ownerDir) && isset($ownerDir['name']) ? $ownerDir['name'] : 'unknown';
        $error = 'Не удалось сохранить шаблон навигации. Проверь права на запись. ' .
            'dir_perms=' . ($permsDir ?: '????') . ', dir_owner=' . $ownerName . ', dir=' . $templatesDir;
        return false;
    }
    @chmod($tmpPath, 0664);

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($tmpPath));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        @unlink($tmpPath);
        $error = 'Синтаксическая ошибка в шаблоне навигации: ' . trim((string) $lintOutput);
        return false;
    }

    if (is_file($finalPath)) {
        $backupDir = dirname(__DIR__, 3) . '/var/backups/layouts';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0770, true);
            @chmod($backupDir, 0770);
        }
        $backupPath = $backupDir . '/' . $layoutKey . '.nav.php.' . date('YmdHis') . '.bak';
        if (@copy($finalPath, $backupPath)) {
            @chmod($backupPath, 0660);
        }
    }

    if (!rename($tmpPath, $finalPath)) {
        @unlink($tmpPath);
        $error = 'Не удалось обновить шаблон навигации.';
        return false;
    }
    @chmod($finalPath, 0664);

    return true;
}
