<?php
/** @var array $ctx */
/** @var callable $body */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];

Layout::renderDocumentStart($title, $meta);
Layout::renderNavbar((string) ($site['title'] ?? 'CMS'), [
    ['label' => 'Админ', 'href' => '/admin.php'],
]);

echo '<main class="container py-4">';
$body();
echo '</main>';

Layout::renderDocumentEnd();
