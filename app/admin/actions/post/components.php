<?php

if (isAjaxRequest()) {
    jsonResponse([
        'ok' => true,
        'redirect' => buildAdminUrl(['action' => 'components']),
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components']));
