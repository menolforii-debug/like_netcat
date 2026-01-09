<?php
// Здесь можно описать функции построения меню или другие helper-функции для макета.

function renderSiteMenu(array $ctx, int $maxDepth = 2): void
{
    $site = $ctx['site'] ?? [];
    $section = $ctx['section'] ?? [];
    $siteId = (int) ($site['id'] ?? 0);
    if ($siteId <= 0) {
        return;
    }

    $currentId = (int) ($section['id'] ?? 0);
    $repo = new SectionRepo();

    echo '<ul class="navbar-nav me-auto">';
    renderSiteMenuLevel($repo, $siteId, $siteId, $currentId, 1, $maxDepth);
    echo '</ul>';
}

function renderSiteMenuLevel(SectionRepo $repo, int $siteId, int $parentId, int $currentId, int $depth, int $maxDepth): void
{
    if ($depth > $maxDepth) {
        return;
    }

    $children = $repo->listChildren($parentId);
    foreach ($children as $child) {
        $englishName = (string) ($child['english_name'] ?? '');
        if ($englishName === '404') {
            continue;
        }

        $itemId = (int) ($child['id'] ?? 0);
        $title = (string) ($child['title'] ?? $englishName);
        $path = $repo->buildPath($itemId);
        $isActive = $itemId === $currentId || ($currentId > 0 && $repo->isDescendant($currentId, $itemId));

        $childrenItems = $repo->listChildren($itemId);
        $hasChildren = !empty($childrenItems) && $depth < $maxDepth;

        $linkClass = 'nav-link';
        if ($isActive) {
            $linkClass .= ' active';
        }

        if ($hasChildren) {
            echo '<li class="nav-item dropdown">';
            echo '<a class="' . $linkClass . ' dropdown-toggle" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '<ul class="dropdown-menu">';
            foreach ($childrenItems as $childItem) {
                $childId = (int) ($childItem['id'] ?? 0);
                $childTitle = (string) ($childItem['title'] ?? ($childItem['english_name'] ?? ''));
                $childPath = $repo->buildPath($childId);
                echo '<li><a class="dropdown-item" href="' . htmlspecialchars($childPath, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($childTitle, ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
            echo '</ul>';
            echo '</li>';
        } else {
            echo '<li class="nav-item">';
            echo '<a class="' . $linkClass . '" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '</li>';
        }
    }
}
