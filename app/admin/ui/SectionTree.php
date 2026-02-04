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

        $html = '';
        $html .= '<nav class="nav-deep nav-deep-sm nav-deep-light section-tree">';
        $html .= '<ul class="nav flex-column section-tree-root">';

        foreach ($nodes as $node) {
            $html .= self::renderNode($node, $currentId, $expandedRootId, $canManage);
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }

    private static function renderNode(array $node, ?int $currentId, ?int $expandedRootId, bool $canManage): string
    {
        $id = (int) $node['id'];
        $title = htmlspecialchars((string) $node['title'], ENT_QUOTES, 'UTF-8');

        $hasChildren = !empty($node['children']);
        $isActive = $currentId !== null && $id === $currentId;

        $hasSelected = $currentId !== null && self::hasSelected((int) $currentId, $node);
        $forceExpanded = $expandedRootId !== null && $id === (int) $expandedRootId;
        $expanded = ($isActive || $hasSelected || $forceExpanded);

        $repo = new SectionRepo();
        $sitePath = $repo->buildPath($id);

        $isSystemRoot = isset($node['english_name'])
            && in_array($node['english_name'], ['index', '404'], true);
        $isSiteRoot = $node['parent_id'] === null && (int) $node['site_id'] === $id;

        $link = buildAdminUrl(['section_id' => $id]);

        // "1. Главная"
        $label = $id . '. ' . $title;

        // Выделение только жирным
        $labelClass = $isActive ? 'fw-bold' : 'fw-normal';

        $liClass = 'nav-item section-tree-item';
        if ($expanded) {
            $liClass .= ' is-open';
        }

        // Chevron (кликабельный; JS будет раскрывать/сворачивать)
        if ($hasChildren) {
            $toggleBtn = '<button type="button" class="btn btn-icon-square btn-outline-secondary section-tree-toggle js-tree-toggle"'
                . ' data-node-id="' . $id . '" aria-label="Свернуть/развернуть">'
                . '<span class="section-tree-chevron" aria-hidden="true"></span>'
                . '</button>';
        } else {
            $toggleBtn = '<span class="btn-icon-square section-tree-toggle-spacer" aria-hidden="true"></span>';
        }

        // Кнопки действий (показываем по hover ТОЛЬКО текущей строки)
        $actions = '<div class="section-tree-actions" aria-hidden="true">';

        if ($canManage) {
            $actions .= '<button type="button" class="btn btn-icon-square btn-outline-secondary" data-modal-url="'
                . htmlspecialchars(buildAdminUrl(['action' => 'section_form', 'parent_id' => $id]), ENT_QUOTES, 'UTF-8')
                . '" title="Добавить раздел" aria-label="Добавить раздел">+</button>';
        }

        $actions .= '<a class="btn btn-icon-square btn-outline-secondary" href="'
            . htmlspecialchars($sitePath, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" title="Открыть на сайте" aria-label="Открыть на сайте">↗</a>';

        if ($canManage && !$isSystemRoot && !$isSiteRoot) {
            $actions .= '<form method="post" action="/admin.php?action=section_delete" class="m-0" data-confirm="Удалить этот раздел?">';
            $actions .= csrf_token_field();
            $actions .= '<input type="hidden" name="id" value="' . $id . '">';
            $actions .= '<button class="btn btn-icon-square btn-outline-danger" type="submit" title="Удалить" aria-label="Удалить">×</button>';
            $actions .= '</form>';
        }

        $actions .= '</div>';

        $html = '<li class="' . $liClass . '" data-node-id="' . $id . '">';

        // ВАЖНО: строка НЕ .nav-link (чтобы не было оверлея и клики работали)
        $html .= '<div class="section-tree-row">';

        // Основная ссылка — именно она .nav-link (SOW/Bootstrap подсветка/типографика)
        $html .= '<a class="nav-link section-tree-link text-decoration-none text-truncate ' . $labelClass . '" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">';
        $html .= $label;
        $html .= '</a>';

        // Правая часть: actions (появляются на hover строки) + chevron (всегда)
        $html .= '<div class="section-tree-right">';
        $html .= $actions;
        $html .= $toggleBtn;
        $html .= '</div>';

        $html .= '</div>'; // row

        if ($hasChildren) {
            $html .= '<ul class="nav flex-column section-tree-children">';
            foreach ($node['children'] as $child) {
                $html .= self::renderNode($child, $currentId, $expandedRootId, $canManage);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';

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
