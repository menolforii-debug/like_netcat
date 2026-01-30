<?php

final class Nav
{
    private ?array $site = null;
    private ?array $section = null;
    private $sectionRepo = null;

    private bool $loaded = false;
    private array $sections = [];
    private array $byId = [];
    private array $childrenByParent = [];
    private array $parentById = [];
    private array $levelById = [];
    private array $pathIds = [];

    private ?int $modeParentId = null;
    private ?int $modeLevel = null;
    private array $conditions = [];
    private array $orderBy = [];
    private bool $asArray = false;

    private array $allowedFields = ['id', 'parent_id', 'title', 'english_name'];

    public function setContext(array $site, ?array $section, $sectionRepo): void
    {
        $this->site = $site;
        $this->section = $section;
        $this->sectionRepo = $sectionRepo;
        $this->loaded = false;
        $this->sections = [];
        $this->byId = [];
        $this->childrenByParent = [];
        $this->parentById = [];
        $this->levelById = [];
        $this->pathIds = [];
        $this->resetBuilder();
    }

    public function get_sub($parent_id = 0): array
    {
        $parentId = (int) $parent_id;
        if ($parentId <= 0) {
            $parentId = (int) ($this->section['id'] ?? 0);
        }
        if ($parentId <= 0) {
            $parentId = (int) ($this->site['id'] ?? 0);
        }
        if ($parentId <= 0) {
            return [];
        }

        $this->sub($parentId);
        return $this->get();
    }

    public function get_by_level($level): array
    {
        $this->level($level);
        return $this->get();
    }

    public function get_path($offset = 0, $length = null): array
    {
        $this->loadSections();
        $items = [];
        foreach ($this->pathIds as $id) {
            if (!isset($this->byId[$id])) {
                continue;
            }
            $items[] = $this->mapItem($this->byId[$id]);
        }

        $slice = array_slice($items, (int) $offset, $length !== null ? (int) $length : null);
        return $this->asArray ? $slice : $this->toObjects($slice);
    }

    public function get(): array
    {
        $this->loadSections();
        $items = $this->sections;

        if ($this->modeParentId !== null) {
            $items = array_filter($items, function (array $item): bool {
                return (int) ($item['parent_id'] ?? 0) === (int) $this->modeParentId;
            });
        }
        if ($this->modeLevel !== null) {
            $level = (int) $this->modeLevel;
            $items = array_filter($items, function (array $item) use ($level): bool {
                $id = (int) ($item['id'] ?? 0);
                return isset($this->levelById[$id]) && $this->levelById[$id] === $level;
            });
        }

        $items = array_filter($items, function (array $item): bool {
            return $this->matchConditions($item);
        });

        $items = array_values($items);
        $this->applyOrder($items);

        $result = [];
        foreach ($items as $item) {
            $result[] = $this->mapItem($item);
        }

        $this->resetBuilder();
        return $this->asArray ? $result : $this->toObjects($result);
    }

    public function sub($parent_id): self
    {
        $this->modeParentId = (int) $parent_id;
        return $this;
    }

    public function level($level): self
    {
        $this->modeLevel = (int) $level;
        return $this;
    }

    public function where(string $field, $opOrVal = null, $val = null): self
    {
        return $this->addCondition('and', $field, $opOrVal, $val);
    }

    public function or_where(string $field, $opOrVal = null, $val = null): self
    {
        return $this->addCondition('or', $field, $opOrVal, $val);
    }

    public function where_in(string $field, array $values): self
    {
        return $this->addInCondition('and', $field, $values);
    }

    public function or_where_in(string $field, array $values): self
    {
        return $this->addInCondition('or', $field, $values);
    }

    public function order_by(string $field, string $dir = 'asc'): self
    {
        $field = $this->normalizeField($field);
        if ($field === '') {
            return $this;
        }
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        $this->orderBy[] = ['field' => $field, 'dir' => $dir];
        return $this;
    }

    public function as_object(): self
    {
        $this->asArray = false;
        return $this;
    }

    public function as_array(): self
    {
        $this->asArray = true;
        return $this;
    }

    private function loadSections(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $siteId = (int) ($this->site['id'] ?? 0);
        if ($siteId <= 0) {
            return;
        }

        $this->sections = DB::fetchAll(
            'SELECT id, parent_id, site_id, english_name, title, sort, extra_json
            FROM sections
            WHERE site_id = :site_id
            ORDER BY sort ASC, id ASC',
            ['site_id' => $siteId]
        );

        foreach ($this->sections as $section) {
            $id = (int) ($section['id'] ?? 0);
            $parentId = isset($section['parent_id']) ? (int) $section['parent_id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $this->byId[$id] = $section;
            $this->parentById[$id] = $parentId;
            $this->childrenByParent[$parentId][] = $id;
        }

        $this->buildLevels($siteId);
        $this->buildPathIds();
    }

    private function buildLevels(int $siteId): void
    {
        $this->levelById = [];
        $queue = [];
        $this->levelById[$siteId] = -1;

        foreach ($this->childrenByParent[$siteId] ?? [] as $childId) {
            $this->levelById[$childId] = 0;
            $queue[] = $childId;
        }

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $currentLevel = $this->levelById[$currentId] ?? 0;
            foreach ($this->childrenByParent[$currentId] ?? [] as $childId) {
                $this->levelById[$childId] = $currentLevel + 1;
                $queue[] = $childId;
            }
        }
    }

