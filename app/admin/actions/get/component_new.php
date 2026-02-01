<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl([]));
}

redirectTo(buildAdminUrl(['action' => 'components', 'component_id' => '_new']));
