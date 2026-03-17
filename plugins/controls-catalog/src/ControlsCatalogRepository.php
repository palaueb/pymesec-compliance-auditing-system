<?php

namespace PymeSec\Plugins\ControlsCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Menus\MenuLabelResolver;

class ControlsCatalogRepository
{
    public function __construct(
        private readonly MenuLabelResolver $translator,
    ) {}
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('controls')
            ->where('organization_id', $organizationId)
            ->orderBy('name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($control): array => $this->mapControl($control))
            ->all();
    }

    /**
     * Returns all frameworks visible to the given organization:
     *   - Global framework packs (organization_id IS NULL)
     *   - Custom frameworks owned by this organization
     *
     * @return array<int, array<string, string>>
     */
    public function frameworks(string $organizationId): array
    {
        if (! $this->hasFrameworkTables()) {
            return [];
        }

        return DB::table('frameworks')
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $organizationId);
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($framework): array => $this->mapFramework($framework))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findFramework(string $organizationId, string $frameworkId): ?array
    {
        if (! $this->hasFrameworkTables()) {
            return null;
        }

        $framework = DB::table('frameworks')
            ->where('id', $frameworkId)
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $organizationId);
            })
            ->first();

        return $framework !== null ? $this->mapFramework($framework) : null;
    }

    /**
     * Returns framework elements (requirements/clauses/articles) visible to the org.
     * Optionally filtered by framework.
     *
     * @return array<int, array<string, string>>
     */
    public function requirements(string $organizationId, ?string $frameworkId = null): array
    {
        if (! $this->hasFrameworkTables()) {
            return [];
        }

        $query = DB::table('framework_elements as elements')
            ->join('frameworks', function ($join): void {
                $join->on('frameworks.id', '=', 'elements.framework_id');
            })
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('frameworks.organization_id')
                    ->orWhere('frameworks.organization_id', $organizationId);
            })
            ->orderBy('frameworks.name')
            ->orderBy('elements.sort_order');

        if (is_string($frameworkId) && $frameworkId !== '') {
            $query->where('elements.framework_id', $frameworkId);
        }

        return $query->get([
            'elements.id',
            'elements.framework_id',
            'elements.parent_id',
            'elements.code',
            'elements.title',
            'elements.description',
            'elements.element_type',
            'elements.applicability_level',
            'frameworks.code as framework_code',
            'frameworks.name as framework_name',
            'frameworks.organization_id as framework_organization_id',
        ])->map(fn ($element): array => $this->mapRequirement($element))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findRequirement(string $organizationId, string $requirementId): ?array
    {
        if (! $this->hasFrameworkTables()) {
            return null;
        }

        $element = DB::table('framework_elements as elements')
            ->join('frameworks', function ($join): void {
                $join->on('frameworks.id', '=', 'elements.framework_id');
            })
            ->where('elements.id', $requirementId)
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('frameworks.organization_id')
                    ->orWhere('frameworks.organization_id', $organizationId);
            })
            ->first([
                'elements.id',
                'elements.framework_id',
                'elements.parent_id',
                'elements.code',
                'elements.title',
                'elements.description',
                'elements.element_type',
                'elements.applicability_level',
                'frameworks.code as framework_code',
                'frameworks.name as framework_name',
                'frameworks.organization_id as framework_organization_id',
            ]);

        return $element !== null ? $this->mapRequirement($element) : null;
    }

    /**
     * Returns framework elements mapped to the given controls, grouped by control ID.
     *
     * @param  array<int, string>  $controlIds
     * @return array<string, array<int, array<string, string>>>
     */
    public function requirementsForControls(array $controlIds): array
    {
        if ($controlIds === [] || ! $this->hasFrameworkTables() || ! Schema::hasTable('control_requirement_mappings')) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('control_requirement_mappings as mappings')
            ->join('framework_elements as elements', 'elements.id', '=', 'mappings.framework_element_id')
            ->join('frameworks', 'frameworks.id', '=', 'elements.framework_id')
            ->whereIn('mappings.control_id', $controlIds)
            ->orderBy('frameworks.name')
            ->orderBy('elements.sort_order')
            ->get([
                'mappings.control_id',
                'mappings.coverage',
                'mappings.notes',
                'elements.id as element_id',
                'elements.code as element_code',
                'elements.title as element_title',
                'frameworks.id as framework_id',
                'frameworks.code as framework_code',
                'frameworks.name as framework_name',
            ]);

        foreach ($rows as $row) {
            $grouped[(string) $row->control_id][] = [
                'requirement_id' => (string) $row->element_id,
                'requirement_code' => (string) $row->element_code,
                'requirement_title' => $this->translateIfKey((string) $row->element_title),
                'framework_id' => (string) $row->framework_id,
                'framework_code' => (string) $row->framework_code,
                'framework_name' => $this->translateIfKey((string) $row->framework_name),
                'coverage' => (string) $row->coverage,
                'notes' => is_string($row->notes) ? $row->notes : '',
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, string>|null
     */
    public function find(string $controlId): ?array
    {
        $control = DB::table('controls')->where('id', $controlId)->first();

        return $control !== null ? $this->mapControl($control) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function create(array $data): array
    {
        $id = $this->nextId((string) ($data['name'] ?? 'control'));
        [$frameworkId, $frameworkLabel] = $this->resolveFramework(
            (string) $data['organization_id'],
            $data['framework_id'] ?? null,
            $data['framework'] ?? null,
        );

        DB::table('controls')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'framework_id' => $frameworkId,
            'framework_element_id' => null,
            'name' => $data['name'],
            'framework' => $frameworkLabel,
            'domain' => $data['domain'],
            'evidence' => $data['evidence'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $control */
        $control = $this->find($id);

        return $control;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function update(string $controlId, array $data): ?array
    {
        [$frameworkId, $frameworkLabel] = $this->resolveFramework(
            (string) $data['organization_id'],
            $data['framework_id'] ?? null,
            $data['framework'] ?? null,
        );

        DB::table('controls')
            ->where('id', $controlId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'framework_id' => $frameworkId,
                'name' => $data['name'],
                'framework' => $frameworkLabel,
                'domain' => $data['domain'],
                'evidence' => $data['evidence'],
                'updated_at' => now(),
            ]);

        return $this->find($controlId);
    }

    /**
     * Creates a custom org-level framework (not a global pack).
     *
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createFramework(array $data): array
    {
        $this->assertFrameworkTablesAvailable();

        $id = $this->nextScopedId(
            table: 'frameworks',
            prefix: 'framework',
            seed: (string) ($data['code'] ?? $data['name'] ?? 'framework'),
        );

        DB::table('frameworks')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'version' => null,
            'description' => ($data['description'] ?? null) ?: null,
            'kind' => 'custom',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $framework */
        $framework = $this->findFramework((string) $data['organization_id'], $id);

        return $framework;
    }

    /**
     * Creates a framework element (requirement/clause) inside any framework visible to the org.
     *
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createRequirement(array $data): array
    {
        $this->assertFrameworkTablesAvailable();

        $framework = $this->findFramework((string) $data['organization_id'], (string) $data['framework_id']);

        if ($framework === null) {
            throw ValidationException::withMessages([
                'framework_id' => 'The selected framework is invalid for this organization.',
            ]);
        }

        $id = $this->nextScopedId(
            table: 'framework_elements',
            prefix: 'requirement',
            seed: (string) ($data['code'] ?? $data['title'] ?? 'requirement'),
        );

        DB::table('framework_elements')->insert([
            'id' => $id,
            'framework_id' => $framework['id'],
            'parent_id' => null,
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => ($data['description'] ?? null) ?: null,
            'element_type' => 'control',
            'applicability_level' => null,
            'sort_order' => 0,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $requirement */
        $requirement = $this->findRequirement((string) $data['organization_id'], $id);

        return $requirement;
    }

    public function attachRequirement(
        string $controlId,
        string $requirementId,
        string $organizationId,
        string $coverage = 'supports',
        ?string $notes = null,
    ): void {
        $this->assertFrameworkTablesAvailable();

        $control = $this->find($controlId);

        if ($control === null || $control['organization_id'] !== $organizationId) {
            throw ValidationException::withMessages([
                'control_id' => 'The selected control is invalid for this organization.',
            ]);
        }

        $requirement = $this->findRequirement($organizationId, $requirementId);

        if ($requirement === null) {
            throw ValidationException::withMessages([
                'requirement_id' => 'The selected requirement is invalid for this organization.',
            ]);
        }

        $existing = DB::table('control_requirement_mappings')
            ->where('organization_id', $organizationId)
            ->where('control_id', $controlId)
            ->where('framework_element_id', $requirementId)
            ->first();

        if ($existing !== null) {
            DB::table('control_requirement_mappings')
                ->where('organization_id', $organizationId)
                ->where('control_id', $controlId)
                ->where('framework_element_id', $requirementId)
                ->update([
                    'coverage' => $coverage,
                    'notes' => $notes !== null && $notes !== '' ? $notes : null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('control_requirement_mappings')->insert([
            'id' => $this->nextScopedId(
                table: 'control_requirement_mappings',
                prefix: 'mapping',
                seed: $controlId.'-'.$requirementId,
            ),
            'organization_id' => $organizationId,
            'control_id' => $controlId,
            'framework_element_id' => $requirementId,
            'coverage' => $coverage,
            'notes' => $notes !== null && $notes !== '' ? $notes : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextId(string $name): string
    {
        $base = 'control-'.Str::slug($name);
        $candidate = $base !== 'control-' ? $base : 'control-'.Str::lower(Str::ulid());

        if (! DB::table('controls')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function nextScopedId(string $table, string $prefix, string $seed): string
    {
        $base = $prefix.'-'.Str::slug($seed);
        $candidate = $base !== $prefix.'-' ? $base : $prefix.'-'.Str::lower(Str::ulid());

        if (! DB::table($table)->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function resolveFramework(string $organizationId, ?string $frameworkId, ?string $frameworkLabel): array
    {
        if (! $this->hasFrameworkTables()) {
            if (is_string($frameworkLabel) && $frameworkLabel !== '') {
                return [null, $frameworkLabel];
            }

            if (is_string($frameworkId) && $frameworkId !== '') {
                return [null, $frameworkId];
            }
        }

        if (is_string($frameworkId) && $frameworkId !== '') {
            $framework = $this->findFramework($organizationId, $frameworkId);

            if ($framework === null) {
                throw ValidationException::withMessages([
                    'framework_id' => 'The selected framework is invalid for this organization.',
                ]);
            }

            return [$framework['id'], $framework['name']];
        }

        if (is_string($frameworkLabel) && $frameworkLabel !== '') {
            return [null, $frameworkLabel];
        }

        throw ValidationException::withMessages([
            'framework_id' => 'A framework is required.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function mapFramework(object $framework): array
    {
        return [
            'id' => (string) $framework->id,
            'organization_id' => is_string($framework->organization_id) ? $framework->organization_id : '',
            'code' => (string) $framework->code,
            'name' => $this->translateIfKey((string) $framework->name),
            'description' => $this->translateIfKey(is_string($framework->description) ? $framework->description : ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapRequirement(object $element): array
    {
        return [
            'id' => (string) $element->id,
            // Backward-compatible: consumers expect organization_id; for global elements it is empty
            'organization_id' => is_string($element->framework_organization_id) ? $element->framework_organization_id : '',
            'framework_id' => (string) $element->framework_id,
            'framework_code' => (string) $element->framework_code,
            'framework_name' => $this->translateIfKey((string) $element->framework_name),
            'code' => is_string($element->code) ? $element->code : '',
            'title' => $this->translateIfKey((string) $element->title),
            'description' => $this->translateIfKey(is_string($element->description) ? $element->description : ''),
            'element_type' => (string) $element->element_type,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapControl(object $control): array
    {
        return [
            'id' => (string) $control->id,
            'organization_id' => (string) $control->organization_id,
            'scope_id' => is_string($control->scope_id) ? $control->scope_id : '',
            'framework_id' => is_string($control->framework_id) ? $control->framework_id : '',
            'framework_element_id' => property_exists($control, 'framework_element_id') && is_string($control->framework_element_id) ? $control->framework_element_id : '',
            'name' => (string) $control->name,
            'framework' => (string) $control->framework,
            'domain' => (string) $control->domain,
            'evidence' => (string) $control->evidence,
        ];
    }

    /**
     * Resolves a translation key of the form plugin.<plugin-id>.* using the plugin's lang file.
     * Values that are not translation keys (custom org framework text) are returned unchanged.
     */
    private function translateIfKey(string $value): string
    {
        if (! str_starts_with($value, 'plugin.')) {
            return $value;
        }

        $parts = explode('.', $value, 3);

        if (count($parts) < 2 || $parts[1] === '') {
            return $value;
        }

        $pluginId = $parts[1];
        $locale = app()->getLocale();

        return $this->translator->label($pluginId, $value, $locale);
    }

    private function hasFrameworkTables(): bool
    {
        return Schema::hasTable('frameworks') && Schema::hasTable('framework_elements');
    }

    private function assertFrameworkTablesAvailable(): void
    {
        if ($this->hasFrameworkTables()) {
            return;
        }

        throw ValidationException::withMessages([
            'framework_id' => 'Framework catalog tables are not available yet. Run the latest migrations first.',
        ]);
    }
}
