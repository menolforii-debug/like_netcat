<?php

final class SectionList
{
    public static function render(array $sections): string
    {
        if (empty($sections)) {
            return '<div class="text-muted">Разделов нет.</div>';
        }

        $html = '<ul class="list-unstyled section-list">';

        foreach ($sections as $section) {
            $title = htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $path = trim((string) ($section['path'] ?? ''));
            $href = $path !== '' ? $path : '#';

            if ($title === '') {
                $title = 'Без названия';
            }

            $html .= '<li class="section-list-item">'
                . '<a class="section-list-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a>'
                . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
