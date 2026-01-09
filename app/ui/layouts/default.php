<?php
/** @var array $ctx */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];
$infoblocksHtml = (string) ($ctx['infoblocks_html'] ?? '');

Layout::renderDocumentStart($title, $meta);
Layout::renderNavbar((string) ($site['title'] ?? 'CMS'), [
    ['label' => 'Админ', 'href' => '/admin.php'],
]);

echo '<main class="container py-4">';
echo $infoblocksHtml;
echo '</main>';

Layout::renderDocumentEnd();
