<?php
declare(strict_types=1);

final class Renderer
{
    public function renderPath(string $path, bool $editMode): void
    {
        $sectionRepo = new SectionRepo();
        $section = $sectionRepo->findByPath($path);

        if ($section === null) {
            http_response_code(404);
            echo 'Section not found';
            return;
        }

        $children = $sectionRepo->findChildren((int) $section['id']);
        $this->renderSection($section, $children, $editMode);

        $infoblockRepo = new InfoblockRepo();
        $componentRepo = new ComponentRepo();
        $objectRepo = new ObjectRepo();

        $infoblocks = $infoblockRepo->findBySection((int) $section['id']);
        foreach ($infoblocks as $infoblock) {
            $component = $componentRepo->findById((int) $infoblock['component_id']);
            if ($component === null) {
                continue;
            }

            $objects = $objectRepo->findByInfoblock((int) $infoblock['id']);
            $items = $this->decodeItems($objects);
            $this->renderInfoblock($section, $infoblock, $component, $items, $editMode);
        }
    }

    private function renderSection(array $section, array $children, bool $editMode): void
    {
        $component = ['keyword' => 'section'];
        $infoblock = ['view_template' => 'default'];
        $items = $children;
        $core = [];

        $templatePath = __DIR__ . '/../../templates/section/default.php';
        if (!is_file($templatePath)) {
            http_response_code(500);
            echo 'Template not found';
            return;
        }

        require $templatePath;
    }

    private function renderInfoblock(array $section, array $infoblock, array $component, array $items, bool $editMode): void
    {
        $core = [];

        $templatePath = __DIR__ . '/../../templates/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            return;
        }

        require $templatePath;
    }

    private function decodeItems(array $objects): array
    {
        $items = [];

        foreach ($objects as $object) {
            $data = json_decode((string) $object['data_json'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $items[] = [
                'id' => $object['id'],
                'data' => $data,
                'created_at' => $object['created_at'],
                'updated_at' => $object['updated_at'],
            ];
        }

        return $items;
    }
}
