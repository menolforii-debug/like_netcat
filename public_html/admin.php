<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (!Auth::check('editor')) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

echo 'Admin interface (CMS mode)';
