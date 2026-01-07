<?php

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function buildAdminUrl(array $params = []): string
{
    return '/admin.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

/**
 * Render flash/notice/error as SOW Toast instead of inline bootstrap alert.
 * Uses $.SOW.core.toast.show(type, title, body, position, delay, fill)
 *
 * Includes JS-level dedupe to prevent duplicated toasts when renderAlert()
 * is called multiple times with the same message/type.
 */
function renderAlert(?string $message, string $type = 'info'): void
{
    if ($message === null || $message === '') {
        return;
    }

    static $rendered = [];
    $dedupeKey = $type . '|' . $message;
    if (isset($rendered[$dedupeKey])) {
        return;
    }
    $rendered[$dedupeKey] = true;

    $t = strtolower(trim($type));
    $toastType = match ($t) {
        'error', 'danger' => 'danger',
        'success' => 'success',
        'warning', 'warn' => 'warning',
        default => 'info',
    };

    // JS-safe strings
    $msgJs = json_encode((string) $message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $typeJs = json_encode((string) $toastType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $posJs = json_encode('top-center', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo "<script>
(function() {
  var msg = {$msgJs};
  var toastType = {$typeJs};
  var pos = {$posJs};
  var delay = 3500;
  var fill = true;

  // ---- DEDUPE (per page load) ----
  // key: type + message
  try {
    window.__CMS_TOASTS_SHOWN__ = window.__CMS_TOASTS_SHOWN__ || {};
    var key = String(toastType) + '|' + String(msg);
    if (window.__CMS_TOASTS_SHOWN__[key]) {
      return;
    }
    window.__CMS_TOASTS_SHOWN__[key] = 1;
  } catch (e) {
    // if something goes wrong, do not block the toast
  }

  function showOnce() {
    try {
      if (!window.jQuery) return false;
      var \$ = window.jQuery;
      if (!\$.SOW || !\$.SOW.core || !\$.SOW.core.toast || typeof \$.SOW.core.toast.show !== 'function') return false;
      \$.SOW.core.toast.show(toastType, '', msg, pos, delay, fill);
      return true;
    } catch (e) {
      return false;
    }
  }

  function tryLater(attempt) {
    if (showOnce()) return;
    if (attempt >= 6) return;
    setTimeout(function() { tryLater(attempt + 1); }, 180);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { tryLater(0); });
  } else {
    tryLater(0);
  }
})();
</script>";
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfTokenField(): string
{
    $token = csrfToken();

    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrfToken(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
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
    return Utils::decodeExtra($row);
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
    return $repo->buildPath($sectionId);
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
        $line = Utils::normalizeHost(trim($line));
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

function renderComponentViewTemplate(string $listTpl, string $singleTpl): string
{
    $content = "<?php\n";
    $content .= "/** GENERATED FILE. Do not edit manually. */\n";
    $content .= "if (!isset(\$isSingle)) { \$isSingle = false; }\n";
    $content .= "if (\$isSingle && isset(\$object) && is_array(\$object)) {\n";
    $content .= "?>\n";
    $content .= $singleTpl . "\n";
    $content .= "<?php\n";
    $content .= "} else {\n";
    $content .= "?>\n";
    $content .= $listTpl . "\n";
    $content .= "<?php\n";
    $content .= "}\n";

    return $content;
}

function writeComponentViewTemplate(string $componentKey, string $viewName, string $listTpl, string $singleTpl, ?string &$error = null): bool
{
    $root = dirname(__DIR__, 2);
    $templatesDir = $root . '/templates/' . $componentKey;
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0770, true);
        @chmod($templatesDir, 0770);
    }

    $finalPath = $templatesDir . '/' . $viewName . '.php';
    $tmpPath = $finalPath . '.tmp';
    $content = renderComponentViewTemplate($listTpl, $singleTpl);

    if (file_put_contents($tmpPath, $content) === false) {
        $error = 'Не удалось сохранить шаблон.';
        return false;
    }
    @chmod($tmpPath, 0660);

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($tmpPath));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        @unlink($tmpPath);
        $error = 'Синтаксическая ошибка в шаблоне: ' . trim((string) $lintOutput);
        return false;
    }

    if (is_file($finalPath)) {
        $backupDir = $root . '/var/backups/templates/' . $componentKey;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0770, true);
            @chmod($backupDir, 0770);
        }
        $backupPath = $backupDir . '/' . $viewName . '.php.' . date('YmdHis') . '.bak';
        if (@copy($finalPath, $backupPath)) {
            @chmod($backupPath, 0660);
        }
    }

    if (!rename($tmpPath, $finalPath)) {
        @unlink($tmpPath);
        $error = 'Не удалось обновить шаблон.';
        return false;
    }
    @chmod($finalPath, 0660);

    return true;
}

function layoutTemplatesDir(): string
{
    return dirname(__DIR__, 2) . '/app/ui/layouts';
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

function writeLayoutTemplate(string $layoutKey, string $content, ?string &$error = null): bool
{
    if (!layoutKeyIsValid($layoutKey)) {
        $error = 'Некорректный ключ макета.';
        return false;
    }

    $templatesDir = layoutTemplatesDir();
    if (!is_dir($templatesDir)) {
        mkdir($templatesDir, 0770, true);
        @chmod($templatesDir, 0770);
    }

    $finalPath = $templatesDir . '/' . $layoutKey . '.php';
    $tmpPath = $finalPath . '.tmp';

    if (file_put_contents($tmpPath, $content) === false) {
        $error = 'Не удалось сохранить макет.';
        return false;
    }
    @chmod($tmpPath, 0660);

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($tmpPath));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        @unlink($tmpPath);
        $error = 'Синтаксическая ошибка в макете: ' . trim((string) $lintOutput);
        return false;
    }

    if (is_file($finalPath)) {
        $backupDir = dirname(__DIR__, 2) . '/var/backups/layouts';
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
    @chmod($finalPath, 0660);

    return true;
}

function syncComponentViewsJson(int $componentId): void
{
    if (!DB::hasTable('component_views')) {
        return;
    }

    $viewRepo = new ComponentViewRepo();
    $views = $viewRepo->listNamesForComponent($componentId);
    $stmt = DB::pdo()->prepare('UPDATE components SET views_json = :views_json WHERE id = :id');
    $stmt->execute([
        'views_json' => json_encode($views, JSON_UNESCAPED_UNICODE),
        'id' => $componentId,
    ]);
}
