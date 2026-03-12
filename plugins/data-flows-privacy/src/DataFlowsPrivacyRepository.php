<?php

namespace PymeSec\Plugins\DataFlowsPrivacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataFlowsPrivacyRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allDataFlows(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('privacy_data_flows')
            ->where('organization_id', $organizationId)
            ->orderBy('review_due_on')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($flow): array => $this->mapDataFlow($flow))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findDataFlow(string $flowId): ?array
    {
        $flow = DB::table('privacy_data_flows')->where('id', $flowId)->first();

        return $flow !== null ? $this->mapDataFlow($flow) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createDataFlow(array $data): array
    {
        $id = $this->nextId('data-flow', (string) ($data['title'] ?? 'data-flow'), 'privacy_data_flows');

        DB::table('privacy_data_flows')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'source' => $data['source'],
            'destination' => $data['destination'],
            'data_category_summary' => $data['data_category_summary'],
            'transfer_type' => $data['transfer_type'],
            'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
            'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
            'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $flow */
        $flow = $this->findDataFlow($id);

        return $flow;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateDataFlow(string $flowId, array $data): ?array
    {
        $updated = DB::table('privacy_data_flows')
            ->where('id', $flowId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'source' => $data['source'],
                'destination' => $data['destination'],
                'data_category_summary' => $data['data_category_summary'],
                'transfer_type' => $data['transfer_type'],
                'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
                'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
                'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findDataFlow($flowId);
        }

        return $this->findDataFlow($flowId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function allProcessingActivities(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('privacy_processing_activities')
            ->where('organization_id', $organizationId)
            ->orderBy('review_due_on')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($activity): array => $this->mapProcessingActivity($activity))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findProcessingActivity(string $activityId): ?array
    {
        $activity = DB::table('privacy_processing_activities')->where('id', $activityId)->first();

        return $activity !== null ? $this->mapProcessingActivity($activity) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createProcessingActivity(array $data): array
    {
        $id = $this->nextId('processing-activity', (string) ($data['title'] ?? 'processing-activity'), 'privacy_processing_activities');

        DB::table('privacy_processing_activities')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'purpose' => $data['purpose'],
            'lawful_basis' => $data['lawful_basis'],
            'linked_data_flow_ids' => ($data['linked_data_flow_ids'] ?? null) ?: null,
            'linked_risk_ids' => ($data['linked_risk_ids'] ?? null) ?: null,
            'linked_policy_id' => ($data['linked_policy_id'] ?? null) ?: null,
            'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
            'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $activity */
        $activity = $this->findProcessingActivity($id);

        return $activity;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateProcessingActivity(string $activityId, array $data): ?array
    {
        $updated = DB::table('privacy_processing_activities')
            ->where('id', $activityId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'purpose' => $data['purpose'],
                'lawful_basis' => $data['lawful_basis'],
                'linked_data_flow_ids' => ($data['linked_data_flow_ids'] ?? null) ?: null,
                'linked_risk_ids' => ($data['linked_risk_ids'] ?? null) ?: null,
                'linked_policy_id' => ($data['linked_policy_id'] ?? null) ?: null,
                'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
                'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findProcessingActivity($activityId);
        }

        return $this->findProcessingActivity($activityId);
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
    private function mapDataFlow(object $flow): array
    {
        return [
            'id' => (string) $flow->id,
            'organization_id' => (string) $flow->organization_id,
            'scope_id' => is_string($flow->scope_id) ? $flow->scope_id : '',
            'title' => (string) $flow->title,
            'source' => (string) $flow->source,
            'destination' => (string) $flow->destination,
            'data_category_summary' => (string) $flow->data_category_summary,
            'transfer_type' => (string) $flow->transfer_type,
            'review_due_on' => is_string($flow->review_due_on) ? $flow->review_due_on : '',
            'linked_asset_id' => is_string($flow->linked_asset_id) ? $flow->linked_asset_id : '',
            'linked_risk_id' => is_string($flow->linked_risk_id) ? $flow->linked_risk_id : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapProcessingActivity(object $activity): array
    {
        return [
            'id' => (string) $activity->id,
            'organization_id' => (string) $activity->organization_id,
            'scope_id' => is_string($activity->scope_id) ? $activity->scope_id : '',
            'title' => (string) $activity->title,
            'purpose' => (string) $activity->purpose,
            'lawful_basis' => (string) $activity->lawful_basis,
            'linked_data_flow_ids' => is_string($activity->linked_data_flow_ids) ? $activity->linked_data_flow_ids : '',
            'linked_risk_ids' => is_string($activity->linked_risk_ids) ? $activity->linked_risk_ids : '',
            'linked_policy_id' => is_string($activity->linked_policy_id) ? $activity->linked_policy_id : '',
            'linked_finding_id' => is_string($activity->linked_finding_id) ? $activity->linked_finding_id : '',
            'review_due_on' => is_string($activity->review_due_on) ? $activity->review_due_on : '',
        ];
    }
}
