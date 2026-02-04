<?php

AdminLog::trimToLimit(500);

$filters = [
    'entity_type' => isset($_GET['entity_type']) ? trim((string) $_GET['entity_type']) : '',
    'action' => isset($_GET['action_filter']) ? trim((string) $_GET['action_filter']) : '',
    'user_id' => isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '',
];
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
if ($limit <= 0) {
    $limit = 100;
}

$entityTypeOptions = ['' => 'Все'];
$actionOptions = ['' => 'Все'];
if (DB::hasTable('admin_log')) {
    $entityRows = DB::fetchAll('SELECT DISTINCT entity_type FROM admin_log ORDER BY entity_type');
    foreach ($entityRows as $row) {
        $value = trim((string) ($row['entity_type'] ?? ''));
        if ($value !== '') {
            $entityTypeOptions[$value] = $value;
        }
    }
    $actionRows = DB::fetchAll('SELECT DISTINCT action FROM admin_log ORDER BY action');
    foreach ($actionRows as $row) {
        $value = trim((string) ($row['action'] ?? ''));
        if ($value !== '') {
            $actionOptions[$value] = $value;
        }
    }
}

$logs = AdminLog::list($filters, $limit);

function renderLogDetails(array $log): string
{
    $raw = (string) ($log['data_json'] ?? '');
    if ($raw === '') {
        return '';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return '<div class="text-muted small">data_json: ' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if (($log['entity_type'] ?? '') === 'admin_error') {
        $message = $decoded['message'] ?? '';
        $file = $decoded['file'] ?? '';
        $line = $decoded['line'] ?? '';
        $trace = $decoded['trace'] ?? '';
        $summary = trim($message !== '' ? $message : 'Ошибка');
        $location = trim($file !== '' ? ($file . ($line !== '' ? ':' . $line : '')) : '');
        $summaryEscaped = htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
        $locationEscaped = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $traceEscaped = htmlspecialchars($trace, ENT_QUOTES, 'UTF-8');

        $details = '<div class="fw-semibold text-danger">' . $summaryEscaped . '</div>';
        if ($locationEscaped !== '') {
            $details .= '<div class="text-muted small">' . $locationEscaped . '</div>';
        }
        if ($traceEscaped !== '') {
            $details .= '<details class="mt-1"><summary class="small text-muted">Trace</summary><pre class="small mb-0">' . $traceEscaped . '</pre></details>';
        }
        return $details;
    }

    return '<div class="text-muted small">data_json: ' . htmlspecialchars(json_encode($decoded, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</div>';
}

AdminLayout::renderHeader('Логи');
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h4 mb-0">Логи администратора</h1>';
echo '<span class="badge bg-secondary">Сначала новые</span>';
echo '</div>';
echo '<div class="row g-4">';
echo '<div class="col-12 col-lg-3">';
echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<form class="row g-3" method="get" action="/admin.php">';
echo '<input type="hidden" name="action" value="logs">';
echo '<div class="col-12"><label class="form-label">Тип сущности</label><select class="form-select" name="entity_type">';
foreach ($entityTypeOptions as $value => $label) {
    $selected = $filters['entity_type'] === $value ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-12"><label class="form-label">Действие</label><select class="form-select" name="action_filter">';
foreach ($actionOptions as $value => $label) {
    $selected = $filters['action'] === $value ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
}
echo '</select></div>';
echo '<div class="col-12"><label class="form-label">ID пользователя</label><input class="form-control" type="text" name="user_id" value="' . htmlspecialchars($filters['user_id'], ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="col-12"><label class="form-label">Лимит</label><input class="form-control" type="number" name="limit" value="' . (int) $limit . '"></div>';
echo '<div class="col-12"><button class="btn btn-primary w-100" type="submit">Фильтр</button></div>';
echo '</form>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-12 col-lg-9">';
if (empty($logs)) {
    echo '<div class="alert alert-light border">Записей лога нет.</div>';
} else {
    echo '<div class="card shadow-sm">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped align-middle mb-0">';
    echo '<thead><tr><th>Дата/время (UTC)</th><th>Пользователь</th><th>Действие</th><th>Сущность</th><th>ID сущности</th><th>IP</th><th>Детали</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['user_id'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['action'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['entity_type'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log['ip'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . renderLogDetails($log) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
}
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
exit;
