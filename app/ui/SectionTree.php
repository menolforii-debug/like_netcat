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
            $html .= '<li class="list-group-item">';
            $html .= '<a class="text-decoration-none' . ($isActive ? ' fw-semibold' : '') . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a>';
            if (!empty($node['children'])) {
                $html .= '<div class="ms-3 mt-2">' . self::renderTree($node['children'], $currentId) . '</div>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
