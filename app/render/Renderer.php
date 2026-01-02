<?php

final class Renderer
{
    public function renderPath($path): void
    {
        $editMode = isset($_GET['edit']) && $_GET['edit'] === '1' && Auth::canEdit();
        $previewMode = $editMode && isset($_GET['preview']) && $_GET['preview'] === '1';
        $previewObjectId = isset($_GET['object_id']) ? (int) $_GET['object_id'] : 0;

        $sectionRepo = new SectionRepo();
        $section = $sectionRepo->findByPath($path);

        if ($section === null) {
            http_response_code(404);
            echo 'Section not found';
            return;
        }

        $sectionPath = $this->buildSectionPath($sectionRepo, (int) $section['id']);
        $section['path'] = $sectionPath;

        $children = $sectionRepo->findChildren((int) $section['id']);
        foreach ($children as $index => $child) {
            $children[$index]['path'] = $this->joinPath($sectionPath, $child['slug']);
        }

        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo(core()->events());

        $infoblocks = $infoblockRepo->findBySection((int) $section['id']);
        $infoblocksHtml = '';
        $infoblockViews = [];
        $previewObjectData = null;
        foreach ($infoblocks as $infoblock) {
            if (!Permission::canView(Auth::user(), $infoblock)) {
                continue;
            }

            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);

            if ($editMode) {
                $objects = $objectRepo->listForInfoblockEdit((int) $infoblock['id']);
            } else {
                $objects = $objectRepo->listForInfoblock((int) $infoblock['id']);
            }

            if ($previewMode && $previewObjectId > 0) {
                $previewObject = $objectRepo->findById($previewObjectId);
                if ($previewObject && (int) $previewObject['infoblock_id'] === (int) $infoblock['id']) {
                    $objects = $this->appendPreviewObject($objects, $previewObject);
                    $previewObjectData = $this->decodeObjectData($previewObject);
                }
            }

            $items = $this->decodeItems($objects, $editMode);
            $infoblocksHtml .= $this->renderInfoblock($section, $infoblock, $component, $items, $editMode);
            $infoblockViews[] = [
                'infoblock' => $infoblock,
                'component' => $component,
                'items' => $items,
            ];
        }

        $core = [
            'infoblocks_html' => $infoblocksHtml,
        ];

