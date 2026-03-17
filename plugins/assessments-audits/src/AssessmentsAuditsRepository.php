<?php

namespace PymeSec\Plugins\AssessmentsAudits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AssessmentsAuditsRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        return DB::table('assessment_campaigns')
            ->where('organization_id', $organizationId)
            ->when(is_string($scopeId) && $scopeId !== '', function ($query) use ($scopeId): void {
                $query->where(function ($scoped) use ($scopeId): void {
                    $scoped->whereNull('scope_id')
                        ->orWhere('scope_id', $scopeId);
                });
            })
            ->orderByDesc('starts_on')
            ->orderBy('title')
            ->get()
            ->map(fn ($campaign): array => $this->mapCampaign($campaign))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $assessmentId): ?array
    {
        $campaign = DB::table('assessment_campaigns')->where('id', $assessmentId)->first();

        return $campaign !== null ? $this->mapCampaign($campaign) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $id = $this->nextId((string) $data['title']);

        DB::table('assessment_campaigns')->insert([
            'id' => $id,
            'organization_id' => (string) $data['organization_id'],
            'scope_id' => $this->nullableString($data['scope_id'] ?? null),
            'framework_id' => $this->nullableString($data['framework_id'] ?? null),
            'title' => (string) $data['title'],
            'summary' => (string) $data['summary'],
            'starts_on' => (string) $data['starts_on'],
            'ends_on' => (string) $data['ends_on'],
            'status' => (string) ($data['status'] ?? 'draft'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncControls($id, $this->normalizeStringArray($data['control_ids'] ?? []));

        return $this->find($id) ?? [
            'id' => $id,
            ...$data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function update(string $assessmentId, array $data): ?array
    {
        $existing = $this->find($assessmentId);

        if ($existing === null) {
            return null;
        }

        DB::table('assessment_campaigns')
            ->where('id', $assessmentId)
            ->update([
                'scope_id' => $this->nullableString($data['scope_id'] ?? null),
                'framework_id' => $this->nullableString($data['framework_id'] ?? null),
                'title' => (string) $data['title'],
                'summary' => (string) $data['summary'],
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'status' => (string) ($data['status'] ?? $existing['status']),
                'updated_at' => now(),
            ]);

        $this->syncControls($assessmentId, $this->normalizeStringArray($data['control_ids'] ?? []));

        return $this->find($assessmentId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function controlOptions(string $organizationId, ?string $scopeId = null): array
    {
        return DB::table('controls')
            ->where('organization_id', $organizationId)
            ->when(is_string($scopeId) && $scopeId !== '', function ($query) use ($scopeId): void {
                $query->where(function ($scoped) use ($scopeId): void {
                    $scoped->whereNull('scope_id')
                        ->orWhere('scope_id', $scopeId);
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'framework', 'domain'])
            ->map(static fn ($control): array => [
                'id' => (string) $control->id,
                'label' => trim(sprintf('%s · %s · %s', (string) $control->name, (string) $control->framework, (string) $control->domain), ' ·'),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function frameworkOptions(string $organizationId): array
    {
        if (! Schema::hasTable('frameworks')) {
            return [];
        }

        return DB::table('frameworks')
            ->where(function ($q) use ($organizationId): void {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $organizationId);
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(static fn ($framework): array => [
                'id' => (string) $framework->id,
                'label' => sprintf('%s · %s', (string) $framework->code, (string) $framework->name),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reviews(string $assessmentId): array
    {
        if (! Schema::hasTable('assessment_control_reviews')) {
            return [];
        }

        return DB::table('assessment_campaign_controls as links')
            ->join('controls', 'controls.id', '=', 'links.control_id')
            ->leftJoin('assessment_control_reviews as reviews', function ($join): void {
                $join->on('reviews.assessment_id', '=', 'links.assessment_id')
                    ->on('reviews.control_id', '=', 'links.control_id');
            })
            ->where('links.assessment_id', $assessmentId)
            ->orderBy('links.position')
            ->get([
                'links.assessment_id',
                'links.control_id',
                'links.position',
                'controls.name as control_name',
                'controls.framework as control_framework',
                'controls.domain as control_domain',
                'controls.evidence as control_evidence',
                'reviews.id as review_id',
                'reviews.result',
                'reviews.test_notes',
                'reviews.conclusion',
                'reviews.reviewed_on',
                'reviews.reviewer_principal_id',
                'reviews.linked_finding_id',
            ])
            ->map(fn ($row): array => $this->mapReview($row))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function review(string $assessmentId, string $controlId): ?array
    {
        $review = collect($this->reviews($assessmentId))
            ->first(fn (array $row): bool => $row['control_id'] === $controlId);

        return is_array($review) ? $review : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function upsertReview(string $assessmentId, string $controlId, array $data, string $principalId): ?array
    {
        $assessment = $this->find($assessmentId);

        if ($assessment === null) {
            return null;
        }

        $existing = $this->review($assessmentId, $controlId);

        if ($existing === null) {
            return null;
        }

        DB::table('assessment_control_reviews')->updateOrInsert(
            [
                'id' => $this->reviewId($assessmentId, $controlId),
            ],
            [
                'assessment_id' => $assessmentId,
                'control_id' => $controlId,
                'organization_id' => $assessment['organization_id'],
                'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                'result' => (string) ($data['result'] ?? $existing['result']),
                'test_notes' => $this->nullableString($data['test_notes'] ?? $existing['test_notes']),
                'conclusion' => $this->nullableString($data['conclusion'] ?? $existing['conclusion']),
                'reviewed_on' => $this->nullableString($data['reviewed_on'] ?? $existing['reviewed_on'] ?? now()->toDateString()),
                'reviewer_principal_id' => $principalId,
                'linked_finding_id' => $this->nullableString($existing['linked_finding_id'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $this->review($assessmentId, $controlId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function linkFinding(string $assessmentId, string $controlId, string $findingId): ?array
    {
        $review = $this->review($assessmentId, $controlId);

        if ($review === null) {
            return null;
        }

        DB::table('assessment_control_reviews')
            ->where('id', $review['id'])
            ->update([
                'linked_finding_id' => $findingId,
                'updated_at' => now(),
            ]);

        return $this->review($assessmentId, $controlId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function report(string $assessmentId): ?array
    {
        $assessment = $this->find($assessmentId);

        if ($assessment === null) {
            return null;
        }

        $reviews = $this->reviews($assessmentId);

        return [
            'assessment' => $assessment,
            'reviews' => $reviews,
            'summary' => $assessment['review_summary'],
        ];
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private function controlsForAssessment(string $assessmentId): array
    {
        return DB::table('assessment_campaign_controls as links')
            ->join('controls', 'controls.id', '=', 'links.control_id')
            ->where('links.assessment_id', $assessmentId)
            ->orderBy('links.position')
            ->get(['controls.id', 'controls.name'])
            ->map(static fn ($control): array => [
                'id' => (string) $control->id,
                'label' => (string) $control->name,
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $controlIds
     */
    private function syncControls(string $assessmentId, array $controlIds): void
    {
        DB::table('assessment_campaign_controls')->where('assessment_id', $assessmentId)->delete();

        foreach (array_values($controlIds) as $index => $controlId) {
            DB::table('assessment_campaign_controls')->insert([
                'id' => sprintf('%s-control-%02d', $assessmentId, $index + 1),
                'assessment_id' => $assessmentId,
                'control_id' => $controlId,
                'position' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->syncReviewRows($assessmentId);
    }

    private function syncReviewRows(string $assessmentId): void
    {
        if (! Schema::hasTable('assessment_control_reviews')) {
            return;
        }

        $assessment = $this->find($assessmentId);

        if ($assessment === null) {
            return;
        }

        $controlIds = DB::table('assessment_campaign_controls')
            ->where('assessment_id', $assessmentId)
            ->orderBy('position')
            ->pluck('control_id')
            ->map(static fn ($controlId): string => (string) $controlId)
            ->all();

        DB::table('assessment_control_reviews')
            ->where('assessment_id', $assessmentId)
            ->whereNotIn('control_id', $controlIds)
            ->delete();

        foreach ($controlIds as $controlId) {
            $reviewId = $this->reviewId($assessmentId, $controlId);

            if (DB::table('assessment_control_reviews')->where('id', $reviewId)->exists()) {
                continue;
            }

            DB::table('assessment_control_reviews')->insert([
                'id' => $reviewId,
                'assessment_id' => $assessmentId,
                'control_id' => $controlId,
                'organization_id' => $assessment['organization_id'],
                'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                'result' => 'not-tested',
                'test_notes' => null,
                'conclusion' => null,
                'reviewed_on' => null,
                'reviewer_principal_id' => null,
                'linked_finding_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  object  $campaign
     * @return array<string, mixed>
     */
    private function mapCampaign(object $campaign): array
    {
        $assessmentId = (string) $campaign->id;

        return [
            'id' => $assessmentId,
            'organization_id' => (string) $campaign->organization_id,
            'scope_id' => (string) ($campaign->scope_id ?? ''),
            'framework_id' => (string) ($campaign->framework_id ?? ''),
            'title' => (string) $campaign->title,
            'summary' => (string) $campaign->summary,
            'starts_on' => (string) $campaign->starts_on,
            'ends_on' => (string) $campaign->ends_on,
            'status' => (string) $campaign->status,
            'controls' => $this->controlsForAssessment($assessmentId),
            'review_summary' => $this->reviewSummary($assessmentId),
        ];
    }

    /**
     * @param  object  $review
     * @return array<string, mixed>
     */
    private function mapReview(object $review): array
    {
        $reviewId = is_string($review->review_id ?? null) && $review->review_id !== ''
            ? (string) $review->review_id
            : $this->reviewId((string) $review->assessment_id, (string) $review->control_id);
        $linkedFindingId = is_string($review->linked_finding_id ?? null) ? $review->linked_finding_id : '';

        return [
            'id' => $reviewId,
            'assessment_id' => (string) $review->assessment_id,
            'control_id' => (string) $review->control_id,
            'control_name' => (string) $review->control_name,
            'control_framework' => (string) $review->control_framework,
            'control_domain' => (string) $review->control_domain,
            'control_evidence' => (string) $review->control_evidence,
            'position' => (int) $review->position,
            'result' => is_string($review->result ?? null) && $review->result !== '' ? (string) $review->result : 'not-tested',
            'test_notes' => is_string($review->test_notes ?? null) ? $review->test_notes : '',
            'conclusion' => is_string($review->conclusion ?? null) ? $review->conclusion : '',
            'reviewed_on' => is_string($review->reviewed_on ?? null) ? $review->reviewed_on : '',
            'reviewer_principal_id' => is_string($review->reviewer_principal_id ?? null) ? $review->reviewer_principal_id : '',
            'linked_finding_id' => $linkedFindingId,
            'linked_finding' => $this->findingSummary($linkedFindingId),
            'artifacts' => $this->artifactsForReview($reviewId),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function reviewSummary(string $assessmentId): array
    {
        $summary = [
            'not-tested' => 0,
            'pass' => 0,
            'partial' => 0,
            'fail' => 0,
            'not-applicable' => 0,
            'linked_findings' => 0,
            'artifacts' => 0,
        ];

        if (! Schema::hasTable('assessment_control_reviews')) {
            return $summary;
        }

        foreach (DB::table('assessment_control_reviews')->where('assessment_id', $assessmentId)->get(['id', 'result', 'linked_finding_id']) as $review) {
            $result = is_string($review->result ?? null) && isset($summary[$review->result])
                ? (string) $review->result
                : 'not-tested';
            $summary[$result]++;

            if (is_string($review->linked_finding_id ?? null) && $review->linked_finding_id !== '') {
                $summary['linked_findings']++;
            }

            $summary['artifacts'] += DB::table('artifacts')
                ->where('subject_type', 'assessment-review')
                ->where('subject_id', (string) $review->id)
                ->count();
        }

        return $summary;
    }

    /**
     * @return array<string, string>|null
     */
    private function findingSummary(string $findingId): ?array
    {
        if ($findingId === '' || ! Schema::hasTable('findings')) {
            return null;
        }

        $finding = DB::table('findings')->where('id', $findingId)->first();

        if ($finding === null) {
            return null;
        }

        return [
            'id' => (string) $finding->id,
            'title' => (string) $finding->title,
            'severity' => (string) $finding->severity,
            'description' => (string) $finding->description,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function artifactsForReview(string $reviewId): array
    {
        if (! Schema::hasTable('artifacts')) {
            return [];
        }

        return DB::table('artifacts')
            ->where('subject_type', 'assessment-review')
            ->where('subject_id', $reviewId)
            ->orderByDesc('created_at')
            ->get(['id', 'label', 'original_filename', 'artifact_type'])
            ->map(static fn ($artifact): array => [
                'id' => (string) $artifact->id,
                'label' => (string) $artifact->label,
                'original_filename' => (string) $artifact->original_filename,
                'artifact_type' => (string) $artifact->artifact_type,
            ])
            ->all();
    }

    private function reviewId(string $assessmentId, string $controlId): string
    {
        return sprintf('review-%s-%s', $assessmentId, $controlId);
    }

    private function nextId(string $title): string
    {
        $slug = Str::of($title)->slug('-')->limit(40, '');
        $slug = $slug !== '' ? (string) $slug : 'assessment';
        $candidate = 'assessment-'.$slug;
        $suffix = 2;

        while (DB::table('assessment_campaigns')->where('id', $candidate)->exists()) {
            $candidate = sprintf('assessment-%s-%02d', $slug, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => is_string($item) ? trim($item) : '', $value)));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
