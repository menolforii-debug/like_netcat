<?php

final class SectionTree
{
    public static function render(array $sections, ?int $currentId = null, ?int $expandedRootId = null): string
    {
        $tree = self::buildTree($sections);
        return self::renderTree($tree, $currentId, $expandedRootId);
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
            if ($parentId === null && (int) $section['site_id'] !== (int) $section['id']) {
                $siteId = (int) $section['site_id'];
                if ($siteId > 0 && isset($items[$siteId])) {
                    $parentId = $siteId;
                }
            }

            if ($parentId !== null && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$items[$id];
            } else {
                $root[] = &$items[$id];
            }
        }

        return $root;
    }

    private static function renderTree(array $nodes, ?int $currentId, ?int $expandedRootId): string
    {
        if (empty($nodes)) {
            return '<div class="text-muted">Разделов нет.</div>';
        }

        $canManage = Auth::isAdmin();
        $html = '<div class="accordion" id="sectionTreeAccordion">';

        $index = 0;
        foreach ($nodes as $node) {
            $index++;
            $html .= self::renderNode($node, $currentId, $expandedRootId, $canManage, 'sectionTreeAccordion', (string) $index);
        }

        $html .= '</div>';

        return $html;
    }

    private static function renderNode(array $node, ?int $currentId, ?int $expandedRootId, bool $canManage, string $parentId, string $suffix): string
    {
        $isActive = $currentId !== null && (int) $node['id'] === $currentId;
        $title = htmlspecialchars((string) $node['title'], ENT_QUOTES, 'UTF-8');
        $link = '/admin.php?section_id=' . (int) $node['id'];
        $repo = new SectionRepo();
        $sitePath = buildSectionPathFromId($repo, (int) $node['id']);
        $isSystemRoot = $node['parent_id'] === null
            && isset($node['english_name'])
            && in_array($node['english_name'], ['index', '404'], true);
        $hasChildren = !empty($node['children']);
        $collapseId = 'sectionTreeCollapse' . $suffix . '_' . (int) $node['id'];
        $headingId = 'sectionTreeHeading' . $suffix . '_' . (int) $node['id'];
        $hasSelected = $currentId !== null && self::hasSelected((int) $currentId, $node);
        $forceExpanded = $expandedRootId !== null && (int) $node['id'] === $expandedRootId;
        $expanded = ($isActive || $hasSelected || $forceExpanded) ? 'true' : 'false';
        $show = ($isActive || $hasSelected || $forceExpanded) ? ' show' : '';

        $html = '<div class="accordion-item border-0">';
        $html .= '<div class="accordion-header" id="' . $headingId . '">';
        $html .= '<div class="d-flex align-items-center gap-2">';
        if ($hasChildren) {
            $html .= '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="' . $expanded . '" aria-controls="' . $collapseId . '" title="Развернуть"><i class="bi bi-chevron-down"></i></button>';
        } else {
            $html .= '<span class="btn btn-sm btn-outline-secondary invisible"></span>';
        }
        $html .= '<span class="text-muted small me-2">#' . (int) $node['id'] . '</span>';
        $html .= '<a class="text-decoration-none flex-grow-1' . ($isActive ? ' fw-semibold' : '') . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a>';

        if ($canManage) {
            $html .= '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(buildAdminUrl(['action' => 'section_new', 'parent_id' => (int) $node['id']]), ENT_QUOTES, 'UTF-8') . '" title="Добавить раздел"><i class="bi bi-plus"></i></a>';
        }

        $html .= '<a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($sitePath, ENT_QUOTES, 'UTF-8') . '" target="_blank" title="Открыть на сайте"><i class="bi bi-box-arrow-up-right"></i></a>';

        if ($canManage && !$isSystemRoot) {
            $html .= '<form method="post" action="/admin.php?action=section_delete" class="m-0" onsubmit="return confirm(\'Удалить этот раздел?\')">';
            $html .= csrfTokenField();
            $html .= '<input type="hidden" name="id" value="' . (int) $node['id'] . '">';
            $html .= '<button class="btn btn-sm btn-outline-danger" type="submit" title="Удалить"><i class="bi bi-trash"></i></button>';
            $html .= '</form>';
        }

        $html .= '</div>';
        $html .= '</div>';

        if ($hasChildren) {
            $html .= '<div id="' . $collapseId . '" class="accordion-collapse collapse' . $show . '" aria-labelledby="' . $headingId . '" data-bs-parent="#' . $parentId . '">';
            $html .= '<div class="accordion-body py-2 ps-4">';
            $html .= '<div class="accordion" id="' . $collapseId . '-inner">';
            $index = 0;
            foreach ($node['children'] as $child) {
                $index++;
                $html .= self::renderNode($child, $currentId, $expandedRootId, $canManage, $collapseId . '-inner', $suffix . '_' . (string) $index);
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    private static function hasSelected(int $selectedId, array $node): bool
    {
        if ((int) ($node['id'] ?? 0) === $selectedId) {
            return true;
        }

        if (empty($node['children'])) {
            return false;
        }

        foreach ($node['children'] as $child) {
            if (self::hasSelected($selectedId, $child)) {
                return true;
            }
        }

        return false;
    }
}