        $seo = $this->resolveSeo($section, $infoblockViews, $previewObjectData);
        $this->renderDocumentStart($seo);
        $this->renderSection($section, $children, $core, $editMode);
        $this->renderDocumentEnd();
    }

    private function renderDocumentStart(array $seo): void
    {
        $title = (string) ($seo['title'] ?? '');
        Layout::renderDocumentStart($title, $seo);
        Layout::renderNavbar('CMS', [
            ['label' => 'Админ', 'href' => '/admin.php'],
        ]);
        echo '<main class="container mb-5">';
    }

    private function renderDocumentEnd(): void
    {
        echo '</main>';
        Layout::renderDocumentEnd();
    }

    private function renderSection(array $section, array $children, array $core, $editMode): void
    {
        $component = ['keyword' => 'section'];
        $infoblock = ['view_template' => 'default'];
        $items = $children;

        $templatePath = __DIR__ . '/../../templates/section/default.php';
        if (!is_file($templatePath)) {
            http_response_code(500);
            echo 'Template not found';
            return;
        }

        require $templatePath;
    }

    private function renderInfoblock(array $section, array $infoblock, array $component, array $items, $editMode): string
    {
        $core = [];
        $infoblock['view_template'] = $this->resolveViewTemplate($infoblock, $component);

        $templatePath = __DIR__ . '/../../templates/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
    }

    private function resolveViewTemplate(array $infoblock, array $component): string
    {
        $views = [];
        if (isset($component['views']) && is_array($component['views'])) {
            $views = $component['views'];
        }

        $template = isset($infoblock['view_template']) ? trim((string) $infoblock['view_template']) : '';
        if ($template !== '' && in_array($template, $views, true)) {
            return $template;
        }

        return 'list';
    }

    private function decodeItems(array $objects, $editMode): array
    {
        $items = [];

        foreach ($objects as $object) {
            $data = json_decode((string) $object['data_json'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $controls = [];
            if ($editMode) {
                $controls = [
                    'delete_url' => $this->buildDeleteUrl((int) $object['id']),
                ];
            }

            $items[] = [
                'id' => $object['id'],
                'data' => $data,
                'status' => $object['status'] ?? 'published',
                'created_at' => $object['created_at'],
                'updated_at' => $object['updated_at'],
                'controls' => $controls,
            ];
        }

        return $items;
    }

    private function resolveSeo(array $section, array $infoblockViews, ?array $previewObjectData): array
    {
        $sectionExtra = [];
        if (isset($section['extra']) && is_array($section['extra'])) {
            $sectionExtra = $section['extra'];
        } elseif (isset($section['extra_json'])) {
            $decoded = json_decode((string) $section['extra_json'], true);
            if (is_array($decoded)) {
                $sectionExtra = $decoded;
            }
        }

        $objectData = [];
        $primaryInfoblock = $infoblockViews[0]['infoblock'] ?? null;
        $primaryView = $primaryInfoblock['view_template'] ?? 'list';

        if ($previewObjectData !== null) {
            $objectData = $previewObjectData;
            $primaryView = 'item';
        } else {
            foreach ($infoblockViews as $view) {
                if (($view['infoblock']['view_template'] ?? '') === 'item' && !empty($view['items'])) {
                    $objectData = $view['items'][0]['data'] ?? [];
                    $primaryView = 'item';
                    $primaryInfoblock = $view['infoblock'];
                    break;
                }
            }
        }

        $title = '';
        if (!empty($objectData['seo_title'])) {
            $title = (string) $objectData['seo_title'];
        } elseif (!empty($sectionExtra['seo_title'])) {
            $title = (string) $sectionExtra['seo_title'];
        }

        if ($title === '') {
            if ($primaryView === 'item' && !empty($objectData['title'])) {
                $title = (string) $objectData['title'];
            } elseif ($primaryView === 'list' && $primaryInfoblock && count($infoblockViews) === 1) {
                $title = (string) ($section['title'] ?? '') . ' — ' . (string) ($primaryInfoblock['name'] ?? '');
            } else {
                $title = (string) ($section['title'] ?? '');
            }
        }

        $description = '';
        if (!empty($objectData['seo_description'])) {
            $description = (string) $objectData['seo_description'];
        } elseif (!empty($sectionExtra['seo_description'])) {
            $description = (string) $sectionExtra['seo_description'];
        }

        $keywords = '';
        if (!empty($objectData['seo_keywords'])) {
            $keywords = (string) $objectData['seo_keywords'];
        } elseif (!empty($sectionExtra['seo_keywords'])) {
            $keywords = (string) $sectionExtra['seo_keywords'];
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
        ];
    }

    private function decodeObjectData(array $object): array
    {
        $data = json_decode((string) ($object['data_json'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }

        return $data;
    }

    private function appendPreviewObject(array $objects, array $previewObject): array
    {
        foreach ($objects as $object) {
            if ((int) $object['id'] === (int) $previewObject['id']) {
                return $objects;
            }
        }

        $objects[] = $previewObject;
        return $objects;
    }

    private function buildDeleteUrl($id): string
    {
        return '/admin.php?action=object_delete&id=' . (int) $id;
    }

    private function buildSectionPath(SectionRepo $repo, $sectionId): string
    {
        $segments = [];
        $currentId = $sectionId;

        while ($currentId !== null) {
            $section = $repo->findById($currentId);
            if ($section === null) {
                break;
            }

            if ($section['slug'] !== '') {
                $segments[] = $section['slug'];
            }

            $currentId = $section['parent_id'] !== null ? (int) $section['parent_id'] : null;
        }

        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', array_reverse($segments)) . '/';
    }

    private function joinPath($basePath, $slug): string
    {
        $basePath = rtrim($basePath, '/');
        $slug = trim($slug, '/');

        if ($basePath === '') {
            $basePath = '/';
        }

        if ($slug === '') {
            return $basePath . '/';
        }

        if ($basePath === '/') {
            return '/' . $slug . '/';
        }

        return $basePath . '/' . $slug . '/';
    }
}
