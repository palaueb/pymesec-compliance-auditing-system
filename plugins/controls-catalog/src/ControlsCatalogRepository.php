<?php

namespace PymeSec\Plugins\ControlsCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        DB::table('controls')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'name' => $data['name'],
            'framework' => $data['framework'],
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
        $updated = DB::table('controls')
            ->where('id', $controlId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'name' => $data['name'],
                'framework' => $data['framework'],
                'domain' => $data['domain'],
                'evidence' => $data['evidence'],
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->find($controlId);
        }

        return $this->find($controlId);
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

    /**
     * @return array<string, string>
     */
    private function mapControl(object $control): array
    {
        return [
            'id' => (string) $control->id,
            'organization_id' => (string) $control->organization_id,
            'scope_id' => is_string($control->scope_id) ? $control->scope_id : '',
            'name' => (string) $control->name,
            'framework' => (string) $control->framework,
            'domain' => (string) $control->domain,
            'evidence' => (string) $control->evidence,
        ];
    }
}
