<?php

require __DIR__ . '/../app/admin/bootstrap.php';


$maintenanceLockPath = __DIR__ . '/../var/restore.maintenance.lock';
if (is_file($maintenanceLockPath)) {
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
    if ($method === 'POST' && $action !== 'backups_restore') {
        http_response_code(503);
        header('Retry-After: 120');
        echo 'Admin is in read-only mode during backup restore.';
        return;
    }
}

AdminRouter::run();
