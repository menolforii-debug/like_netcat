<?php

final class FieldValidator
{
    public function validate(array $component, array $data): array
    {
        $fields = $this->parseFields($component);
        $errors = [];
        $sanitized = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'];
            $required = $field['required'];
            $hasValue = array_key_exists($name, $data);
            $value = $hasValue ? $data[$name] : null;

            if (!$hasValue || $value === '' || $value === null) {
                if (array_key_exists('default', $field)) {
                    $sanitized[$name] = $field['default'];
                    continue;
                }

                if ($required) {
                    $errors[] = 'Поле "' . $name . '" обязательно.';
                    continue;
                }

                continue;
            }

            $coerced = $this->coerceValue($name, $type, $value, $errors);
            if ($coerced !== null || $type === 'bool') {
                $sanitized[$name] = $coerced;
            }
        }

        if (isset($sanitized['english_name']) && !$this->isUrlSafe((string) $sanitized['english_name'])) {
            $errors[] = 'Поле "english_name" должно быть URL-безопасным.';
        }

        $this->applySeoMapping($fields, $sanitized);

        if (!empty($errors)) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return $sanitized;
    }

    public function parseFields(array $component): array
    {
        $fieldsJson = $component['fields_json'] ?? '{}';
        $decoded = json_decode((string) $fieldsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $fields = $decoded['fields'] ?? $decoded;
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (is_string($field)) {
                $normalized[] = [
                    'name' => $field,
                    'type' => 'string',
                    'required' => false,
                    'seo' => false,
                ];
                continue;
            }

            if (!is_array($field) || empty($field['name']) || empty($field['type'])) {
                continue;
            }

            $normalized[] = [
                'name' => (string) $field['name'],
                'type' => (string) $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'default' => $field['default'] ?? null,
                'seo' => (bool) ($field['seo'] ?? false),
            ];
        }

        return $normalized;
    }

    private function coerceValue(string $name, string $type, $value, array &$errors)
    {
        switch ($type) {
            case 'string':
            case 'text':
            case 'html':
                return (string) $value;
            case 'int':
                if (!is_numeric($value)) {
                    $errors[] = 'Поле "' . $name . '" должно быть числом.';
                    return null;
                }
                return (int) $value;
            case 'number':
            case 'float':
                if (!is_numeric($value)) {
                    $errors[] = 'Поле "' . $name . '" должно быть числом.';
                    return null;
                }
                return (float) $value;
            case 'bool':
                return $this->toBool($value);
            case 'date':
                $value = (string) $value;
                if (!$this->isValidDate($value)) {
                    $errors[] = 'Поле "' . $name . '" должно быть датой YYYY-MM-DD.';
                    return null;
                }
                return $value;
            case 'datetime':
                $value = (string) $value;
                if (!$this->isValidDateTime($value)) {
                    $errors[] = 'Поле "' . $name . '" должно быть корректной датой и временем.';
                    return null;
                }
                return $value;
            case 'url_safe':
            case 'slug':
                $value = (string) $value;
                if (!$this->isUrlSafe($value)) {
                    $errors[] = 'Поле "' . $name . '" должно быть URL-безопасным.';
                    return null;
                }
                return $value;
            default:
                $errors[] = 'Поле "' . $name . '" имеет неизвестный тип.';
                return null;
        }
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string) $value);

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $parts = explode('-', $value);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function isValidDateTime(string $value): bool
    {
        try {
            new DateTimeImmutable($value);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function isUrlSafe(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $value);
    }

    private function applySeoMapping(array $fields, array &$data): void
    {
        $seoTitle = $data['seo_title'] ?? '';
        $seoDescription = $data['seo_description'] ?? '';

        foreach ($fields as $field) {
            if (empty($field['seo'])) {
                continue;
            }

            $name = $field['name'];
            if (($name === 'english_name' || $name === 'slug') && isset($data[$name]) && !$this->isUrlSafe((string) $data[$name])) {
                continue;
            }
            if ($name === 'title' && $seoTitle === '' && isset($data[$name])) {
                $data['seo_title'] = (string) $data[$name];
                $seoTitle = $data['seo_title'];
            }

            if (($name === 'text' || $name === 'description') && $seoDescription === '' && isset($data[$name])) {
                $data['seo_description'] = (string) $data[$name];
                $seoDescription = $data['seo_description'];
            }
        }
    }
}
