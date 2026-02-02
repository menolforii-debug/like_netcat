<?php

if (isAjaxRequest()) {
    adminOk('', [], true, [
        'redirect' => buildAdminUrl(['action' => 'components']),
    ]);
}

redirectTo(buildAdminUrl(['action' => 'components']));
