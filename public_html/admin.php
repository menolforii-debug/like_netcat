<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/admin/AdminHelpers.php';
require __DIR__ . '/../app/admin/AdminRouter.php';

AdminRouter::run();
