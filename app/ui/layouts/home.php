<?php
/** @var array $ctx */
/** @var callable $body */

$title = (string) ($ctx['title'] ?? '');
$meta = $ctx['meta'] ?? [];
$site = $ctx['site'] ?? [];
$section = $ctx['section'] ?? null;

Layout::renderDocumentStart($title, $meta);
Layout::renderNavbar((string) ($site['title'] ?? 'CMS'), [
    ['label' => 'Админ', 'href' => '/admin.php'],
]);

echo '<header class="bg-white border-bottom">';
echo '<div class="container py-5">';
echo '<div class="d-flex flex-column gap-2">';
echo '<span class="text-uppercase text-muted small">Welcome</span>';
echo '<h1 class="display-6 mb-0">' . htmlspecialchars((string) ($section['title'] ?? $site['title'] ?? 'CMS'), ENT_QUOTES, 'UTF-8') . '</h1>';
if (!empty($site['english_name'])) {
    echo '<div class="text-muted">' . htmlspecialchars((string) $site['english_name'], ENT_QUOTES, 'UTF-8') . '</div>';
}
echo '</div>';
echo '</div>';
echo '</header>';

echo '<main class="container py-4">';
$body();
echo '</main>';

Layout::renderDocumentEnd();
