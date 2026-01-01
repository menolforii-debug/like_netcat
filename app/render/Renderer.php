<?php
declare(strict_types=1);

final class Renderer
{
    public function renderSection(array $section, array $children, bool $editMode): void
    {
        $component = ['keyword' => 'section'];
        $infoblock = ['view_template' => 'default'];
        $items = $children;
        $core = [];

        $templatePath = __DIR__ . '/../../templates/' . $component['keyword'] . '/' . $infoblock['view_template'] . '.php';
        if (!is_file($templatePath)) {
            http_response_code(500);
            echo 'Template not found';
            return;
        }

        require $templatePath;
    }
}
