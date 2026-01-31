<?php

if (!defined('APP_RUNTIME')) {
    define('APP_RUNTIME', 'admin');
}
require __DIR__ . '/../shared/runtime_guard.php';

// shared runtime
require __DIR__ . '/../shared/bootstrap.php';
require __DIR__ . '/../shared/core/Auth.php';
require __DIR__ . '/../shared/core/AdminLog.php';
require __DIR__ . '/../shared/core/Permission.php';
require __DIR__ . '/../shared/ui/LayoutCatalog.php';
require __DIR__ . '/../shared/ui/Pagination.php';

// admin runtime
require __DIR__ . '/AdminHelpers.php';
require __DIR__ . '/AdminRouter.php';
require __DIR__ . '/ui/AdminLayout.php';
require __DIR__ . '/ui/SectionTree.php';

Auth::start();
