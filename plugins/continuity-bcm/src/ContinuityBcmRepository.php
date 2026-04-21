<?php

namespace PymeSec\Plugins\ContinuityBcm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Security\ContextualReferenceValidator;

class ContinuityBcmRepository
{
    private const ID_MAX_LENGTH = 120;

    private const ID_RANDOM_SUFFIX_LENGTH = 4;

    public function __construct(
        private readonly ContextualReferenceValidator $references,
    ) {}

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
     * @param  array<int, string>  $serviceIds
     * @return array<string, array<int, array<string, string>>>
     */
    public function dependenciesForServices(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('continuity_service_dependencies as dependencies')
            ->join('continuity_services as target_services', function ($join): void {
                $join->on('target_services.id', '=', 'dependencies.depends_on_service_id')
                    ->on('target_services.organization_id', '=', 'dependencies.organization_id');
            })
            ->whereIn('dependencies.source_service_id', $serviceIds)
            ->orderBy('target_services.title')
            ->get([
                'dependencies.source_service_id',
                'dependencies.depends_on_service_id',
                'dependencies.dependency_kind',
                'dependencies.recovery_notes',
                'target_services.title as target_service_title',
            ]);

        foreach ($rows as $row) {
            $grouped[(string) $row->source_service_id][] = [
                'depends_on_service_id' => (string) $row->depends_on_service_id,
                'depends_on_service_title' => (string) $row->target_service_title,
                'dependency_kind' => (string) $row->dependency_kind,
                'recovery_notes' => is_string($row->recovery_notes) ? $row->recovery_notes : '',
            ];
        }

        return $grouped;
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
        $scopeId = ($data['scope_id'] ?? null) ?: null;

        $this->references->assertRecord(
            recordId: ($data['linked_asset_id'] ?? null) ?: null,
            table: 'assets',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_asset_id',
            message: 'The selected linked asset is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_risk_id'] ?? null) ?: null,
            table: 'risks',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_risk_id',
            message: 'The selected linked risk is invalid for this organization or scope.',
        );

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
     */
    public function addServiceDependency(string $serviceId, array $data): void
    {
        $service = $this->findService($serviceId);
        $dependsOnService = $this->findService((string) $data['depends_on_service_id']);

        if ($service === null || $service['organization_id'] !== (string) $data['organization_id']) {
            throw ValidationException::withMessages([
                'service_id' => 'The selected continuity service is invalid for this organization.',
            ]);
        }

        if ($dependsOnService === null || $dependsOnService['organization_id'] !== $service['organization_id']) {
            throw ValidationException::withMessages([
                'depends_on_service_id' => 'The selected dependency is invalid for this organization.',
            ]);
        }

        if ($serviceId === (string) $data['depends_on_service_id']) {
            throw ValidationException::withMessages([
                'depends_on_service_id' => 'A service cannot depend on itself.',
            ]);
        }

        $existing = DB::table('continuity_service_dependencies')
            ->where('organization_id', $service['organization_id'])
            ->where('source_service_id', $serviceId)
            ->where('depends_on_service_id', (string) $data['depends_on_service_id'])
            ->exists();

        if ($existing) {
            DB::table('continuity_service_dependencies')
                ->where('organization_id', $service['organization_id'])
                ->where('source_service_id', $serviceId)
                ->where('depends_on_service_id', (string) $data['depends_on_service_id'])
                ->update([
                    'dependency_kind' => (string) $data['dependency_kind'],
                    'recovery_notes' => ($data['recovery_notes'] ?? null) ?: null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('continuity_service_dependencies')->insert([
            'id' => $this->nextId('continuity-dependency', $serviceId.'-'.(string) $data['depends_on_service_id'], 'continuity_service_dependencies'),
            'organization_id' => $service['organization_id'],
            'source_service_id' => $serviceId,
            'depends_on_service_id' => (string) $data['depends_on_service_id'],
            'dependency_kind' => (string) $data['dependency_kind'],
            'recovery_notes' => ($data['recovery_notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateService(string $serviceId, array $data): ?array
    {
        $scopeId = ($data['scope_id'] ?? null) ?: null;

        $this->references->assertRecord(
            recordId: ($data['linked_asset_id'] ?? null) ?: null,
            table: 'assets',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_asset_id',
            message: 'The selected linked asset is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_risk_id'] ?? null) ?: null,
            table: 'risks',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_risk_id',
            message: 'The selected linked risk is invalid for this organization or scope.',
        );

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
     * @param  array<int, string>  $planIds
     * @return array<string, array<int, array<string, string>>>
     */
    public function exercisesForPlans(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('continuity_plan_exercises')
            ->whereIn('plan_id', $planIds)
            ->orderByDesc('exercise_date')
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $row) {
            $grouped[(string) $row->plan_id][] = $this->mapExercise($row);
        }

        return $grouped;
    }

    /**
     * @param  array<int, string>  $planIds
     * @return array<string, array<int, array<string, string>>>
     */
    public function testExecutionsForPlans(array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('continuity_plan_test_executions')
            ->whereIn('plan_id', $planIds)
            ->orderByDesc('executed_on')
            ->orderByDesc('created_at')
            ->get();

        foreach ($rows as $row) {
            $grouped[(string) $row->plan_id][] = $this->mapExecution($row);
        }

        return $grouped;
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
        $scopeId = ($data['scope_id'] ?? null) ?: null;

        $this->references->assertRecord(
            recordId: ($data['linked_policy_id'] ?? null) ?: null,
            table: 'policies',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_policy_id',
            message: 'The selected linked policy is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_finding_id'] ?? null) ?: null,
            table: 'findings',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_finding_id',
            message: 'The selected linked finding is invalid for this organization or scope.',
        );

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
     */
    public function recordExercise(string $planId, array $data): void
    {
        $plan = $this->findPlan($planId);

        if ($plan === null || $plan['organization_id'] !== (string) $data['organization_id']) {
            throw ValidationException::withMessages([
                'plan_id' => 'The selected recovery plan is invalid for this organization.',
            ]);
        }

        DB::table('continuity_plan_exercises')->insert([
            'id' => $this->nextId('continuity-exercise', $planId.'-'.(string) $data['exercise_date'].'-'.(string) $data['exercise_type'], 'continuity_plan_exercises'),
            'organization_id' => $plan['organization_id'],
            'plan_id' => $planId,
            'exercise_date' => $data['exercise_date'],
            'exercise_type' => $data['exercise_type'],
            'scenario_summary' => $data['scenario_summary'],
            'outcome' => $data['outcome'],
            'follow_up_summary' => ($data['follow_up_summary'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function recordTestExecution(string $planId, array $data): void
    {
        $plan = $this->findPlan($planId);

        if ($plan === null || $plan['organization_id'] !== (string) $data['organization_id']) {
            throw ValidationException::withMessages([
                'plan_id' => 'The selected recovery plan is invalid for this organization.',
            ]);
        }

        DB::table('continuity_plan_test_executions')->insert([
            'id' => $this->nextId('continuity-test', $planId.'-'.(string) $data['executed_on'].'-'.(string) $data['execution_type'], 'continuity_plan_test_executions'),
            'organization_id' => $plan['organization_id'],
            'plan_id' => $planId,
            'executed_on' => $data['executed_on'],
            'execution_type' => $data['execution_type'],
            'status' => $data['status'],
            'participants' => ($data['participants'] ?? null) ?: null,
            'notes' => ($data['notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updatePlan(string $planId, array $data): ?array
    {
        $scopeId = ($data['scope_id'] ?? null) ?: null;

        $this->references->assertRecord(
            recordId: ($data['linked_policy_id'] ?? null) ?: null,
            table: 'policies',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_policy_id',
            message: 'The selected linked policy is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_finding_id'] ?? null) ?: null,
            table: 'findings',
            organizationId: (string) $data['organization_id'],
            scopeId: is_string($scopeId) ? $scopeId : null,
            field: 'linked_finding_id',
            message: 'The selected linked finding is invalid for this organization or scope.',
        );

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
        $slug = Str::slug($value);
        $base = $slug !== '' ? $prefix.'-'.$slug : $prefix.'-'.Str::lower(Str::ulid());
        $candidate = $this->limitId($base);

        if (! DB::table($table)->where('id', $candidate)->exists()) {
            return $candidate;
        }

        do {
            $suffix = '-'.Str::lower(Str::random(self::ID_RANDOM_SUFFIX_LENGTH));
            $candidate = $this->limitId($base, strlen($suffix)).$suffix;
        } while (DB::table($table)->where('id', $candidate)->exists());

        return $candidate;
    }

    private function limitId(string $id, int $reservedLength = 0): string
    {
        $maxLength = max(1, self::ID_MAX_LENGTH - $reservedLength);

        return rtrim(Str::substr($id, 0, $maxLength), '-');
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

    /**
     * @return array<string, string>
     */
    private function mapExercise(object $exercise): array
    {
        return [
            'id' => (string) $exercise->id,
            'organization_id' => (string) $exercise->organization_id,
            'plan_id' => (string) $exercise->plan_id,
            'exercise_date' => (string) $exercise->exercise_date,
            'exercise_type' => (string) $exercise->exercise_type,
            'scenario_summary' => (string) $exercise->scenario_summary,
            'outcome' => (string) $exercise->outcome,
            'follow_up_summary' => is_string($exercise->follow_up_summary) ? $exercise->follow_up_summary : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapExecution(object $execution): array
    {
        return [
            'id' => (string) $execution->id,
            'organization_id' => (string) $execution->organization_id,
            'plan_id' => (string) $execution->plan_id,
            'executed_on' => (string) $execution->executed_on,
            'execution_type' => (string) $execution->execution_type,
            'status' => (string) $execution->status,
            'participants' => is_string($execution->participants) ? $execution->participants : '',
            'notes' => is_string($execution->notes) ? $execution->notes : '',
        ];
    }
}
