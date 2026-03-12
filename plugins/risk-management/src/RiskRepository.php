<?php

namespace PymeSec\Plugins\RiskManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiskRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('risks')
            ->where('organization_id', $organizationId)
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($risk): array => $this->mapRisk($risk))
            ->all();
    }

    /**
     * @return array<string, string> | null
     */
    public function find(string $riskId): ?array
    {
        $risk = DB::table('risks')->where('id', $riskId)->first();

        return $risk !== null ? $this->mapRisk($risk) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function create(array $data): array
    {
        $id = $this->nextId((string) ($data['title'] ?? 'risk'));

        DB::table('risks')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'category' => $data['category'],
            'inherent_score' => (int) $data['inherent_score'],
            'residual_score' => (int) $data['residual_score'],
            'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
            'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
            'treatment' => $data['treatment'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $risk */
        $risk = $this->find($id);

        return $risk;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string> | null
     */
    public function update(string $riskId, array $data): ?array
    {
        $updated = DB::table('risks')
            ->where('id', $riskId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'category' => $data['category'],
                'inherent_score' => (int) $data['inherent_score'],
                'residual_score' => (int) $data['residual_score'],
                'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
                'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
                'treatment' => $data['treatment'],
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->find($riskId);
        }

        return $this->find($riskId);
    }

    private function nextId(string $title): string
    {
        $base = 'risk-'.Str::slug($title);
        $candidate = $base !== 'risk-' ? $base : 'risk-'.Str::lower(Str::ulid());

        if (! DB::table('risks')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array<string, string>
     */
    private function mapRisk(object $risk): array
    {
        return [
            'id' => (string) $risk->id,
            'organization_id' => (string) $risk->organization_id,
            'scope_id' => is_string($risk->scope_id) ? $risk->scope_id : '',
            'title' => (string) $risk->title,
            'category' => (string) $risk->category,
            'inherent_score' => (string) $risk->inherent_score,
            'residual_score' => (string) $risk->residual_score,
            'linked_asset_id' => is_string($risk->linked_asset_id) ? $risk->linked_asset_id : '',
            'linked_control_id' => is_string($risk->linked_control_id) ? $risk->linked_control_id : '',
            'treatment' => (string) $risk->treatment,
        ];
    }
}
