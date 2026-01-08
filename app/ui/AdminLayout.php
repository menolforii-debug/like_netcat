<?php

final class AdminLayout
{
    private static bool $withSidebar = true;
    private static bool $layoutRowOpen = false;

    public static function renderHeader(string $title, bool $showSidebar = true): void
    {
        self::$withSidebar = $showSidebar;
        self::$layoutRowOpen = false;
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
        echo "    <link href=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css\" rel=\"stylesheet\">\n";
        echo "</head>\n";

        if (!$showSidebar) {
            echo "<body class=\"bg-light\">\n";
            echo "<main class=\"d-flex align-items-center min-vh-100\">\n";
            echo "<div class=\"container\">\n";
            return;
        }

        echo "<body class=\"bg-light\">\n";
        echo "<div class=\"d-flex flex-column min-vh-100\">\n";
        echo "<header class=\"navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm\">\n";
        echo "<div class=\"container-fluid\">\n";
        echo "<a class=\"navbar-brand fw-semibold\" href=\"/admin.php\">Админка</a>\n";
        echo "<button class=\"navbar-toggler\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#adminTopNav\" aria-controls=\"adminTopNav\" aria-expanded=\"false\" aria-label=\"Toggle navigation\">\n";
        echo "<span class=\"navbar-toggler-icon\"></span>\n";
        echo "</button>\n";
        echo "<div class=\"collapse navbar-collapse\" id=\"adminTopNav\">\n";
        echo "<ul class=\"navbar-nav me-auto mb-2 mb-lg-0\">\n";
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        $action = $action !== '' ? $action : 'dashboard';
        $menu = [
            'dashboard' => ['label' => 'Разделы', 'href' => '/admin.php'],
            'logs' => ['label' => 'Логи', 'href' => '/admin.php?action=logs'],
            'users_list' => ['label' => 'Пользователи', 'href' => '/admin.php?action=users_list'],
            'components' => ['label' => 'Компоненты', 'href' => '/admin.php?action=components'],
            'layouts' => ['label' => 'Макеты дизайна', 'href' => '/admin.php?action=layouts'],
            'sql' => ['label' => 'SQL', 'href' => '/admin.php?action=sql'],
        ];
        foreach ($menu as $key => $item) {
            $active = $action === $key ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . "</a></li>\n";
        }
        echo "</ul>\n";
        echo "<div class=\"d-flex gap-2\">\n";
        echo "<a class=\"btn btn-outline-dark btn-sm\" href=\"/admin.php?action=logout\">Выйти</a>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</header>\n";
        echo "<main class=\"flex-grow-1\">\n";
        echo "<div class=\"container-fluid py-4\">\n";
    }

    public static function openSidebar(): void
    {
        if (!self::$withSidebar || self::$layoutRowOpen) {
            return;
        }

        self::$layoutRowOpen = true;
        echo "<div class=\"row g-4\">\n";
        echo "<aside class=\"col-12 col-lg-3\">\n";
    }

    public static function closeSidebar(): void
    {
        if (!self::$withSidebar || !self::$layoutRowOpen) {
            return;
        }

        echo "</aside>\n";
    }

    public static function openContent(): void
    {
        if (!self::$withSidebar || !self::$layoutRowOpen) {
            return;
        }

        echo "<main class=\"col-12 col-lg-9\">\n";
    }

    public static function closeContent(): void
    {
        if (!self::$withSidebar || !self::$layoutRowOpen) {
            return;
        }

        echo "</main>\n";
        echo "</div>\n";
        self::$layoutRowOpen = false;
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
        }

        echo '<div class="modal fade" id="adminModal" tabindex="-1" aria-hidden="true">';
        echo '<div class="modal-dialog modal-lg modal-dialog-scrollable">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h5 class="modal-title">Загрузка...</h5>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        echo '</div>';
        echo '<div class="modal-body"><div class="text-muted">Загрузка...</div></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-hidden="true">';
        echo '<div class="modal-dialog modal-dialog-centered">';
        echo '<div class="modal-content">';
        echo '<div class="modal-header">';
        echo '<h5 class="modal-title">Подтверждение</h5>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        echo '</div>';
        echo '<div class="modal-body"><div class="text-muted">Подтвердите действие.</div></div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>';
        echo '<button type="button" class="btn btn-danger" data-confirm-action="true">Удалить</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

       
        echo "<script src=\"/assets/sow/js/core.min.js\"></script>\n";
         echo "<script src=\"/assets/sow/js/vendor_bundle.min.js\"></script>\n";
        echo "<script src=\"/assets/admin.js\"></script>\n";

        // CodeMirror core
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js\"></script>\n";

        // ВАЖНО: зависимости для PHP mode (иначе падает с l.indent is not a function)
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js\"></script>\n";
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js\"></script>\n";
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js\"></script>\n";
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js\"></script>\n";
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js\"></script>\n";

        // PHP mode (должен идти ПОСЛЕ зависимостей)
        echo "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js\"></script>\n";

        // Наш инициализатор
        echo "<script src=\"/assets/admin_code_editor.js\"></script>\n";

        echo "</body>\n";
        echo "</html>\n";
    }
}
