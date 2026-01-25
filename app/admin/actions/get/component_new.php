<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => '_new']));
