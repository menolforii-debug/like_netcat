<?php

$filters = [
    'entity_type' => isset($_GET['entity_type']) ? trim((string) $_GET['entity_type']) : '',
    'action' => isset($_GET['action_filter']) ? trim((string) $_GET['action_filter']) : '',
    'user_id' => isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '',
];
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
if ($limit <= 0) {
    $limit = 100;
}

$logs = AdminLog::list($filters, $limit);

AdminLayout::renderHeader('Логи');
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h1 class="h4 mb-0">Логи администратора</h1>';
echo '<span class="badge bg-secondary">Сначала новые</span>';
echo '</div>';
echo '<div class="card shadow-sm mb-4">';
echo '<div class="card-body">';
echo '<form class="row g-3" method="get" action="/admin.php">';
echo '<input type="hidden" name="action" value="logs">';
echo '<div class="col-md-3"><label class="form-label">Тип сущности</label><input class="form-control" type="text" name="entity_type" value="' . htmlspecialchars($filters['entity_type'], ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="col-md-3"><label class="form-label">Действие</label><input class="form-control" type="text" name="action_filter" value="' . htmlspecialchars($filters['action'], ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="col-md-2"><label class="form-label">ID пользователя</label><input class="form-control" type="text" name="user_id" value="' . htmlspecialchars($filters['user_id'], ENT_QUOTES, 'UTF-8') . '"></div>';
echo '<div class="col-md-2"><label class="form-label">Лимит</label><input class="form-control" type="number" name="limit" value="' . (int) $limit . '"></div>';
echo '<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Фильтр</button></div>';
echo '</form>';
echo '</div>';
echo '</div>';

if (empty($logs)) {
    echo '<div class="alert alert-light border">Записей лога нет.</div>';
} else {
    echo '<div class="card shadow-sm">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped align-middle mb-0">';
    echo '<thead><tr><th>Дата/время (UTC)</th><th>Пользователь</th><th>Действие</th><th>Сущность</th><th>ID сущности</th><th>IP</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['user_id'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['action'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log['entity_type'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($log['ip'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
}

AdminLayout::renderFooter();
exit;