    private function buildPathIds(): void
    {
        $this->pathIds = [];
        $currentId = (int) ($this->section['id'] ?? 0);
        if ($currentId <= 0) {
            return;
        }

        $visited = [];
        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $this->pathIds[] = $currentId;
            if (!isset($this->parentById[$currentId])) {
                break;
            }
            $parentId = (int) $this->parentById[$currentId];
            if ($parentId <= 0 || $parentId === $currentId) {
                break;
            }
            $currentId = $parentId;
        }

        $this->pathIds = array_reverse($this->pathIds);
    }

    private function mapItem(array $section): array
    {
        $id = (int) ($section['id'] ?? 0);
        $name = (string) ($section['title'] ?? '');
        $parentId = isset($section['parent_id']) ? (int) $section['parent_id'] : 0;
        $englishName = (string) ($section['english_name'] ?? '');
        $url = $this->sectionRepo ? $this->sectionRepo->buildPath($id) : '';
        $currentId = (int) ($this->section['id'] ?? 0);
        $active = in_array($id, $this->pathIds, true);
        $current = $id > 0 && $id === $currentId;
        $level = $this->levelById[$id] ?? null;

        return [
            'id' => $id,
            'name' => $name,
            'url' => $url,
            'active' => $active,
            'current' => $current,
            'parent_id' => $parentId,
            'english_name' => $englishName,
            'level' => $level,
        ];
    }

    private function toObjects(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $obj = new stdClass();
            foreach ($item as $key => $value) {
                $obj->{$key} = $value;
            }
            $result[] = $obj;
        }
        return $result;
    }

    private function addCondition(string $boolean, string $field, $opOrVal, $val): self
    {
        $field = $this->normalizeField($field);
        if ($field === '') {
            return $this;
        }
        if ($val === null) {
            $op = '=';
            $value = $opOrVal;
        } else {
            $op = (string) $opOrVal;
            $value = $val;
        }
        $op = strtolower($op);
        $this->conditions[] = [
            'boolean' => $boolean,
            'type' => 'basic',
            'field' => $field,
            'op' => $op,
            'value' => $value,
        ];
        return $this;
    }

    private function addInCondition(string $boolean, string $field, array $values): self
    {
        $field = $this->normalizeField($field);
        if ($field === '') {
            return $this;
        }
        $this->conditions[] = [
            'boolean' => $boolean,
            'type' => 'in',
            'field' => $field,
            'values' => $values,
        ];
        return $this;
    }

    private function normalizeField(string $field): string
    {
        $field = strtolower(trim($field));
        if (!in_array($field, $this->allowedFields, true)) {
            return '';
        }
        return $field;
    }

    private function matchConditions(array $item): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        $result = null;
        foreach ($this->conditions as $condition) {
            $matched = $this->matchCondition($item, $condition);
            if ($result === null) {
                $result = $matched;
            } elseif ($condition['boolean'] === 'or') {
                $result = $result || $matched;
            } else {
                $result = $result && $matched;
            }
        }

        return $result ?? true;
    }

    private function matchCondition(array $item, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $value = $item[$field] ?? null;
        if ($condition['type'] === 'in') {
            $values = $condition['values'] ?? [];
            return in_array($value, $values, true);
        }

        $op = $condition['op'] ?? '=';
        $compare = $condition['value'] ?? null;

        if (in_array($field, ['id', 'parent_id'], true)) {
            $value = (int) $value;
            $compare = (int) $compare;
        } else {
            $value = (string) $value;
            $compare = (string) $compare;
        }

        switch ($op) {
            case '!=':
                return $value != $compare;
            case '>':
                return $value > $compare;
            case '<':
                return $value < $compare;
            case '>=':
                return $value >= $compare;
            case '<=':
                return $value <= $compare;
            case 'like':
                $pattern = str_replace('%', '', $compare);
                if ($pattern === '') {
                    return true;
                }
                return stripos((string) $value, $pattern) !== false;
            case '=':
            default:
                return $value == $compare;
        }
    }

    private function applyOrder(array &$items): void
    {
        if (empty($this->orderBy)) {
            return;
        }
        $orderBy = $this->orderBy;
        usort($items, function (array $a, array $b) use ($orderBy): int {
            foreach ($orderBy as $order) {
                $field = $order['field'];
                $dir = $order['dir'];
                $aVal = $a[$field] ?? null;
                $bVal = $b[$field] ?? null;
                if (in_array($field, ['id', 'parent_id'], true)) {
                    $aVal = (int) $aVal;
                    $bVal = (int) $bVal;
                } else {
                    $aVal = (string) $aVal;
                    $bVal = (string) $bVal;
                }
                if ($aVal === $bVal) {
                    continue;
                }
                $cmp = $aVal <=> $bVal;
                return $dir === 'desc' ? -$cmp : $cmp;
            }
            return 0;
        });
    }

    private function resetBuilder(): void
    {
        $this->modeParentId = null;
        $this->modeLevel = null;
        $this->conditions = [];
        $this->orderBy = [];
        $this->asArray = false;
    }
}
