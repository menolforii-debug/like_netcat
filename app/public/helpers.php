<?php

function objects_list(array $filters): array
{
    $infoblockId = $filters['infoblock_id'] ?? null;
    $componentId = $filters['component_id'] ?? null;
    if ($infoblockId === null && $componentId === null) {
        return [];
    }

    $infoblockRepo = new InfoblockRepo();
    $componentRepo = new ComponentRepo();
    $sectionRepo = new SectionRepo();
    $objectRepo = new ObjectRepo();

    $infoblock = $infoblockId !== null ? $infoblockRepo->findById((int) $infoblockId) : null;
    if ($infoblock !== null) {
        $componentId = (int) $infoblock['component_id'];
    }
    if ($componentId === null) {
        return [];
    }

    $component = $componentRepo->findById((int) $componentId);
    if ($component === null) {
        return [];
    }

    $views = [];
    if (isset($component['views_json'])) {
        $decoded = json_decode((string) $component['views_json'], true);
        if (is_array($decoded)) {
            $views = $decoded;
        }
    }
    $views = array_values(array_filter($views, static function ($view): bool {
        return is_string($view) && trim($view) !== '';
    }));

    $template = isset($filters['template']) ? trim((string) $filters['template']) : '';
    if ($template === '') {
        if (in_array('default', $views, true)) {
            $template = 'default';
        } elseif (!empty($views)) {
            $template = (string) $views[0];
        } else {
            $template = 'default';
        }
    }

    $root = dirname(__DIR__, 2);
    $componentKey = (string) ($component['keyword'] ?? '');
    $templateDir = $componentKey !== '' && $template !== ''
        ? $root . '/templates/component/' . $componentKey . '/' . $template
        : '';
    if ($templateDir === '' || !is_dir($templateDir)) {
        $template = '';
        foreach ($views as $view) {
            $candidateDir = $componentKey !== ''
                ? $root . '/templates/component/' . $componentKey . '/' . $view
                : '';
            if ($candidateDir !== '' && is_dir($candidateDir)) {
                $templateDir = $candidateDir;
                $template = $view;
                break;
            }
        }
    }
    if ($templateDir === '' || !is_dir($templateDir)) {
        return [];
    }

    $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
    $includeDeleted = !empty($filters['is_deleted']);
    $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 0;
    $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? (int) $filters['offset'] : 0;
    if ($offset < 0) {
        $offset = 0;
    }
    $useIgnoreSub = !empty($filters['ignore_sub']) || ($infoblockId === null && $componentId !== null);

    $rows = $objectRepo->listBySystemQuery([
        'infoblock_id' => (int) ($infoblock['id'] ?? $infoblockId ?? 0),
        'component_id' => (int) $component['id'],
        'status' => $status,
        'include_deleted' => $includeDeleted,
        'per_page' => $limit,
        'offset' => $offset,
        'ignore_sub' => $useIgnoreSub ? 1 : 0,
        'ignore_cc' => !empty($filters['ignore_cc']) ? 1 : 0,
        'ignore_check' => !empty($filters['ignore_check']) ? 1 : 0,
        'ignore_all' => !empty($filters['ignore_all']) ? 1 : 0,
        'ignore_limit' => !empty($filters['ignore_limit']) ? 1 : 0,
        'query_select' => $filters['query_select'] ?? '',
        'query_from' => $filters['query_from'] ?? '',
        'query_join' => $filters['query_join'] ?? '',
        'query_where' => $filters['query_where'] ?? '',
        'query_group' => $filters['query_group'] ?? '',
        'query_having' => $filters['query_having'] ?? '',
        'query_order' => $filters['query_order'] ?? '',
        'query_limit' => $filters['query_limit'] ?? '',
        'distinct' => $filters['distinct'] ?? '',
    ]);

    $items = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data_json'], true);
        if (!is_array($data)) {
            $data = [];
        }
        $items[] = [
            'id' => $row['id'],
            'data' => $data,
            'status' => $row['status'] ?? 'draft',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'controls' => [],
        ];
    }

    $section = null;
    $site = null;
    if ($infoblock !== null) {
        $section = $sectionRepo->findById((int) $infoblock['section_id']);
        if ($section !== null && isset($section['site_id'])) {
            $site = $sectionRepo->findById((int) $section['site_id']);
        }
    }
    $section = $section ?? [];
    $site = $site ?? [];
    $settings = $infoblock['settings'] ?? [];
    $message_select = (string) ($objectRepo->getLastSelectQuery() ?? '');
    $editMode = false;
    $setFields = static function (array $item): void {
        $data = $item['data'] ?? [];
        if (!is_array($data)) {
            return;
        }
        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $GLOBALS['f_' . $key] = $value;
        }
    };

    $result = [];
    foreach ($items as $item) {
        $objects = [$item];
        $object = $item;
        $isSingle = true;

        $templatePath = $isSingle && is_file($templateDir . '/single.php')
            ? $templateDir . '/single.php'
            : $templateDir . '/list.php';
        if (!is_file($templatePath)) {
            continue;
        }

        $setFields($item);
        $previousScope = $GLOBALS['_snip_scope'] ?? null;
        $GLOBALS['_snip_scope'] = get_defined_vars();

        ob_start();
        require $templatePath;
        $rendered = (string) ob_get_clean();

        if ($previousScope !== null) {
            $GLOBALS['_snip_scope'] = $previousScope;
        } else {
            unset($GLOBALS['_snip_scope']);
        }

        // ✅ Сохраняем старый контракт: массив HTML-строк
        $result[] = $rendered;
    }

    return $result;
}

function insert_snip(string $keyword, array $vars = []): string
{
    $keyword = trim($keyword);
    if ($keyword === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $keyword)) {
        return '';
    }

    $root = dirname(__DIR__, 2);
    $baseDir = $root . '/templates/snippets';
    $baseReal = realpath($baseDir);
    if ($baseReal === false) {
        return '';
    }
    $snippetPath = $baseReal . '/' . $keyword . '.php';
    $realSnippetPath = realpath($snippetPath);
    if ($realSnippetPath === false) {
        return '';
    }

    $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($realSnippetPath, $baseReal) !== 0) {
        return '';
    }
    if (!is_file($realSnippetPath)) {
        return '';
    }

    if ($vars === [] && isset($GLOBALS['_snip_scope']) && is_array($GLOBALS['_snip_scope'])) {
        $vars = $GLOBALS['_snip_scope'];
    }

    if ($vars !== []) {
        extract($vars, EXTR_SKIP);
    }

    ob_start();
    require $realSnippetPath;
    return (string) ob_get_clean();
}
