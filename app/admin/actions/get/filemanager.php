<?php

if (!Auth::isAdmin()) {
    redirectTo(buildAdminUrl(['error' => 'Недостаточно прав']));
}

AdminLayout::renderHeader('Файлы');
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h1 class="h4 mb-0">Файлы</h1>';
echo '</div>';
echo '<div class="card shadow-sm">';
echo '<div class="card-body p-0">';
 
echo '<iframe title="Файловый менеджер" src="/admin.php?action=filemanager_embed" style="width:100%;min-height:75vh;border:0;"></iframe>';
 
echo '</div>';
echo '</div>';
AdminLayout::renderFooter();
