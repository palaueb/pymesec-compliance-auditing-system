<?php

namespace PymeSec\Plugins\ThirdPartyRisk;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Security\ContextualReferenceValidator;

class ThirdPartyRiskRepository
{
    public function __construct(
        private readonly ContextualReferenceValidator $references,
        private readonly AuditTrailInterface $audit,
    ) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function all(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('vendors')
            ->where('organization_id', $organizationId)
            ->orderBy('legal_name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where('scope_id', $scopeId);
        }

        return $query->get()
            ->map(fn ($vendor): array => $this->mapVendor($vendor))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function find(string $vendorId): ?array
    {
        $vendor = DB::table('vendors')->where('id', $vendorId)->first();

        return $vendor !== null ? $this->mapVendor($vendor) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function reviewsForVendor(string $vendorId): array
    {
        return DB::table('vendor_reviews')
            ->where('vendor_id', $vendorId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($review): array => $this->mapReview($review))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findReview(string $reviewId): ?array
    {
        $review = DB::table('vendor_reviews')->where('id', $reviewId)->first();

        return $review !== null ? $this->mapReview($review) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function allReviewProfiles(string $organizationId, ?string $scopeId = null): array
    {
        $query = DB::table('vendor_review_profiles')
            ->where('organization_id', $organizationId)
            ->orderBy('name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        return $query->get()
            ->map(fn ($profile): array => $this->mapReviewProfile($profile))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findReviewProfile(string $profileId): ?array
    {
        $profile = DB::table('vendor_review_profiles')->where('id', $profileId)->first();

        return $profile !== null ? $this->mapReviewProfile($profile) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function allQuestionnaireTemplates(string $organizationId, ?string $scopeId = null, ?string $profileId = null): array
    {
        $query = DB::table('vendor_questionnaire_templates')
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($scopeId !== null && $scopeId !== '') {
            $query->where(function ($nested) use ($scopeId): void {
                $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
            });
        }

        if ($profileId !== null && $profileId !== '') {
            $query->where('profile_id', $profileId);
        }

        return $query->get()
            ->map(fn ($template): array => $this->mapQuestionnaireTemplate($template))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findQuestionnaireTemplate(string $templateId): ?array
    {
        $template = DB::table('vendor_questionnaire_templates')->where('id', $templateId)->first();

        return $template !== null ? $this->mapQuestionnaireTemplate($template) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function questionnaireTemplateItems(string $templateId): array
    {
        return DB::table('vendor_questionnaire_template_items')
            ->where('template_id', $templateId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($item): array => $this->mapQuestionnaireTemplateItem($item))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function externalLinksForReview(string $reviewId): array
    {
        return DB::table('vendor_review_external_links')
            ->where('review_id', $reviewId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($link): array => $this->mapExternalLink($link))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findExternalLink(string $linkId): ?array
    {
        $link = DB::table('vendor_review_external_links')->where('id', $linkId)->first();

        return $link !== null ? $this->mapExternalLink($link) : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function resolveExternalLinkByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $link = DB::table('vendor_review_external_links')
            ->where('token_hash', $tokenHash)
            ->first();

        if ($link === null) {
            return null;
        }

        $mapped = $this->mapExternalLink($link);

        if (! $this->externalLinkIsActive($mapped)) {
            return null;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function createVendorWithReview(array $data): array
    {
        $scopeId = ($data['scope_id'] ?? null) ?: null;
        $this->assertLinkedReferences($data, (string) $data['organization_id'], $scopeId);
        [$profileId, $templateId] = $this->resolveProfileAndTemplateSelection(
            organizationId: (string) $data['organization_id'],
            scopeId: $scopeId,
            profileId: ($data['review_profile_id'] ?? null) ?: null,
            templateId: ($data['questionnaire_template_id'] ?? null) ?: null,
        );

        $vendorId = $this->nextVendorId((string) $data['legal_name']);
        $reviewId = $this->nextReviewId((string) $data['review_title'], $vendorId);

        DB::table('vendors')->insert([
            'id' => $vendorId,
            'organization_id' => $data['organization_id'],
            'scope_id' => $scopeId,
            'legal_name' => $data['legal_name'],
            'vendor_status' => 'prospective',
            'tier' => $data['tier'],
            'service_summary' => $data['service_summary'],
            'website' => ($data['website'] ?? null) ?: null,
            'primary_contact_name' => ($data['primary_contact_name'] ?? null) ?: null,
            'primary_contact_email' => ($data['primary_contact_email'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_reviews')->insert([
            'id' => $reviewId,
            'vendor_id' => $vendorId,
            'organization_id' => $data['organization_id'],
            'scope_id' => $scopeId,
            'review_profile_id' => $profileId,
            'questionnaire_template_id' => $templateId,
            'title' => $data['review_title'],
            'inherent_risk' => $data['inherent_risk'],
            'review_summary' => $data['review_summary'],
            'decision_notes' => ($data['decision_notes'] ?? null) ?: null,
            'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
            'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
            'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
            'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
            'next_review_due_on' => ($data['next_review_due_on'] ?? null) ?: null,
            'created_by_principal_id' => ($data['created_by_principal_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($templateId !== null) {
            $this->applyQuestionnaireTemplateToReview($reviewId, $templateId);
        }

        return [
            $this->find($vendorId),
            $this->findReview($reviewId),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, string>|null, 1: array<string, string>|null}
     */
    public function updateVendorWithReview(string $vendorId, array $data): array
    {
        $scopeId = ($data['scope_id'] ?? null) ?: null;
        $this->assertLinkedReferences($data, (string) $data['organization_id'], $scopeId);
        [$profileId, $templateId] = $this->resolveProfileAndTemplateSelection(
            organizationId: (string) $data['organization_id'],
            scopeId: $scopeId,
            profileId: ($data['review_profile_id'] ?? null) ?: null,
            templateId: ($data['questionnaire_template_id'] ?? null) ?: null,
        );

        DB::table('vendors')
            ->where('id', $vendorId)
            ->update([
                'scope_id' => $scopeId,
                'legal_name' => $data['legal_name'],
                'tier' => $data['tier'],
                'service_summary' => $data['service_summary'],
                'website' => ($data['website'] ?? null) ?: null,
                'primary_contact_name' => ($data['primary_contact_name'] ?? null) ?: null,
                'primary_contact_email' => ($data['primary_contact_email'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        DB::table('vendor_reviews')
            ->where('id', $data['review_id'])
            ->where('vendor_id', $vendorId)
            ->update([
                'scope_id' => $scopeId,
                'review_profile_id' => $profileId,
                'questionnaire_template_id' => $templateId,
                'title' => $data['review_title'],
                'inherent_risk' => $data['inherent_risk'],
                'review_summary' => $data['review_summary'],
                'decision_notes' => ($data['decision_notes'] ?? null) ?: null,
                'linked_asset_id' => ($data['linked_asset_id'] ?? null) ?: null,
                'linked_control_id' => ($data['linked_control_id'] ?? null) ?: null,
                'linked_risk_id' => ($data['linked_risk_id'] ?? null) ?: null,
                'linked_finding_id' => ($data['linked_finding_id'] ?? null) ?: null,
                'next_review_due_on' => ($data['next_review_due_on'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        return [
            $this->find($vendorId),
            $this->findReview((string) $data['review_id']),
        ];
    }

    public function applyQuestionnaireTemplateToReview(string $reviewId, string $templateId): int
    {
        $review = $this->findReview($reviewId);
        $template = $this->findQuestionnaireTemplate($templateId);

        if ($review === null || $template === null) {
            abort(404);
        }

        $currentPosition = (int) DB::table('vendor_review_questionnaire_items')
            ->where('review_id', $reviewId)
            ->max('position');

        $existingTemplateItemIds = DB::table('vendor_review_questionnaire_items')
            ->where('review_id', $reviewId)
            ->whereNotNull('source_template_item_id')
            ->pluck('source_template_item_id')
            ->filter()
            ->all();

        $created = 0;

        foreach ($this->questionnaireTemplateItems($templateId) as $templateItem) {
            if (in_array($templateItem['id'], $existingTemplateItemIds, true)) {
                continue;
            }

            $currentPosition++;
            $created++;

            DB::table('vendor_review_questionnaire_items')->insert([
                'id' => 'vendor-question-'.Str::lower(Str::ulid()),
                'review_id' => $reviewId,
                'organization_id' => $review['organization_id'],
                'scope_id' => $review['scope_id'] !== '' ? $review['scope_id'] : null,
                'source_template_item_id' => $templateItem['id'],
                'position' => $currentPosition,
                'prompt' => $templateItem['prompt'],
                'response_type' => $templateItem['response_type'],
                'response_status' => 'draft',
                'answer_text' => null,
                'follow_up_notes' => $templateItem['guidance_text'] !== '' ? $templateItem['guidance_text'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('vendor_reviews')
            ->where('id', $reviewId)
            ->update([
                'questionnaire_template_id' => $templateId,
                'updated_at' => now(),
            ]);

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, string>, 1: string}
     */
    public function issueExternalLinkForReview(string $reviewId, array $data): array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            abort(404);
        }

        $token = Str::random(64);
        $linkId = 'vendor-external-link-'.Str::lower(Str::ulid());

        DB::table('vendor_review_external_links')->insert([
            'id' => $linkId,
            'review_id' => $reviewId,
            'organization_id' => $review['organization_id'],
            'scope_id' => $review['scope_id'] !== '' ? $review['scope_id'] : null,
            'contact_name' => ($data['contact_name'] ?? null) ?: null,
            'contact_email' => $data['contact_email'],
            'token_hash' => hash('sha256', $token),
            'can_answer_questionnaire' => (bool) ($data['can_answer_questionnaire'] ?? false),
            'can_upload_artifacts' => (bool) ($data['can_upload_artifacts'] ?? false),
            'issued_by_principal_id' => ($data['issued_by_principal_id'] ?? null) ?: null,
            'expires_at' => ($data['expires_at'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = $this->findExternalLink($linkId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.external-link.issued',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['issued_by_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'vendor_review_external_link',
            targetId: $linkId,
            summary: [
                'review_id' => $reviewId,
                'contact_email' => $data['contact_email'],
                'can_answer_questionnaire' => (bool) ($data['can_answer_questionnaire'] ?? false),
                'can_upload_artifacts' => (bool) ($data['can_upload_artifacts'] ?? false),
                'expires_at' => ($data['expires_at'] ?? null) ?: null,
            ],
            executionOrigin: 'third-party-risk',
        ));

        return [$link, $token];
    }

    public function revokeExternalLink(string $reviewId, string $linkId, ?string $principalId = null): ?array
    {
        $review = $this->findReview($reviewId);
        $link = $this->findExternalLink($linkId);

        if ($review === null || $link === null || $link['review_id'] !== $reviewId) {
            return null;
        }

        DB::table('vendor_review_external_links')
            ->where('id', $linkId)
            ->update([
                'revoked_at' => now(),
                'revoked_by_principal_id' => $principalId,
                'updated_at' => now(),
            ]);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.external-link.revoked',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'vendor_review_external_link',
            targetId: $linkId,
            summary: [
                'review_id' => $reviewId,
                'contact_email' => $link['contact_email'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $this->findExternalLink($linkId);
    }

    public function touchExternalLinkAccess(string $linkId): void
    {
        $link = $this->findExternalLink($linkId);

        if ($link === null) {
            return;
        }

        DB::table('vendor_review_external_links')
            ->where('id', $linkId)
            ->update([
                'last_accessed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.external-link.accessed',
            outcome: 'success',
            originComponent: 'third-party-risk',
            organizationId: $link['organization_id'],
            scopeId: $link['scope_id'] !== '' ? $link['scope_id'] : null,
            targetType: 'vendor_review_external_link',
            targetId: $linkId,
            summary: [
                'review_id' => $link['review_id'],
                'contact_email' => $link['contact_email'],
            ],
            executionOrigin: 'third-party-risk-external-portal',
        ));
    }

    /**
     * @return array<string, string>|null
     */
    public function recordExternalLinkDelivery(string $linkId, string $status, ?string $error = null): ?array
    {
        $link = $this->findExternalLink($linkId);

        if ($link === null) {
            return null;
        }

        DB::table('vendor_review_external_links')
            ->where('id', $linkId)
            ->update([
                'email_delivery_status' => $status,
                'email_delivery_error' => $error !== null && trim($error) !== '' ? trim($error) : null,
                'email_last_attempted_at' => now(),
                'email_sent_at' => $status === 'sent' ? now() : null,
                'updated_at' => now(),
            ]);

        return $this->findExternalLink($linkId);
    }

    public function submitExternalQuestionnaireAnswer(string $reviewId, string $itemId, string $answerText): ?array
    {
        $review = $this->findReview($reviewId);
        $item = $this->findQuestionnaireItem($itemId);

        if ($review === null || $item === null || $item['review_id'] !== $reviewId) {
            return null;
        }

        DB::table('vendor_review_questionnaire_items')
            ->where('id', $itemId)
            ->where('review_id', $reviewId)
            ->update([
                'answer_text' => $answerText,
                'response_status' => 'submitted',
                'updated_at' => now(),
            ]);

        return $this->findQuestionnaireItem($itemId);
    }

    public function syncVendorStatusForReview(string $reviewId, string $transitionKey): void
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return;
        }

        $status = match ($transitionKey) {
            'approve', 'approve-with-conditions' => 'active',
            'reject', 'reopen' => 'prospective',
            default => null,
        };

        if ($status === null) {
            return;
        }

        DB::table('vendors')
            ->where('id', $review['vendor_id'])
            ->update([
                'vendor_status' => $status,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function questionnaireItemsForReview(string $reviewId): array
    {
        return DB::table('vendor_review_questionnaire_items')
            ->where('review_id', $reviewId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($item): array => $this->mapQuestionnaireItem($item))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function addQuestionnaireItem(string $reviewId, array $data): array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            abort(404);
        }

        $position = (int) DB::table('vendor_review_questionnaire_items')
            ->where('review_id', $reviewId)
            ->max('position');

        $id = 'vendor-question-'.Str::lower(Str::ulid());

        DB::table('vendor_review_questionnaire_items')->insert([
            'id' => $id,
            'review_id' => $reviewId,
            'organization_id' => $review['organization_id'],
            'scope_id' => $review['scope_id'] !== '' ? $review['scope_id'] : null,
            'source_template_item_id' => null,
            'position' => $position + 1,
            'prompt' => $data['prompt'],
            'response_type' => $data['response_type'],
            'response_status' => ($data['response_status'] ?? 'draft') ?: 'draft',
            'answer_text' => ($data['answer_text'] ?? null) ?: null,
            'follow_up_notes' => ($data['follow_up_notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $item */
        $item = $this->findQuestionnaireItem($id);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateQuestionnaireItem(string $reviewId, string $itemId, array $data): ?array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        DB::table('vendor_review_questionnaire_items')
            ->where('id', $itemId)
            ->where('review_id', $reviewId)
            ->update([
                'prompt' => $data['prompt'],
                'response_type' => $data['response_type'],
                'response_status' => $data['response_status'],
                'answer_text' => ($data['answer_text'] ?? null) ?: null,
                'follow_up_notes' => ($data['follow_up_notes'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        return $this->findQuestionnaireItem($itemId);
    }

    /**
     * @return array<string, string>|null
     */
    public function findQuestionnaireItem(string $itemId): ?array
    {
        $item = DB::table('vendor_review_questionnaire_items')->where('id', $itemId)->first();

        return $item !== null ? $this->mapQuestionnaireItem($item) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLinkedReferences(array $data, string $organizationId, ?string $scopeId): void
    {
        $this->references->assertRecord(
            recordId: ($data['linked_asset_id'] ?? null) ?: null,
            table: 'assets',
            organizationId: $organizationId,
            scopeId: $scopeId,
            field: 'linked_asset_id',
            message: 'The selected linked asset is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_control_id'] ?? null) ?: null,
            table: 'controls',
            organizationId: $organizationId,
            scopeId: $scopeId,
            field: 'linked_control_id',
            message: 'The selected linked control is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_risk_id'] ?? null) ?: null,
            table: 'risks',
            organizationId: $organizationId,
            scopeId: $scopeId,
            field: 'linked_risk_id',
            message: 'The selected linked risk is invalid for this organization or scope.',
        );
        $this->references->assertRecord(
            recordId: ($data['linked_finding_id'] ?? null) ?: null,
            table: 'findings',
            organizationId: $organizationId,
            scopeId: $scopeId,
            field: 'linked_finding_id',
            message: 'The selected linked finding is invalid for this organization or scope.',
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveProfileAndTemplateSelection(
        string $organizationId,
        ?string $scopeId,
        ?string $profileId,
        ?string $templateId,
    ): array {
        $profile = null;
        $template = null;

        if ($profileId !== null && $profileId !== '') {
            $profile = $this->findReviewProfile($profileId);

            abort_if(
                $profile === null
                || $profile['organization_id'] !== $organizationId
                || ! $this->scopeAllowsRecord($scopeId, $profile['scope_id']),
                422,
                'The selected review profile is invalid for this organization or scope.',
            );
        }

        if ($templateId !== null && $templateId !== '') {
            $template = $this->findQuestionnaireTemplate($templateId);

            abort_if(
                $template === null
                || $template['organization_id'] !== $organizationId
                || ! $this->scopeAllowsRecord($scopeId, $template['scope_id']),
                422,
                'The selected questionnaire template is invalid for this organization or scope.',
            );
        }

        if ($profile === null && $template !== null) {
            $profile = $this->findReviewProfile($template['profile_id']);
        }

        if ($template === null && $profile !== null) {
            $template = collect($this->allQuestionnaireTemplates($organizationId, $scopeId, $profile['id']))->first();
        }

        abort_if(
            $profile !== null && $template !== null && $template['profile_id'] !== $profile['id'],
            422,
            'The selected questionnaire template does not belong to the chosen review profile.',
        );

        return [$profile['id'] ?? null, $template['id'] ?? null];
    }

    private function scopeAllowsRecord(?string $requestedScopeId, string $recordScopeId): bool
    {
        if ($requestedScopeId === null || $requestedScopeId === '') {
            return true;
        }

        return $recordScopeId === '' || $recordScopeId === $requestedScopeId;
    }

    /**
     * @param  array<string, string>  $link
     */
    private function externalLinkIsActive(array $link): bool
    {
        if ($link['revoked_at'] !== '') {
            return false;
        }

        if ($link['expires_at'] === '') {
            return true;
        }

        return now()->lte($link['expires_at']);
    }

    private function nextVendorId(string $legalName): string
    {
        $base = 'vendor-'.Str::slug($legalName);
        $candidate = $base !== 'vendor-' ? $base : 'vendor-'.Str::lower(Str::ulid());

        if (! DB::table('vendors')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    private function nextReviewId(string $title, string $vendorId): string
    {
        $base = 'vendor-review-'.Str::slug($title);
        $candidate = $base !== 'vendor-review-' ? $base : 'vendor-review-'.Str::after($vendorId, 'vendor-');

        if (! DB::table('vendor_reviews')->where('id', $candidate)->exists()) {
            return $candidate;
        }

        return $candidate.'-'.Str::lower(Str::random(4));
    }

    /**
     * @return array<string, string>
     */
    private function mapVendor(object $vendor): array
    {
        return [
            'id' => (string) $vendor->id,
            'organization_id' => (string) $vendor->organization_id,
            'scope_id' => is_string($vendor->scope_id) ? $vendor->scope_id : '',
            'legal_name' => (string) $vendor->legal_name,
            'vendor_status' => (string) $vendor->vendor_status,
            'tier' => (string) $vendor->tier,
            'service_summary' => (string) $vendor->service_summary,
            'website' => is_string($vendor->website) ? $vendor->website : '',
            'primary_contact_name' => is_string($vendor->primary_contact_name) ? $vendor->primary_contact_name : '',
            'primary_contact_email' => is_string($vendor->primary_contact_email) ? $vendor->primary_contact_email : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapReview(object $review): array
    {
        return [
            'id' => (string) $review->id,
            'vendor_id' => (string) $review->vendor_id,
            'organization_id' => (string) $review->organization_id,
            'scope_id' => is_string($review->scope_id) ? $review->scope_id : '',
            'review_profile_id' => is_string($review->review_profile_id) ? $review->review_profile_id : '',
            'questionnaire_template_id' => is_string($review->questionnaire_template_id) ? $review->questionnaire_template_id : '',
            'title' => (string) $review->title,
            'inherent_risk' => (string) $review->inherent_risk,
            'review_summary' => (string) $review->review_summary,
            'decision_notes' => is_string($review->decision_notes) ? $review->decision_notes : '',
            'linked_asset_id' => is_string($review->linked_asset_id) ? $review->linked_asset_id : '',
            'linked_control_id' => is_string($review->linked_control_id) ? $review->linked_control_id : '',
            'linked_risk_id' => is_string($review->linked_risk_id) ? $review->linked_risk_id : '',
            'linked_finding_id' => is_string($review->linked_finding_id) ? $review->linked_finding_id : '',
            'next_review_due_on' => is_string($review->next_review_due_on) ? $review->next_review_due_on : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapQuestionnaireItem(object $item): array
    {
        return [
            'id' => (string) $item->id,
            'review_id' => (string) $item->review_id,
            'organization_id' => (string) $item->organization_id,
            'scope_id' => is_string($item->scope_id) ? $item->scope_id : '',
            'source_template_item_id' => is_string($item->source_template_item_id) ? $item->source_template_item_id : '',
            'position' => (string) $item->position,
            'prompt' => (string) $item->prompt,
            'response_type' => (string) $item->response_type,
            'response_status' => (string) $item->response_status,
            'answer_text' => is_string($item->answer_text) ? $item->answer_text : '',
            'follow_up_notes' => is_string($item->follow_up_notes) ? $item->follow_up_notes : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapReviewProfile(object $profile): array
    {
        return [
            'id' => (string) $profile->id,
            'organization_id' => (string) $profile->organization_id,
            'scope_id' => is_string($profile->scope_id) ? $profile->scope_id : '',
            'name' => (string) $profile->name,
            'tier' => (string) $profile->tier,
            'default_inherent_risk' => (string) $profile->default_inherent_risk,
            'review_interval_days' => $profile->review_interval_days !== null ? (string) $profile->review_interval_days : '',
            'summary' => is_string($profile->summary) ? $profile->summary : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapQuestionnaireTemplate(object $template): array
    {
        return [
            'id' => (string) $template->id,
            'profile_id' => (string) $template->profile_id,
            'organization_id' => (string) $template->organization_id,
            'scope_id' => is_string($template->scope_id) ? $template->scope_id : '',
            'name' => (string) $template->name,
            'summary' => is_string($template->summary) ? $template->summary : '',
            'is_default' => (bool) $template->is_default ? '1' : '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapQuestionnaireTemplateItem(object $item): array
    {
        return [
            'id' => (string) $item->id,
            'template_id' => (string) $item->template_id,
            'organization_id' => (string) $item->organization_id,
            'scope_id' => is_string($item->scope_id) ? $item->scope_id : '',
            'position' => (string) $item->position,
            'prompt' => (string) $item->prompt,
            'response_type' => (string) $item->response_type,
            'guidance_text' => is_string($item->guidance_text) ? $item->guidance_text : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapExternalLink(object $link): array
    {
        return [
            'id' => (string) $link->id,
            'review_id' => (string) $link->review_id,
            'organization_id' => (string) $link->organization_id,
            'scope_id' => is_string($link->scope_id) ? $link->scope_id : '',
            'contact_name' => is_string($link->contact_name) ? $link->contact_name : '',
            'contact_email' => (string) $link->contact_email,
            'can_answer_questionnaire' => (bool) $link->can_answer_questionnaire ? '1' : '0',
            'can_upload_artifacts' => (bool) $link->can_upload_artifacts ? '1' : '0',
            'issued_by_principal_id' => is_string($link->issued_by_principal_id) ? $link->issued_by_principal_id : '',
            'revoked_by_principal_id' => is_string($link->revoked_by_principal_id) ? $link->revoked_by_principal_id : '',
            'email_delivery_status' => is_string($link->email_delivery_status ?? null) ? $link->email_delivery_status : 'manual-only',
            'email_delivery_error' => is_string($link->email_delivery_error ?? null) ? $link->email_delivery_error : '',
            'email_last_attempted_at' => $link->email_last_attempted_at !== null ? (string) $link->email_last_attempted_at : '',
            'email_sent_at' => $link->email_sent_at !== null ? (string) $link->email_sent_at : '',
            'expires_at' => $link->expires_at !== null ? (string) $link->expires_at : '',
            'last_accessed_at' => $link->last_accessed_at !== null ? (string) $link->last_accessed_at : '',
            'revoked_at' => $link->revoked_at !== null ? (string) $link->revoked_at : '',
            'created_at' => $link->created_at !== null ? (string) $link->created_at : '',
        ];
    }
}
