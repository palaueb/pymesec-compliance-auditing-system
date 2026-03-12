<?php

namespace PymeSec\Plugins\PolicyExceptions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyExceptionsRepository
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allPolicies(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('policies')
            ->where('organization_id', $organizationId)
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()->map(fn ($policy): array => $this->mapPolicy($policy))->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findPolicy(string $policyId): ?array
    {
        $policy = DB::table('policies')->where('id', $policyId)->first();

        return $policy !== null ? $this->mapPolicy($policy) : null;
    }

    /**
     * @param array<string, string|null> $data
     * @return array<string, string>
     */
    public function createPolicy(array $data): array
    {
        $id = $this->nextId('policy', (string) ($data['title'] ?? 'policy'));

        DB::table('policies')->insert([
            'id' => $id,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'area' => $data['area'],
            'version_label' => $data['version_label'],
            'statement' => $data['statement'],
            'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
            'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $policy */
        $policy = $this->findPolicy($id);

        return $policy;
    }

    /**
     * @param array<string, string|null> $data
     * @return array<string, string>|null
     */
    public function updatePolicy(string $policyId, array $data): ?array
    {
        $updated = DB::table('policies')
            ->where('id', $policyId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'area' => $data['area'],
                'version_label' => $data['version_label'],
                'statement' => $data['statement'],
                'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
                'review_due_on' => ($data['review_due_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findPolicy($policyId);
        }

        return $this->findPolicy($policyId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function exceptions(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('policy_exceptions')
            ->where('organization_id', $organizationId)
            ->orderBy('expires_on')
            ->orderBy('title');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()->map(fn ($exception): array => $this->mapException($exception))->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function exceptionsForPolicy(string $policyId): array
    {
        return DB::table('policy_exceptions')
            ->where('policy_id', $policyId)
            ->orderBy('expires_on')
            ->orderBy('title')
            ->get()
            ->map(fn ($exception): array => $this->mapException($exception))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findException(string $exceptionId): ?array
    {
        $exception = DB::table('policy_exceptions')->where('id', $exceptionId)->first();

        return $exception !== null ? $this->mapException($exception) : null;
    }

    /**
     * @param array<string, string|null> $data
     * @return array<string, string>
     */
    public function createException(string $policyId, array $data): array
    {
        $id = $this->nextId('exception', (string) ($data['title'] ?? 'exception'));

        DB::table('policy_exceptions')->insert([
            'id' => $id,
            'policy_id' => $policyId,
            'organization_id' => $data['organization_id'],
            'scope_id' => ($data['scope_id'] ?? null) ?: null,
            'title' => $data['title'],
            'rationale' => $data['rationale'],
            'compensating_control' => ($data['compensating_control'] ?? null) ?: null,
            'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
            'expires_on' => ($data['expires_on'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $exception */
        $exception = $this->findException($id);

        return $exception;
    }

    /**
     * @param array<string, string|null> $data
     * @return array<string, string>|null
     */
    public function updateException(string $exceptionId, array $data): ?array
    {
        $updated = DB::table('policy_exceptions')
            ->where('id', $exceptionId)
            ->update([
                'scope_id' => ($data['scope_id'] ?? null) ?: null,
                'title' => $data['title'],
                'rationale' => $data['rationale'],
                'compensating_control' => ($data['compensating_control'] ?? null) ?: null,
                'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
                'expires_on' => ($data['expires_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return $this->findException($exceptionId);
        }

        return $this->findException($exceptionId);
    }

    private function nextId(string $prefix, string $value): string
    {
        $table = $prefix === 'policy' ? 'policies' : 'policy_exceptions';
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
    private function mapPolicy(object $policy): array
    {
        return [
            'id' => (string) $policy->id,
            'organization_id' => (string) $policy->organization_id,
            'scope_id' => is_string($policy->scope_id) ? $policy->scope_id : '',
            'title' => (string) $policy->title,
            'area' => (string) $policy->area,
            'version_label' => (string) $policy->version_label,
            'statement' => (string) $policy->statement,
            'linked_control_id' => is_string($policy->linked_control_id) ? $policy->linked_control_id : '',
            'review_due_on' => is_string($policy->review_due_on) ? $policy->review_due_on : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapException(object $exception): array
    {
        return [
            'id' => (string) $exception->id,
            'policy_id' => (string) $exception->policy_id,
            'organization_id' => (string) $exception->organization_id,
            'scope_id' => is_string($exception->scope_id) ? $exception->scope_id : '',
            'title' => (string) $exception->title,
            'rationale' => (string) $exception->rationale,
            'compensating_control' => is_string($exception->compensating_control) ? $exception->compensating_control : '',
            'linked_finding_id' => is_string($exception->linked_finding_id) ? $exception->linked_finding_id : '',
            'expires_on' => is_string($exception->expires_on) ? $exception->expires_on : '',
        ];
    }
}
