<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

redirectTo(buildAdminUrl(['error' => 'Многосайтовость отключена']));
