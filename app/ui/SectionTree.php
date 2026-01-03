<?php

final class SectionTree
{
    public static function render(array $sections, ?int $currentId = null): string
    {
        $tree = self::buildTree($sections);
        return self::renderTree($tree, $currentId);
    }

    private static function buildTree(array $sections): array
    {
        $items = [];
        foreach ($sections as $section) {
            $section['children'] = [];
            $items[(int) $section['id']] = $section;
        }

        $root = [];
        foreach ($items as $id => $section) {
            $parentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;

            if ($parentId !== null && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$items[$id];
            } else {
                $root[] = &$items[$id];
            }
        }

        return $root;
    }

    private static function renderTree(array $nodes, ?int $currentId): string
    {
        if (empty($nodes)) {
            return '<div class="text-muted">Разделов нет.</div>';
        }

        $html = '<ul class="list-group list-group-flush">';

        foreach ($nodes as $node) {
            $isActive = $currentId !== null && (int) $node['id'] === $currentId;
            $title = htmlspecialchars((string) $node['title'], ENT_QUOTES, 'UTF-8');
            $link = '/admin.php?section_id=' . (int) $node['id'];
            $isSystemRoot = $node['parent_id'] === null
                && isset($node['english_name'])
                && in_array($node['english_name'], ['index', '404'], true);

            $html .= '<li class="list-group-item">';

            $html .= '<div class="d-flex align-items-center gap-2">';
            $html .= '<a class="text-decoration-none flex-grow-1' . ($isActive ? ' fw-semibold' : '') . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a>';

            $html .= '<form method="post" action="/admin.php?action=section_create" class="m-0">';
            $html .= '<input type="hidden" name="parent_id" value="' . (int) $node['id'] . '">';
            $html .= '<button class="btn btn-sm btn-outline-primary" type="submit">+</button>';
            $html .= '</form>';

            if (!$isSystemRoot) {
                $html .= '<form method="post" action="/admin.php?action=section_delete" class="m-0" onsubmit="return confirm(\'Удалить этот раздел?\')">';
                $html .= '<input type="hidden" name="id" value="' . (int) $node['id'] . '">';
                $html .= '<button class="btn btn-sm btn-outline-danger" type="submit">🗑</button>';
                $html .= '</form>';
            }

            $html .= '</div>';

            if (!empty($node['children'])) {
                $html .= '<div class="ms-3 mt-2">' . self::renderTree($node['children'], $currentId) . '</div>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

