<?php

final class AdminLayout
{
    public static function renderHeader(string $title, array $user, array $navLinks = []): void
    {
        Layout::renderDocumentStart($title);
        echo '<nav class="navbar navbar-expand-lg bg-dark navbar-dark">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand" href="/admin.php">CMS Admin</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="adminNavbar">';
        echo '<ul class="navbar-nav me-auto">';
        foreach ($navLinks as $link) {
            $label = htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars((string) ($link['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
            $active = !empty($link['active']) ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $href . '">' . $label . '</a></li>';
        }
        echo '</ul>';
        echo '<div class="d-flex align-items-center text-white">';
        $login = htmlspecialchars((string) ($user['login'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<span class="me-3">' . $login . '</span>';
        echo '<a class="btn btn-outline-light btn-sm" href="/admin.php?action=logout">Выйти</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';
        echo '<div class="container-fluid mt-4">';
    }

    public static function renderFooter(): void
    {
        echo '</div>';
        Layout::renderDocumentEnd();
    }
}
