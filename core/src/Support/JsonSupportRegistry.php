<?php

namespace PymeSec\Core\Support;

use Illuminate\Support\Facades\File;
use PymeSec\Core\Plugins\Contracts\PluginManagerInterface;
use PymeSec\Core\Support\Contracts\SupportRegistryInterface;

class JsonSupportRegistry implements SupportRegistryInterface
{
    public function __construct(
        private readonly PluginManagerInterface $plugins,
    ) {}

    public function catalogue(string $locale = 'en'): array
    {
        $sources = [[
            'owner' => 'core',
            'name' => 'Core',
            'path' => resource_path('support'),
            'supported_locales' => ['en', 'es', 'fr', 'de'],
        ]];

        foreach ($this->plugins->status() as $plugin) {
            if (($plugin['enabled'] ?? false) !== true || ($plugin['booted'] ?? false) !== true) {
                continue;
            }

            $supportPath = $plugin['support_path'] ?? null;

            if (! is_string($supportPath) || trim($supportPath) === '') {
                continue;
            }

            $pluginPath = $plugin['path'] ?? null;

            if (! is_string($pluginPath) || trim($pluginPath) === '') {
                continue;
            }

            $sources[] = [
                'owner' => (string) ($plugin['id'] ?? 'plugin'),
                'name' => (string) ($plugin['name'] ?? ($plugin['id'] ?? 'Plugin')),
                'path' => rtrim($pluginPath, '/').'/'.trim($supportPath, '/'),
                'supported_locales' => is_array($plugin['support_locales'] ?? null)
                    ? array_values(array_filter($plugin['support_locales'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
                    : ['en'],
            ];
        }

        $guide = [];
        $concepts = [];
        $issues = [];

        foreach ($sources as $source) {
            $document = $this->loadDocument(
                path: (string) $source['path'],
                locale: $locale,
                supportedLocales: $source['supported_locales'],
            );

            if ($document === null) {
                continue;
            }

            foreach ($this->normalizeGuideEntries($document['guide'] ?? [], $source) as $entry) {
                $guide[] = $entry;
            }

            foreach ($this->normalizeConceptEntries($document['concepts'] ?? [], $source) as $entry) {
                if (isset($concepts[$entry['id']])) {
                    $issues[] = sprintf(
                        'Duplicate support concept [%s] declared by [%s] and [%s].',
                        $entry['id'],
                        $concepts[$entry['id']]['owner'],
                        $entry['owner'],
                    );

                    continue;
                }

                $concepts[$entry['id']] = $entry;
            }
        }

        usort($guide, static fn (array $left, array $right): int => [$left['order'], $left['title']] <=> [$right['order'], $right['title']]);
        uasort($concepts, static fn (array $left, array $right): int => [$left['order'], $left['label']] <=> [$right['order'], $right['label']]);

        $conceptMap = $concepts;
        $relationshipIndex = [];

        foreach ($concepts as $conceptId => $concept) {
            $related = [];

            foreach ($concept['relations'] as $relation) {
                $targetId = $relation['target'];
                $related[] = [
                    ...$relation,
                    'target_label' => $conceptMap[$targetId]['label'] ?? $targetId,
                ];

                $relationshipIndex[] = [
                    'source_id' => $conceptId,
                    'source_label' => $concept['label'],
                    'type' => $relation['type'],
                    'target_id' => $targetId,
                    'target_label' => $conceptMap[$targetId]['label'] ?? $targetId,
                ];
            }

            $concepts[$conceptId]['relations'] = $related;
        }

        usort($relationshipIndex, static fn (array $left, array $right): int => [$left['source_label'], $left['type'], $left['target_label']] <=> [$right['source_label'], $right['type'], $right['target_label']]);

        return [
            'guide' => array_values($guide),
            'concepts' => array_values($concepts),
            'concept_index' => array_map(static fn (array $concept): array => [
                'id' => $concept['id'],
                'label' => $concept['label'],
                'owner' => $concept['owner'],
                'owner_name' => $concept['owner_name'],
                'category' => $concept['category'],
                'summary' => $concept['summary'],
            ], array_values($concepts)),
            'relationships' => $relationshipIndex,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    private function normalizeGuideEntries(array $entries, array $source): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || ! is_string($entry['title'] ?? null)) {
                continue;
            }

            $body = is_array($entry['body'] ?? null)
                ? array_values(array_filter($entry['body'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
                : [];

            $normalized[] = [
                'id' => $entry['id'],
                'title' => $entry['title'],
                'summary' => is_string($entry['summary'] ?? null) ? $entry['summary'] : '',
                'body' => $body,
                'concept_ids' => is_array($entry['concept_ids'] ?? null)
                    ? array_values(array_filter($entry['concept_ids'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
                    : [],
                'order' => is_numeric($entry['order'] ?? null) ? (int) $entry['order'] : 100,
                'owner' => $source['owner'],
                'owner_name' => $source['name'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConceptEntries(array $entries, array $source): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || ! is_string($entry['label'] ?? null)) {
                continue;
            }

            $howToUse = is_array($entry['how_to_use'] ?? null)
                ? array_values(array_filter($entry['how_to_use'], static fn (mixed $value): bool => is_string($value) && $value !== ''))
                : [];

            $relations = [];

            foreach (($entry['relations'] ?? []) as $relation) {
                if (! is_array($relation) || ! is_string($relation['type'] ?? null) || ! is_string($relation['target'] ?? null)) {
                    continue;
                }

                $relations[] = [
                    'type' => $relation['type'],
                    'target' => $relation['target'],
                    'summary' => is_string($relation['summary'] ?? null) ? $relation['summary'] : '',
                ];
            }

            $normalized[] = [
                'id' => $entry['id'],
                'label' => $entry['label'],
                'category' => is_string($entry['category'] ?? null) ? $entry['category'] : 'general',
                'summary' => is_string($entry['summary'] ?? null) ? $entry['summary'] : '',
                'why_it_exists' => is_string($entry['why_it_exists'] ?? null) ? $entry['why_it_exists'] : '',
                'how_to_use' => $howToUse,
                'relations' => $relations,
                'order' => is_numeric($entry['order'] ?? null) ? (int) $entry['order'] : 100,
                'owner' => $source['owner'],
                'owner_name' => $source['name'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $supportedLocales
     * @return array<string, mixed>|null
     */
    private function loadDocument(string $path, string $locale, array $supportedLocales): ?array
    {
        $supported = $supportedLocales !== [] ? $supportedLocales : ['en'];
        $preferred = in_array($locale, $supported, true) ? $locale : 'en';
        $candidates = array_values(array_unique([$preferred, 'en']));

        foreach ($candidates as $candidate) {
            $file = rtrim($path, '/').'/'.$candidate.'.json';

            if (! File::exists($file)) {
                continue;
            }

            $decoded = json_decode((string) File::get($file), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
