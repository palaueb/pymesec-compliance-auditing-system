<?php

namespace PymeSec\Core\OpenApi;

class ValidationRulesToSchema
{
    /**
     * @param  array<string, mixed>  $rulesByField
     * @param  array<string, string>  $governedFields
     * @return array<string, mixed>
     */
    public function buildRequestBody(array $rulesByField, array $governedFields = []): array
    {
        $properties = [];
        $required = [];
        $arrayItemRules = [];

        foreach ($rulesByField as $field => $rules) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            if (preg_match('/^(.+)\.\*$/', $field, $matches) === 1) {
                $arrayField = trim((string) ($matches[1] ?? ''));

                if ($arrayField !== '') {
                    $arrayItemRules[$arrayField] = $rules;
                }

                continue;
            }

            $fieldSchema = $this->buildFieldSchema($rules);

            if (($fieldSchema['required'] ?? false) === true) {
                $required[] = $field;
            }

            $schema = $fieldSchema['schema'] ?? null;
            if (! is_array($schema)) {
                continue;
            }

            $catalogKey = $governedFields[$field] ?? null;
            if (is_string($catalogKey) && $catalogKey !== '') {
                $schema['x-governed-catalog'] = $catalogKey;
                $schema['x-governed-source'] = sprintf(
                    '/api/v1/lookups/reference-catalogs/%s/options',
                    $catalogKey,
                );
            }

            $properties[$field] = $schema;
        }

        foreach ($arrayItemRules as $arrayField => $itemRules) {
            $itemSchema = $this->buildFieldSchema($itemRules)['schema'] ?? null;

            if (! is_array($itemSchema)) {
                continue;
            }

            if (! is_array($properties[$arrayField] ?? null)) {
                $properties[$arrayField] = [
                    'type' => 'array',
                ];
            }

            if (($properties[$arrayField]['type'] ?? null) !== 'array') {
                continue;
            }

            $properties[$arrayField]['items'] = $itemSchema;
        }

        $payloadSchema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $payloadSchema['required'] = array_values(array_unique($required));
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $payloadSchema,
                ],
            ],
        ];
    }

    /**
     * @return array{schema: array<string, mixed>, required: bool}
     */
    private function buildFieldSchema(mixed $rules): array
    {
        $normalizedRules = $this->normalizeRules($rules);
        $schema = ['type' => 'string'];
        $required = false;
        $nullable = false;

        foreach ($normalizedRules as $rule) {
            if (! is_string($rule) || $rule === '') {
                continue;
            }

            [$name, $params] = $this->parseRule($rule);

            switch ($name) {
                case 'required':
                    $required = true;
                    break;
                case 'nullable':
                    $nullable = true;
                    break;
                case 'string':
                    $schema['type'] = 'string';
                    break;
                case 'integer':
                    $schema['type'] = 'integer';
                    break;
                case 'numeric':
                    $schema['type'] = 'number';
                    break;
                case 'boolean':
                    $schema['type'] = 'boolean';
                    break;
                case 'array':
                    $schema['type'] = 'array';
                    $schema['items'] = ['type' => 'string'];
                    break;
                case 'email':
                    $schema['type'] = 'string';
                    $schema['format'] = 'email';
                    break;
                case 'date':
                    $schema['type'] = 'string';
                    $schema['format'] = 'date';
                    break;
                case 'max':
                    $this->applyBoundary($schema, $params[0] ?? null, isMin: false);
                    break;
                case 'min':
                    $this->applyBoundary($schema, $params[0] ?? null, isMin: true);
                    break;
                case 'in':
                    if ($params !== []) {
                        $schema['enum'] = $params;
                    }
                    break;
                default:
                    break;
            }
        }

        if ($nullable) {
            $type = $schema['type'] ?? 'string';

            if (is_string($type)) {
                $schema['type'] = [$type, 'null'];
            }
        }

        return [
            'schema' => $schema,
            'required' => $required,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRules(mixed $rules): array
    {
        $normalized = [];

        if (is_string($rules) && $rules !== '') {
            foreach (explode('|', $rules) as $rule) {
                $rule = trim($rule);

                if ($rule !== '') {
                    $normalized[] = $rule;
                }
            }

            return $normalized;
        }

        if (! is_array($rules)) {
            return $normalized;
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && $rule !== '') {
                foreach (explode('|', $rule) as $chunk) {
                    $chunk = trim($chunk);

                    if ($chunk !== '') {
                        $normalized[] = $chunk;
                    }
                }

                continue;
            }

            if (is_object($rule) && method_exists($rule, '__toString')) {
                $stringRule = trim((string) $rule);

                if ($stringRule !== '') {
                    $normalized[] = $stringRule;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = strtolower(trim($parts[0]));
        $rawParams = isset($parts[1]) ? trim($parts[1]) : '';

        if ($rawParams === '') {
            return [$name, []];
        }

        return [
            $name,
            array_values(array_filter(array_map('trim', explode(',', $rawParams)), static fn (string $value): bool => $value !== '')),
        ];
    }

    private function applyBoundary(array &$schema, mixed $value, bool $isMin): void
    {
        if (! is_string($value) || $value === '' || ! is_numeric($value)) {
            return;
        }

        $number = (float) $value;
        $type = $schema['type'] ?? 'string';

        if (is_array($type)) {
            $type = $type[0] ?? 'string';
        }

        if (! is_string($type)) {
            $type = 'string';
        }

        if ($type === 'string') {
            $schema[$isMin ? 'minLength' : 'maxLength'] = (int) $number;

            return;
        }

        if ($type === 'array') {
            $schema[$isMin ? 'minItems' : 'maxItems'] = (int) $number;

            return;
        }

        if (in_array($type, ['integer', 'number'], true)) {
            $schema[$isMin ? 'minimum' : 'maximum'] = $type === 'integer' ? (int) $number : $number;
        }
    }
}
