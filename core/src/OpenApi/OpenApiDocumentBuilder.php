<?php

namespace PymeSec\Core\OpenApi;

class OpenApiDocumentBuilder
{
    public function __construct(
        private readonly OpenApiFragmentLoader $fragments,
        private readonly RouteOpenApiExtractor $routes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'PymeSec API',
                'version' => (string) config('app.version', '0.1.0'),
                'description' => 'Canonical REST API contract for PymeSec.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                    ],
                ],
            ],
            'tags' => [],
            'security' => [
                ['bearerAuth' => []],
            ],
            'x-generated-at' => now()->toIso8601String(),
        ];

        foreach ($this->fragments->loadFragments() as $fragment) {
            $document = $this->mergeDocument($document, $fragment, mergePaths: false);
        }

        $routeContract = $this->routes->extract();
        $document['paths'] = $routeContract['paths'];
        $document['tags'] = $this->mergeTags($document['tags'], $routeContract['tags']);

        return $document;
    }

    public function writeTo(string $outputPath): void
    {
        $json = json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($outputPath, $json);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $fragment
     * @return array<string, mixed>
     */
    private function mergeDocument(array $document, array $fragment, bool $mergePaths = true): array
    {
        if (is_array($fragment['info'] ?? null)) {
            $document['info'] = array_merge($document['info'], $fragment['info']);
        }

        if (is_array($fragment['servers'] ?? null)) {
            $document['servers'] = $fragment['servers'];
        }

        if ($mergePaths && is_array($fragment['paths'] ?? null)) {
            foreach ($fragment['paths'] as $path => $operations) {
                if (! is_string($path) || ! is_array($operations)) {
                    continue;
                }

                $document['paths'][$path] = array_merge(
                    is_array($document['paths'][$path] ?? null) ? $document['paths'][$path] : [],
                    $operations,
                );
            }
        }

        if (is_array($fragment['components'] ?? null)) {
            $document['components'] = $this->mergeRecursiveDistinct($document['components'], $fragment['components']);
        }

        if (is_array($fragment['tags'] ?? null)) {
            $document['tags'] = $this->mergeTags($document['tags'], $fragment['tags']);
        }

        return $document;
    }

    /**
     * @param  array<int, mixed>  $baseTags
     * @param  array<int, mixed>  $appendTags
     * @return array<int, mixed>
     */
    private function mergeTags(array $baseTags, array $appendTags): array
    {
        $byName = [];

        foreach ($baseTags as $tag) {
            if (is_array($tag) && is_string($tag['name'] ?? null)) {
                $byName[$tag['name']] = $tag;
            }
        }

        foreach ($appendTags as $tag) {
            if (is_array($tag) && is_string($tag['name'] ?? null)) {
                $byName[$tag['name']] = $tag;
            }
        }

        return array_values($byName);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $append
     * @return array<string, mixed>
     */
    private function mergeRecursiveDistinct(array $base, array $append): array
    {
        foreach ($append as $key => $value) {
            if (is_string($key) && isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
