<?php

final class AdminLayout
{
    private static bool $withSidebar = true;

    public static function renderHeader(string $title, bool $showSidebar = true): void
    {
        self::$withSidebar = false;
        Layout::renderDocumentStart($title);
        echo '<style>';
        echo '.content-wrapper{background:#f5f6f8;}';
        echo '.admin-navbar{background:#2d8ac9;}';
        echo '.admin-navbar .navbar-brand,.admin-navbar .nav-link,.admin-navbar .btn{color:#fff;}';
        echo '.admin-navbar .nav-link:hover{color:#e6f2ff;}';
        echo '.admin-shell{display:flex;min-height:calc(100vh - 56px);}';
        echo '.admin-sidebar{width:280px;background:#fff;border-right:1px solid #e5e5e5;}';
        echo '.admin-sidebar .list-group-item{border:0;border-radius:0;font-size:13px;}';
        echo '.admin-content{flex:1;background:#fff;padding:20px 24px;}';
        echo '.admin-heading{font-size:20px;font-weight:500;margin:0 0 16px;}';
        echo '</style>';
        echo '<nav class="navbar navbar-expand-lg admin-navbar">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand fw-semibold" href="/admin.php">Панель</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Переключить навигацию">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="adminNavbar">';
        echo '<ul class="navbar-nav me-auto">';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php">Панель</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=components">Компоненты</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=user">Пользователи</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=logs">Логи</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/">На сайт</a></li>';
        echo '</ul>';
        echo '<div class="d-flex">';
        echo '<a class="btn btn-outline-light btn-sm" href="/admin.php?action=logout">Выйти</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';

        echo '<div class="admin-shell">';
        echo '<main>';
    }

    public static function renderFooter(): void
    {
        echo '</main>';
        echo '</div>';

        Layout::renderDocumentEnd();
    }
}
