<?php

final class AdminLayout
{
    private static bool $withSidebar = true;

    public static function renderHeader(string $title, bool $showSidebar = true): void
    {
        self::$withSidebar = $showSidebar;
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        echo "<!doctype html>\n";
        echo "<html lang=\"ru\">\n";
        echo "<head>\n";
        echo "    <meta charset=\"utf-8\">\n";
        echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        echo "    <title>{$titleEscaped}</title>\n";
        echo "    <link href=\"/assets/sow/css/vendor_bundle.min.css\" rel=\"stylesheet\">\n";
        echo "    <link href=\"/assets/sow/css/core.min.css\" rel=\"stylesheet\">\n";
        echo "    <link href=\"/assets/admin.css\" rel=\"stylesheet\">\n";
        echo "</head>\n";

        if (!$showSidebar) {
            echo "<body class=\"bg-light\">\n";
            echo "<main class=\"d-flex align-items-center min-vh-100\">\n";
            echo "<div class=\"container\">\n";
            return;
        }

        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        $action = $action !== '' ? $action : 'dashboard';
        $menu = [
            'dashboard' => ['label' => 'Админка', 'href' => '/admin.php'],
            'logs' => ['label' => 'Логи', 'href' => '/admin.php?action=logs'],
            'users' => ['label' => 'Пользователи', 'href' => '/admin.php?action=users'],
            'components' => ['label' => 'Компоненты', 'href' => '/admin.php?action=components'],
        ];

        echo "<body class=\"bg-light\">\n";
        echo "<div class=\"d-flex min-vh-100\">\n";
        echo "<aside class=\"bg-dark text-white p-3\" style=\"width: 260px;\">\n";
        echo "<div class=\"h5 mb-4\">CMS Admin</div>\n";
        echo "<nav class=\"nav flex-column gap-1\">\n";
        foreach ($menu as $key => $item) {
            $active = $action === $key ? ' active' : '';
            echo '<a class="nav-link text-white' . $active . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . "</a>\n";
        }
        echo "</nav>\n";
        echo "</aside>\n";
        echo "<div class=\"flex-grow-1 d-flex flex-column\">\n";
        echo "<header class=\"navbar navbar-light bg-white border-bottom shadow-sm\">\n";
        echo "<div class=\"container-fluid\">\n";
        echo "<span class=\"navbar-brand mb-0 h6\">Админка</span>\n";
        echo "<div class=\"d-flex gap-2\">\n";
        echo "<a class=\"btn btn-outline-secondary btn-sm\" href=\"/\">На сайт</a>\n";
        echo "<a class=\"btn btn-outline-dark btn-sm\" href=\"/admin.php?action=logout\">Выйти</a>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</header>\n";
        echo "<main class=\"flex-grow-1\">\n";
        echo "<div class=\"container-fluid py-4\">\n";
    }

    public static function renderFooter(): void
    {
        if (!self::$withSidebar) {
            echo "</div>\n";
            echo "</main>\n";
        } else {
            echo "</div>\n";
            echo "</main>\n";
            echo "</div>\n";
            echo "</div>\n";
        }

        echo "<script src=\"/assets/sow/js/vendor_bundle.min.js\"></script>\n";
        echo "<script src=\"/assets/sow/js/core.min.js\"></script>\n";
        echo "<script src=\"/assets/admin.js\"></script>\n";
        echo "</body>\n";
        echo "</html>\n";
    }
}
