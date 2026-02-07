<?php
// Здесь можно описать функции построения меню или другие helper-функции для макета.
/** @var array $ctx */
/** @var callable $body */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];
