<?php

namespace PymeSec\Plugins\FindingsRemediation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FindingsRemediationRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allFindings(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('findings')
            ->where('organization_id', $organizationId)
            ->orderBy('severity')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($finding): array => $this->mapFinding($finding))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findFinding(string $findingId): ?array
    {
        $finding = DB::table('findings')->where('id', $findingId)->first();

        return $finding !== null ? $this->mapFinding($finding) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createFinding(array $data): array
    {
        $id = $this->nextId('finding', (string) ($data['title'] ?? 'finding'));

        DB::table('findings')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'severity' => $data['severity'],
            'description' => $data['description'],
            'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
            'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
            'due_on' => ($data['due_on'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $finding */
        $finding = $this->findFinding($id);

        return $finding;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateFinding(string $findingId, array $data): ?array
    {
        $updated = DB::table('findings')
            ->where('id', $findingId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
                'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
                'due_on' => ($data['due_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findFinding($findingId);
        }

        return $this->findFinding($findingId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function actions(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('remediation_actions')
            ->where('organization_id', $organizationId)
            ->orderBy('status')
            ->orderBy('due_on')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($action): array => $this->mapAction($action))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function actionsForFinding(string $findingId): array
    {
        return DB::table('remediation_actions')
            ->where('finding_id', $findingId)
            ->orderBy('status')
            ->orderBy('due_on')
            ->get()
            ->map(fn ($action): array => $this->mapAction($action))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findAction(string $actionId): ?array
    {
        $action = DB::table('remediation_actions')->where('id', $actionId)->first();

        return $action !== null ? $this->mapAction($action) : null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    public function createAction(string $findingId, array $data): array
    {
        $id = $this->nextId('action', (string) ($data['title'] ?? 'action'));

        DB::table('remediation_actions')->insert([
            'id' => $id,
            'finding_id' => $findingId,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'status' => $data['status'],
            'notes' => ($data['notes'] ?? null) ?: null,
            'due_on' => ($data['due_on'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $action */
        $action = $this->findAction($id);

        return $action;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>|null
     */
    public function updateAction(string $actionId, array $data): ?array
    {
        $updated = DB::table('remediation_actions')
            ->where('id', $actionId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'status' => $data['status'],
                'notes' => ($data['notes'] ?? null) ?: null,
                'due_on' => ($data['due_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findAction($actionId);
        }

        return $this->findAction($actionId);
    }

    private function nextId(string $prefix, string $value): string
    {
        $base = $prefix.'-'.Str::slug($value);
        $candidate = $base !== $prefix.'-' ? $base : $prefix.'-'.Str::lower(Str::ulid());

        $table = $prefix === 'finding' ? 'findings' : 'remediation_actions';

        if (! DB::table($table)->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array<string, string>
     */
    private function mapFinding(object $finding): array
    {
        return [
            'id' => (string) $finding->id,
            'organization_id' => (string) $finding->organization_id,
            'scope_id' => is_string($finding->scope_id) ? $finding->scope_id : '',
            'title' => (string) $finding->title,
            'severity' => (string) $finding->severity,
            'description' => (string) $finding->description,
            'linked_control_id' => is_string($finding->linked_control_id) ? $finding->linked_control_id : '',
            'linked_risk_id' => is_string($finding->linked_risk_id) ? $finding->linked_risk_id : '',
            'due_on' => is_string($finding->due_on) ? $finding->due_on : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapAction(object $action): array
    {
        return [
            'id' => (string) $action->id,
            'finding_id' => (string) $action->finding_id,
            'organization_id' => (string) $action->organization_id,
            'scope_id' => is_string($action->scope_id) ? $action->scope_id : '',
            'title' => (string) $action->title,
            'status' => (string) $action->status,
            'notes' => is_string($action->notes) ? $action->notes : '',
            'due_on' => is_string($action->due_on) ? $action->due_on : '',
        ];
    }
}
