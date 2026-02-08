<?php

final class SnippetListView
{
    public static function renderSidebar(array $snippets, array $snippetNames, string $keyword): void
    {
        $createLink = buildAdminUrl(['action' => 'snippet_form']);
        echo '<div class="card shadow-sm border-0">';
        echo '<div class="card-body p-3">';
        echo '<div class="d-flex align-items-center justify-content-between mb-2">';
        echo '<div class="fw-semibold">Врезки</div>';
        echo '<a class="btn btn-icon-square btn-outline-primary" href="' . htmlspecialchars($createLink, ENT_QUOTES, 'UTF-8') . '" title="Добавить врезку" aria-label="Добавить врезку">+</a>';
        echo '</div>';

        if (empty($snippets)) {
            echo '<div class="text-muted">Врезки пока не созданы.</div>';
            echo '</div></div>';
            return;
        }

        echo '<nav class="nav-deep nav-deep-sm nav-deep-light component-tree">';
        echo '<ul class="nav flex-column component-tree-root">';
        foreach ($snippets as $snippet) {
            $snippetLabel = $snippetNames[$snippet] ?? '';
            $link = buildAdminUrl(['action' => 'snippet_list', 'keyword' => $snippet]);
            $liClass = 'nav-item component-tree-item';
            if ($keyword === $snippet) {
                $liClass .= ' is-active is-open';
            }
            echo '<li class="' . $liClass . '">';
            echo '<div class="component-tree-row">';
            echo '<a class="component-tree-link text-decoration-none text-truncate" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '</div>';
            if ($snippetLabel !== '') {
                echo '<div class="text-muted small ms-3 mb-2">' . htmlspecialchars($snippetLabel, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '</nav>';
        echo '</div>';
        echo '</div>';
    }

    public static function renderContent(
        array $snippets,
        string $keyword,
        bool $snippetExists,
        string $snippetName,
        string $content,
        string $error
    ): void {
        echo '<div class="card shadow-sm">';
        echo '<div class="card-body">';
        if ($error !== '') {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($keyword === '' && $snippets === []) {
            echo '<div class="text-muted">Врезки пока не созданы.</div>';
            echo '</div></div>';
            return;
        }

        echo '<form method="post" action="/admin.php?action=snippet_save">';
        echo csrf_token_field();

        echo '<div class="mb-3">';
        echo '<label class="form-label">Ключ</label>';
        if ($snippetExists) {
            echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" readonly>';
        } else {
            echo '<input class="form-control" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" required>';
        }
        echo '</div>';

        echo '<div class="mb-3">';
        echo '<label class="form-label">Название</label>';
        echo '<input class="form-control" name="name" value="' . htmlspecialchars($snippetName, ENT_QUOTES, 'UTF-8') . '">';
        echo '</div>';

        echo '<div class="mb-3 js-code-editor-wrapper">';
        echo '<label class="form-label">Содержимое</label>';
        echo '<textarea class="form-control font-monospace code-editor" id="snippet_content" name="content" rows="16">' . renderTextareaValue($content) . '</textarea>';
        echo '<div class="mt-2 d-flex gap-2">';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-expand" type="button">Развернуть</button>';
        echo '<button class="btn btn-link p-0 link-dotted js-code-editor-collapse d-none" type="button">Свернуть</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="d-flex gap-2 align-items-center">';
        echo '<button class="btn btn-primary" type="submit">Сохранить</button>';
        echo '</form>';
        if ($snippetExists) {
            echo '<form method="post" action="/admin.php?action=snippet_delete" data-confirm="Удалить врезку? Это действие необратимо.">';
            echo csrf_token_field();
            echo '<input type="hidden" name="keyword" value="' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '">';
            echo '<button class="btn btn-outline-danger" type="submit">Удалить</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
