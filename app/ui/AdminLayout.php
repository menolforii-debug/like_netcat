<?php

final class AdminLayout
{
    public static function renderHeader(string $title): void
    {
        Layout::renderDocumentStart($title);
        echo '<nav class="navbar navbar-expand-lg bg-dark navbar-dark">';
        echo '<div class="container-fluid">';
        echo '<a class="navbar-brand" href="/admin.php">Dashboard</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
        echo '<div class="collapse navbar-collapse" id="adminNavbar">';
        echo '<ul class="navbar-nav me-auto">';
        echo '<li class="nav-item"><a class="nav-link" href="/admin.php">Dashboard</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Components</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Users</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#">Logs</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="/">Back to site</a></li>';
        echo '</ul>';
        echo '<div class="d-flex">';
        echo '<a class="btn btn-outline-light btn-sm" href="/admin.php?action=logout">Logout</a>';
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
