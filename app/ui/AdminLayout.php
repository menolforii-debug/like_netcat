<?php

final class AdminLayout
{
    private static bool $withSidebar = true;

    public static function renderHeader(string $title, bool $showSidebar = true): void
    {
        self::$withSidebar = $showSidebar;
        Layout::renderDocumentStart($title);
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand fw-semibold" href="/admin.php">Dashboard</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="adminNavbar">';
        echo '<ul class="navbar-nav me-auto">';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php">Dashboard</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Components</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Users</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php?action=logs">Logs</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/">Back to site</a></li>';
        echo '</ul>';
        echo '<div class="d-flex">';
        echo '<a class="btn btn-outline-light btn-sm" href="/admin.php?action=logout">Logout</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</nav>';

        if ($showSidebar) {
            echo '<div class="container-fluid py-4">';
            echo '<div class="row g-4">';
            echo '<aside class="col-lg-2 col-md-3">';
            echo '<div class="card shadow-sm">';
            echo '<div class="card-header bg-white fw-semibold">Navigation</div>';
            echo '<div class="list-group list-group-flush">';
            echo '<a class="list-group-item list-group-item-action" href="/admin.php">Dashboard</a>';
            echo '<a class="list-group-item list-group-item-action" href="#">Components</a>';
            echo '<a class="list-group-item list-group-item-action" href="#">Users</a>';
            echo '<a class="list-group-item list-group-item-action" href="/admin.php?action=logs">Logs</a>';
            echo '<a class="list-group-item list-group-item-action" href="/">Back to site</a>';
            echo '</div>';
            echo '</div>';
            echo '</aside>';
            echo '<main class="col-lg-10 col-md-9">';
        } else {
            echo '<div class="container py-5">';
            echo '<main>';
        }
    }

    public static function renderFooter(): void
    {
        if (self::$withSidebar) {
            echo '</main>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '</main>';
            echo '</div>';
        }

        Layout::renderDocumentEnd();
    }
}
