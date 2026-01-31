<?php

require __DIR__ . '/../shared/bootstrap.php';
require __DIR__ . '/../shared/core/Auth.php';
require __DIR__ . '/../shared/core/AdminLog.php';
require __DIR__ . '/../shared/core/Permission.php';
require __DIR__ . '/../shared/ui/Layout.php';
require __DIR__ . '/AdminHelpers.php';
require __DIR__ . '/AdminRouter.php';
require __DIR__ . '/ui/AdminLayout.php';
require __DIR__ . '/ui/SectionTree.php';

Auth::start();
