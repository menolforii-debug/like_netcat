<?php

$message = 'Многосайтовость отключена';
if (isAjaxRequest()) {
    jsonResponse(['ok' => false, 'error' => $message]);
}
redirectTo(buildAdminUrl(['error' => $message]));
