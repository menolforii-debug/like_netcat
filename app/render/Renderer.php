<?php

final class Renderer
{
    public function renderPath($path): void
    {
        $editMode = isset($_GET['edit']) && $_GET['edit'] === '1' && Auth::canEdit();

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
        foreach ($infoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $objects = $objectRepo->findByInfoblock((int) $infoblock['id']);
            $items = $this->decodeItems($objects, $editMode);
            $infoblocksHtml .= $this->renderInfoblock($section, $infoblock, $component, $items, $editMode);
        }

        $core = [
            'infoblocks_html' => $infoblocksHtml,
        ];

        $seo = Seo::resolve($section, null);

        $this->renderDocumentStart($seo);
        $this->renderSection($section, $children, $core, $editMode);
        $this->renderDocumentEnd();
    }

    private function renderDocumentStart(array $seo): void
    {
        $title = htmlspecialchars($seo['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $descriptionRaw = isset($seo['description']) ? trim((string) $seo['description']) : '';
        $description = htmlspecialchars($descriptionRaw, ENT_QUOTES, 'UTF-8');
        $keywords = isset($seo['keywords']) ? trim((string) $seo['keywords']) : '';
        $keywordsEscaped = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');

        echo "<!doctype html>\n";
        echo "<html lang=\"ru\">\n";
        echo "<head>\n";
        echo "    <meta charset=\"utf-8\">\n";
        echo "    <title>{$title}</title>\n";
        if ($descriptionRaw !== '') {
            echo "    <meta name=\"description\" content=\"{$description}\">\n";
        }
        if ($keywords !== '') {
            echo "    <meta name=\"keywords\" content=\"{$keywordsEscaped}\">\n";
        }
        echo "</head>\n";
        echo "<body>\n";
    }

    private function renderDocumentEnd(): void
    {
        echo "</body>\n";
        echo "</html>\n";
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

        $templatePath = __DIR__ . '/../../templates/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return '';
        }

        ob_start();
        require $templatePath;
        return (string) ob_get_clean();
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
                'created_at' => $object['created_at'],
                'updated_at' => $object['updated_at'],
                'controls' => $controls,
            ];
        }

        return $items;
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
