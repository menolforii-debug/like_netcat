<?php
/** @var array $section */

$children = $section['children'] ?? [];
echo SectionList::render(is_array($children) ? $children : []);
