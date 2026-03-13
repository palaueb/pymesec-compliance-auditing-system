<?php

namespace PymeSec\Plugins\ControlsCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControlsCatalogRepository
{
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
     * @return array<int, array<string, string>>
     */
    public function frameworks(string $organizationId): array
    {
        return DB::table('control_frameworks')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get()
            ->map(fn ($framework): array => $this->mapFramework($framework))
            ->all();
    }

    /**
     * @return array<string, string> | null
     */
    public function findFramework(string $organizationId, string $frameworkId): ?array
    {
        $framework = DB::table('control_frameworks')
            ->where('organization_id', $organizationId)
            ->where('id', $frameworkId)
            ->first();

        return $framework !== null ? $this->mapFramework($framework) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function requirements(string $organizationId, ?string $frameworkId = null): array
    {
        $query = DB::table('control_requirements as requirements')
            ->join('control_frameworks as frameworks', function ($join): void {
                $join->on('frameworks.id', '=', 'requirements.framework_id')
                    ->on('frameworks.organization_id', '=', 'requirements.organization_id');
            })
            ->where('requirements.organization_id', $organizationId)
            ->orderBy('frameworks.name')
            ->orderBy('requirements.code');

        if (is_string($frameworkId) && $frameworkId !== '') {
            $query->where('requirements.framework_id', $frameworkId);
        }

        return $query->get([
            'requirements.id',
            'requirements.organization_id',
            'requirements.framework_id',
            'requirements.code',
            'requirements.title',
            'requirements.description',
            'frameworks.code as framework_code',
            'frameworks.name as framework_name',
        ])->map(fn ($requirement): array => $this->mapRequirement($requirement))
            ->all();
    }

    /**
     * @return array<string, string> | null
     */
    public function findRequirement(string $organizationId, string $requirementId): ?array
    {
        $requirement = DB::table('control_requirements as requirements')
            ->join('control_frameworks as frameworks', function ($join): void {
                $join->on('frameworks.id', '=', 'requirements.framework_id')
                    ->on('frameworks.organization_id', '=', 'requirements.organization_id');
            })
            ->where('requirements.organization_id', $organizationId)
            ->where('requirements.id', $requirementId)
            ->first([
                'requirements.id',
                'requirements.organization_id',
                'requirements.framework_id',
                'requirements.code',
                'requirements.title',
                'requirements.description',
                'frameworks.code as framework_code',
                'frameworks.name as framework_name',
            ]);

        return $requirement !== null ? $this->mapRequirement($requirement) : null;
    }

    /**
     * @param  array<int, string>  $controlIds
     * @return array<string, array<int, array<string, string>>>
     */
    public function requirementsForControls(array $controlIds): array
    {
        if ($controlIds === []) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('control_requirement_mappings as mappings')
            ->join('control_requirements as requirements', function ($join): void {
                $join->on('requirements.id', '=', 'mappings.requirement_id')
                    ->on('requirements.organization_id', '=', 'mappings.organization_id');
            })
            ->join('control_frameworks as frameworks', function ($join): void {
                $join->on('frameworks.id', '=', 'requirements.framework_id')
                    ->on('frameworks.organization_id', '=', 'requirements.organization_id');
            })
            ->whereIn('mappings.control_id', $controlIds)
            ->orderBy('frameworks.name')
            ->orderBy('requirements.code')
            ->get([
                'mappings.control_id',
                'mappings.coverage',
                'mappings.notes',
                'requirements.id as requirement_id',
                'requirements.code as requirement_code',
                'requirements.title as requirement_title',
                'frameworks.id as framework_id',
                'frameworks.code as framework_code',
                'frameworks.name as framework_name',
            ]);

        foreach ($rows as $row) {
            $grouped[(string) $row->control_id][] = [
                'requirement_id' => (string) $row->requirement_id,
                'requirement_code' => (string) $row->requirement_code,
                'requirement_title' => (string) $row->requirement_title,
                'framework_id' => (string) $row->framework_id,
                'framework_code' => (string) $row->framework_code,
                'framework_name' => (string) $row->framework_name,
                'coverage' => (string) $row->coverage,
                'notes' => is_string($row->notes) ? $row->notes : '',
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, string> | null
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
     * @return array<string, string> | null
     */
    public function update(string $controlId, array $data): ?array
    {
        [$frameworkId, $frameworkLabel] = $this->resolveFramework(
            (string) $data['organization_id'],
            $data['framework_id'] ?? null,
            $data['framework'] ?? null,
        );

        $updated = DB::table('controls')
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

        if ($updated === 0) {
            return $this->find($controlId);
        }

        return $this->find($controlId);
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createFramework(array $data): array
    {
        $id = $this->nextScopedId(
            table: 'control_frameworks',
            prefix: 'framework',
            seed: (string) ($data['code'] ?? $data['name'] ?? 'framework'),
        );

        DB::table('control_frameworks')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => ($data['description'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $framework */
        $framework = $this->findFramework((string) $data['organization_id'], $id);

        return $framework;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createRequirement(array $data): array
    {
        $framework = $this->findFramework((string) $data['organization_id'], (string) $data['framework_id']);

        if ($framework === null) {
            throw ValidationException::withMessages([
                'framework_id' => 'The selected framework is invalid for this organization.',
            ]);
        }

        $id = $this->nextScopedId(
            table: 'control_requirements',
            prefix: 'requirement',
            seed: (string) ($data['code'] ?? $data['title'] ?? 'requirement'),
        );

        DB::table('control_requirements')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'framework_id' => $framework['id'],
            'code' => $data['code'],
            'title' => $data['title'],
            'description' => ($data['description'] ?? null) ?: null,
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
            ->where('requirement_id', $requirementId)
            ->first();

        if ($existing !== null) {
            DB::table('control_requirement_mappings')
                ->where('organization_id', $organizationId)
                ->where('control_id', $controlId)
                ->where('requirement_id', $requirementId)
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
            'requirement_id' => $requirementId,
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
            'organization_id' => (string) $framework->organization_id,
            'code' => (string) $framework->code,
            'name' => (string) $framework->name,
            'description' => is_string($framework->description) ? $framework->description : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapRequirement(object $requirement): array
    {
        return [
            'id' => (string) $requirement->id,
            'organization_id' => (string) $requirement->organization_id,
            'framework_id' => (string) $requirement->framework_id,
            'framework_code' => (string) $requirement->framework_code,
            'framework_name' => (string) $requirement->framework_name,
            'code' => (string) $requirement->code,
            'title' => (string) $requirement->title,
            'description' => is_string($requirement->description) ? $requirement->description : '',
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
            'name' => (string) $control->name,
            'framework' => (string) $control->framework,
            'domain' => (string) $control->domain,
            'evidence' => (string) $control->evidence,
        ];
    }
}
