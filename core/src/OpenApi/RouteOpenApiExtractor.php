<?php

namespace PymeSec\Core\OpenApi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Router;
use RuntimeException;

class RouteOpenApiExtractor
{
    public function __construct(
        private readonly Router $router,
        private readonly ValidationRulesToSchema $validationRulesToSchema,
        private readonly Container $container,
    ) {}

    /**
     * @return array{paths: array<string, array<string, mixed>>, tags: array<int, array{name: string, description?: string}>}
     */
    public function extract(): array
    {
        $paths = [];
        $tagsByName = [];

        foreach ($this->router->getRoutes() as $route) {
            $uri = $route->uri();

            if (! is_string($uri) || ! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $path = $this->normalizePath($uri);
            $metadata = $route->defaults['_openapi'] ?? null;

            if (! is_array($metadata)) {
                throw new RuntimeException(sprintf(
                    'API route [%s %s] is missing mandatory OpenAPI metadata (_openapi).',
                    implode(',', $route->methods()),
                    $uri,
                ));
            }

            $this->assertMandatoryMetadata($metadata, $route->methods(), $uri);

            $tags = array_values(array_filter(
                (array) $metadata['tags'],
                static fn (mixed $tag): bool => is_string($tag) && $tag !== '',
            ));

            foreach ($tags as $tag) {
                $tagDescription = is_array($metadata['tag_descriptions'] ?? null)
                    ? ($metadata['tag_descriptions'][$tag] ?? null)
                    : null;

                $tagsByName[$tag] = [
                    'name' => $tag,
                    ...(
                        is_string($tagDescription) && $tagDescription !== ''
                            ? ['description' => $tagDescription]
                            : []
                    ),
                ];
            }

            foreach ($route->methods() as $method) {
                $normalizedMethod = strtolower($method);

                if (in_array($normalizedMethod, ['head', 'options'], true)) {
                    continue;
                }

                $operation = [
                    'tags' => $tags,
                    'operationId' => (string) $metadata['operation_id'],
                    'summary' => (string) $metadata['summary'],
                    'responses' => (array) $metadata['responses'],
                    'security' => is_array($metadata['security'] ?? null)
                        ? $metadata['security']
                        : [['bearerAuth' => []]],
                ];

                $description = $metadata['description'] ?? null;
                if (is_string($description) && $description !== '') {
                    $operation['description'] = $description;
                }

                $requestBody = $metadata['request_body'] ?? null;
                if (is_array($requestBody) && $requestBody !== []) {
                    $operation['requestBody'] = $requestBody;
                } elseif (is_string($metadata['request_form_request'] ?? null) && $metadata['request_form_request'] !== '') {
                    $operation['requestBody'] = $this->validationRulesToSchema->buildRequestBody(
                        rulesByField: $this->resolveRulesFromFormRequest($metadata['request_form_request']),
                        governedFields: is_array($metadata['governed_fields'] ?? null) ? $metadata['governed_fields'] : [],
                    );
                } elseif (is_array($metadata['request_rules'] ?? null)) {
                    $operation['requestBody'] = $this->validationRulesToSchema->buildRequestBody(
                        rulesByField: $metadata['request_rules'],
                        governedFields: is_array($metadata['governed_fields'] ?? null) ? $metadata['governed_fields'] : [],
                    );
                }

                $parameters = array_merge(
                    $this->pathParameters($path),
                    is_array($metadata['parameters'] ?? null) ? $metadata['parameters'] : [],
                );

                if ($parameters !== []) {
                    $operation['parameters'] = $parameters;
                }

                $permissions = $this->permissionKeys($route->gatherMiddleware());
                if ($permissions !== []) {
                    $operation['x-permissions'] = $permissions;
                }

                $lookupFields = $this->normalizeLookupFields($metadata['lookup_fields'] ?? null);
                if ($lookupFields !== []) {
                    $operation['x-lookup-fields'] = $lookupFields;
                }

                $paths[$path][$normalizedMethod] = $operation;
            }
        }

        ksort($paths);

        return [
            'paths' => $paths,
            'tags' => array_values($tagsByName),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $methods
     */
    private function assertMandatoryMetadata(array $metadata, array $methods, string $uri): void
    {
        foreach (['operation_id', 'tags', 'summary', 'responses'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $metadata)) {
                throw new RuntimeException(sprintf(
                    'API route [%s %s] is missing required OpenAPI metadata key [%s].',
                    implode(',', $methods),
                    $uri,
                    $requiredKey,
                ));
            }
        }

        $hasWriteMethod = false;
        foreach ($methods as $method) {
            if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
                $hasWriteMethod = true;
                break;
            }
        }

        if (! $hasWriteMethod) {
            return;
        }

        $hasRequestContract = (
            is_array($metadata['request_body'] ?? null)
            || is_array($metadata['request_rules'] ?? null)
            || (is_string($metadata['request_form_request'] ?? null) && $metadata['request_form_request'] !== '')
        );

        if (! $hasRequestContract) {
            throw new RuntimeException(sprintf(
                'Write API route [%s %s] requires request contract metadata (request_body, request_rules, or request_form_request).',
                implode(',', $methods),
                $uri,
            ));
        }

        $this->assertLookupFieldCoverage($metadata, $methods, $uri);
    }

    private function normalizePath(string $uri): string
    {
        $withoutPrefix = substr($uri, strlen('api/v1'));
        if (! is_string($withoutPrefix)) {
            return '/';
        }

        $path = '/'.ltrim($withoutPrefix, '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(string $path): array
    {
        if (! preg_match_all('/\{([^}]+)\}/', $path, $matches)) {
            return [];
        }

        $parameters = [];

        foreach ((array) ($matches[1] ?? []) as $rawName) {
            if (! is_string($rawName) || $rawName === '') {
                continue;
            }

            $isOptional = str_ends_with($rawName, '?');
            $name = $isOptional ? substr($rawName, 0, -1) : $rawName;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => ! $isOptional,
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }

        return $parameters;
    }

    /**
     * @param  array<int, string>  $middlewares
     * @return array<int, string>
     */
    private function permissionKeys(array $middlewares): array
    {
        $keys = [];

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'core.permission:')) {
                continue;
            }

            $value = trim(substr($middleware, strlen('core.permission:')));
            if ($value === '') {
                continue;
            }

            foreach (explode(',', $value) as $permission) {
                $permission = trim($permission);

                if ($permission !== '') {
                    $keys[] = $permission;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function normalizeLookupFields(mixed $rawLookupFields): array
    {
        if (! is_array($rawLookupFields)) {
            return [];
        }

        $normalized = [];

        foreach ($rawLookupFields as $field => $definition) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            if (is_string($definition)) {
                $source = trim($definition);

                if ($source === '') {
                    continue;
                }

                $normalized[$field] = ['source' => $source];

                continue;
            }

            if (! is_array($definition)) {
                continue;
            }

            $source = $definition['source'] ?? $definition['endpoint'] ?? null;
            if (! is_string($source) || trim($source) === '') {
                continue;
            }

            $entry = ['source' => trim($source)];

            $description = $definition['description'] ?? null;
            if (is_string($description) && trim($description) !== '') {
                $entry['description'] = trim($description);
            }

            $normalized[$field] = $entry;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $methods
     */
    private function assertLookupFieldCoverage(array $metadata, array $methods, string $uri): void
    {
        $rules = $this->resolveRequestRulesFromMetadata($metadata);

        if ($rules === []) {
            return;
        }

        $governedFields = is_array($metadata['governed_fields'] ?? null) ? $metadata['governed_fields'] : [];
        $lookupFields = $this->normalizeLookupFields($metadata['lookup_fields'] ?? null);
        $missingFields = [];

        foreach ($this->contractFieldNames($rules) as $field) {
            if (
                in_array($field, ['organization_id', 'scope_id', 'principal_id', 'membership_id', 'membership_ids'], true)
                || isset($governedFields[$field])
                || ! $this->fieldRequiresLookupSource($field)
            ) {
                continue;
            }

            if (! isset($lookupFields[$field]['source']) || trim($lookupFields[$field]['source']) === '') {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new RuntimeException(sprintf(
                'Write API route [%s %s] is missing lookup_fields metadata for constrained relation fields: %s.',
                implode(',', $methods),
                $uri,
                implode(', ', $missingFields),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function resolveRequestRulesFromMetadata(array $metadata): array
    {
        if (is_string($metadata['request_form_request'] ?? null) && $metadata['request_form_request'] !== '') {
            return $this->resolveRulesFromFormRequest($metadata['request_form_request']);
        }

        if (is_array($metadata['request_rules'] ?? null)) {
            return $metadata['request_rules'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<int, string>
     */
    private function contractFieldNames(array $rules): array
    {
        $fields = [];

        foreach ($rules as $field => $ruleSet) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            $normalizedField = preg_replace('/\.\*$/', '', $field);
            if (! is_string($normalizedField) || $normalizedField === '') {
                continue;
            }

            if (is_array($ruleSet) || is_string($ruleSet)) {
                $fields[] = $normalizedField;
            }
        }

        return array_values(array_unique($fields));
    }

    private function fieldRequiresLookupSource(string $field): bool
    {
        return str_ends_with($field, '_id') || str_ends_with($field, '_ids');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRulesFromFormRequest(string $className): array
    {
        if (! class_exists($className)) {
            throw new RuntimeException(sprintf(
                'OpenAPI metadata references unknown request_form_request class [%s].',
                $className,
            ));
        }

        $instance = new $className;

        if (! $instance instanceof FormRequest) {
            throw new RuntimeException(sprintf(
                'OpenAPI metadata request_form_request [%s] must extend FormRequest.',
                $className,
            ));
        }

        $instance->setContainer($this->container);

        $rules = $instance->rules();

        if (! is_array($rules)) {
            throw new RuntimeException(sprintf(
                'OpenAPI metadata request_form_request [%s] must return an array from rules().',
                $className,
            ));
        }

        return $rules;
    }
}
