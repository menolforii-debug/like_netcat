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

function renderAlert(?string $message, string $type = 'info'): void
{
    if ($message === null || $message === '') {
        return;
    }

    $class = $type === 'error' ? 'danger' : $type;
    echo '<div class="alert alert-' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
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
    $decoded = json_decode((string) ($row['extra_json'] ?? '{}'), true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
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
    $segments = [];
    $currentId = $sectionId;

    while ($currentId !== null) {
        $section = $repo->findById($currentId);
        if ($section === null) {
            break;
        }

        if (!empty($section['english_name'])) {
            $segments[] = $section['english_name'];
        }

        $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
    }

    if (empty($segments)) {
        return '/';
    }

    return '/' . implode('/', array_reverse($segments)) . '/';
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
        $line = trim($line);
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
        mkdir($templatesDir, 0777, true);
    }

    $finalPath = $templatesDir . '/' . $viewName . '.php';
    $tmpPath = $finalPath . '.tmp';
    $content = renderComponentViewTemplate($listTpl, $singleTpl);

    if (file_put_contents($tmpPath, $content) === false) {
        $error = 'Не удалось сохранить шаблон.';
        return false;
    }

    $lintOutput = @shell_exec('php -l ' . escapeshellarg($tmpPath));
    if ($lintOutput !== null && stripos($lintOutput, 'No syntax errors detected') === false) {
        @unlink($tmpPath);
        $error = 'Синтаксическая ошибка в шаблоне: ' . trim((string) $lintOutput);
        return false;
    }

    if (is_file($finalPath)) {
        $backupDir = $root . '/var/backups/templates/' . $componentKey;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }
        $backupPath = $backupDir . '/' . $viewName . '.php.' . date('YmdHis') . '.bak';
        @copy($finalPath, $backupPath);
    }

    if (!rename($tmpPath, $finalPath)) {
        @unlink($tmpPath);
        $error = 'Не удалось обновить шаблон.';
        return false;
    }

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
