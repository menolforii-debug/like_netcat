<?php

final class AdminLayout
{
    private static bool $withSidebar = true;

    public static function renderHeader(string $title, bool $showSidebar = true): void
    {
        self::$withSidebar = false;
        Layout::renderDocumentStart($title);
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand fw-semibold" href="/admin.php">Панель</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Переключить навигацию">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="adminNavbar">';
        echo '<ul class="navbar-nav me-auto">';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php">Панель</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Компоненты</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=users">Пользователи</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=logs">Логи</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/">На сайт</a></li>';
        echo '</ul>';
        echo '<div class="d-flex">';
        echo '<a class="btn btn-outline-light btn-sm" href="/admin.php?action=logout">Выйти</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';

        echo '<div class="container-fluid py-4">';
        echo '<main>';
    }

    public static function renderFooter(): void
    {
        echo '</main>';
        echo '</div>';

        Layout::renderDocumentEnd();
    }
}
