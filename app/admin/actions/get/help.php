<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

$helpPath = dirname(__DIR__, 3) . '/docs/help.md';
$content = is_file($helpPath) ? file_get_contents($helpPath) : null;
if ($content === false || $content === null) {
    $content = "Файл справки не найден.\n";
}

AdminLayout::renderHeader('Справка');

echo '<div class="card shadow-sm">';
echo '<div class="card-body">';
echo '<h1 class="h4 mb-3">Справка</h1>';
echo '<pre class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap;">';
echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
echo '</pre>';
echo '</div>';
echo '</div>';

AdminLayout::renderFooter();
