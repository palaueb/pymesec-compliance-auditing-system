<?php

namespace PymeSec\Plugins\ContinuityBcm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContinuityBcmRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allServices(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('continuity_services')
            ->where('organization_id', $organizationId)
            ->orderBy('impact_tier')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($service): array => $this->mapService($service))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findService(string $serviceId): ?array
    {
        $service = DB::table('continuity_services')->where('id', $serviceId)->first();

        return $service !== null ? $this->mapService($service) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createService(array $data): array
    {
        $id = $this->nextId('continuity-service', (string) ($data['title'] ?? 'continuity-service'), 'continuity_services');

        DB::table('continuity_services')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'impact_tier' => $data['impact_tier'],
            'recovery_time_objective_hours' => (int) $data['recovery_time_objective_hours'],
            'recovery_point_objective_hours' => (int) $data['recovery_point_objective_hours'],
            'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
            'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $service */
        $service = $this->findService($id);

        return $service;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateService(string $serviceId, array $data): ?array
    {
        $updated = DB::table('continuity_services')
            ->where('id', $serviceId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'impact_tier' => $data['impact_tier'],
                'recovery_time_objective_hours' => (int) $data['recovery_time_objective_hours'],
                'recovery_point_objective_hours' => (int) $data['recovery_point_objective_hours'],
                'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
                'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findService($serviceId);
        }

        return $this->findService($serviceId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function allPlans(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('continuity_recovery_plans')
            ->where('organization_id', $organizationId)
            ->orderBy('test_due_on')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($plan): array => $this->mapPlan($plan))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function plansForService(string $serviceId): array
    {
        return DB::table('continuity_recovery_plans')
            ->where('service_id', $serviceId)
            ->orderBy('test_due_on')
            ->orderBy('title')
            ->get()
            ->map(fn ($plan): array => $this->mapPlan($plan))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findPlan(string $planId): ?array
    {
        $plan = DB::table('continuity_recovery_plans')->where('id', $planId)->first();

        return $plan !== null ? $this->mapPlan($plan) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createPlan(string $serviceId, array $data): array
    {
        $id = $this->nextId('continuity-plan', (string) ($data['title'] ?? 'continuity-plan'), 'continuity_recovery_plans');

        DB::table('continuity_recovery_plans')->insert([
            'id' => $id,
            'service_id' => $serviceId,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'strategy_summary' => $data['strategy_summary'],
            'test_due_on' => ($data['test_due_on'] ?? null) ?: null,
            'linked_policy_id' => ($data['linked_policy_id'] ?? null) ?: null,
            'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $plan */
        $plan = $this->findPlan($id);

        return $plan;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updatePlan(string $planId, array $data): ?array
    {
        $updated = DB::table('continuity_recovery_plans')
            ->where('id', $planId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'strategy_summary' => $data['strategy_summary'],
                'test_due_on' => ($data['test_due_on'] ?? null) ?: null,
                'linked_policy_id' => ($data['linked_policy_id'] ?? null) ?: null,
                'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findPlan($planId);
        }

        return $this->findPlan($planId);
    }

    private function nextId(string $prefix, string $value, string $table): string
    {
        $base = $prefix.'-'.Str::slug($value);
        $candidate = $base !== $prefix.'-' ? $base : $prefix.'-'.Str::lower(Str::ulid());

        if (! DB::table($table)->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array<string, string>
     */
    private function mapService(object $service): array
    {
        return [
            'id' => (string) $service->id,
            'organization_id' => (string) $service->organization_id,
            'scope_id' => is_string($service->scope_id) ? $service->scope_id : '',
            'title' => (string) $service->title,
            'impact_tier' => (string) $service->impact_tier,
            'recovery_time_objective_hours' => (string) $service->recovery_time_objective_hours,
            'recovery_point_objective_hours' => (string) $service->recovery_point_objective_hours,
            'linked_asset_id' => is_string($service->linked_asset_id) ? $service->linked_asset_id : '',
            'linked_risk_id' => is_string($service->linked_risk_id) ? $service->linked_risk_id : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapPlan(object $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'service_id' => (string) $plan->service_id,
            'organization_id' => (string) $plan->organization_id,
            'scope_id' => is_string($plan->scope_id) ? $plan->scope_id : '',
            'title' => (string) $plan->title,
            'strategy_summary' => (string) $plan->strategy_summary,
            'test_due_on' => is_string($plan->test_due_on) ? $plan->test_due_on : '',
            'linked_policy_id' => is_string($plan->linked_policy_id) ? $plan->linked_policy_id : '',
            'linked_finding_id' => is_string($plan->linked_finding_id) ? $plan->linked_finding_id : '',
        ];
    }
}
