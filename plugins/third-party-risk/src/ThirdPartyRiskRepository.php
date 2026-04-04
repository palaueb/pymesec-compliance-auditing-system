<?php

namespace PymeSec\Plugins\ThirdPartyRisk;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;
use PymeSec\Core\Security\ContextualReferenceValidator;

class ThirdPartyRiskRepository
{
    public function __construct(
        private readonly ContextualReferenceValidator $references,
        private readonly AuditTrailInterface $audit,
        private readonly QuestionnaireStoreInterface $questionnaires,
        private readonly CollaborationStoreInterface $collaboration,
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
        return $this->questionnaires->allTemplates(
            organizationId: $organizationId,
            scopeId: $scopeId,
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            profileId: $profileId,
        );
    }

    /**
     * @return array<string, string>|null
     */
    public function findQuestionnaireTemplate(string $templateId): ?array
    {
        $template = $this->questionnaires->findTemplate($templateId);

        if ($template === null || $template['owner_component'] !== 'third-party-risk' || $template['subject_type'] !== 'vendor-review') {
            return null;
        }

        return $template;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function questionnaireTemplateItems(string $templateId): array
    {
        return $this->questionnaires->templateItems($templateId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function externalLinksForReview(string $reviewId): array
    {
        return array_map(
            fn (array $link): array => $this->mapCollaborationExternalLinkForReview($link),
            $this->collaboration->externalLinksForSubject('third-party-risk', 'vendor-review', $reviewId),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function externalCollaboratorsForReview(string $reviewId): array
    {
        return array_map(
            fn (array $collaborator): array => $this->mapCollaborationExternalCollaboratorForReview($collaborator),
            $this->collaboration->externalCollaboratorsForSubject('third-party-risk', 'vendor-review', $reviewId),
        );
    }

    /**
     * @return array<string, string>|null
     */
    public function findExternalLink(string $linkId): ?array
    {
        $link = $this->collaboration->findExternalLink($linkId);

        if ($link === null || $link['owner_component'] !== 'third-party-risk' || $link['subject_type'] !== 'vendor-review') {
            return null;
        }

        return $this->mapCollaborationExternalLinkForReview($link);
    }

    /**
     * @return array<string, string>|null
     */
    public function resolveExternalLinkByToken(string $token): ?array
    {
        $link = $this->collaboration->resolveExternalLinkByToken('third-party-risk', 'vendor-review', $token);

        if ($link === null) {
            return null;
        }

        return $this->mapCollaborationExternalLinkForReview($link);
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

        if ($review === null || $this->findQuestionnaireTemplate($templateId) === null) {
            abort(404);
        }

        $created = $this->questionnaires->applyTemplateToSubject(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            templateId: $templateId,
        );

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

        [$link, $token] = $this->collaboration->issueExternalLink(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );
        $link = $this->mapCollaborationExternalLinkForReview($link);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.external-link.issued',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['issued_by_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'vendor_review_external_link',
            targetId: $link['id'],
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

        $updated = $this->collaboration->revokeExternalLink(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            linkId: $linkId,
            revokedByPrincipalId: $principalId,
        );

        if ($updated === null) {
            return null;
        }

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

        return $this->mapCollaborationExternalLinkForReview($updated);
    }

    /**
     * @return array<string, string>|null
     */
    public function updateExternalCollaboratorLifecycleForReview(
        string $reviewId,
        string $collaboratorId,
        string $lifecycleState,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        $updated = $this->collaboration->updateExternalCollaboratorLifecycle(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            collaboratorId: $collaboratorId,
            lifecycleState: $lifecycleState,
            updatedByPrincipalId: $principalId,
        );

        if ($updated === null) {
            return null;
        }

        $eventType = match ($updated['lifecycle_state']) {
            'blocked' => 'plugin.third-party-risk.external-collaborator.blocked',
            'active' => 'plugin.third-party-risk.external-collaborator.activated',
            default => 'plugin.third-party-risk.external-collaborator.updated',
        };

        $this->audit->record(new AuditRecordData(
            eventType: $eventType,
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'external_collaborator',
            targetId: $updated['id'],
            summary: [
                'review_id' => $reviewId,
                'contact_email' => $updated['contact_email'],
                'lifecycle_state' => $updated['lifecycle_state'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $this->mapCollaborationExternalCollaboratorForReview($updated);
    }

    public function touchExternalLinkAccess(string $linkId): void
    {
        $link = $this->findExternalLink($linkId);

        if ($link === null) {
            return;
        }

        $this->collaboration->touchExternalLinkAccess($linkId);

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

        $updated = $this->collaboration->recordExternalLinkDelivery($linkId, $status, $error);

        return $updated !== null ? $this->mapCollaborationExternalLinkForReview($updated) : null;
    }

    public function submitExternalQuestionnaireAnswer(string $reviewId, string $itemId, string $answerText): ?array
    {
        $review = $this->findReview($reviewId);
        $item = $this->findQuestionnaireItem($itemId);

        if ($review === null || $item === null || $item['review_id'] !== $reviewId) {
            return null;
        }

        return $this->questionnaires->submitSubjectAnswer('third-party-risk', 'vendor-review', $reviewId, $itemId, $answerText);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function brokeredRequestsForReview(string $reviewId): array
    {
        return $this->questionnaires->brokeredRequestsForSubject(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function issueBrokeredRequestForReview(string $reviewId, array $data): array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            abort(404);
        }

        $request = $this->questionnaires->issueBrokeredRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.brokered-request.issued',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['issued_by_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'questionnaire_brokered_request',
            targetId: $request['id'],
            summary: [
                'review_id' => $reviewId,
                'contact_name' => $request['contact_name'],
                'contact_email' => $request['contact_email'],
                'collection_channel' => $request['collection_channel'],
                'collection_status' => $request['collection_status'],
                'broker_principal_id' => $request['broker_principal_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateBrokeredRequestForReview(
        string $reviewId,
        string $requestId,
        array $data,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);
        $current = $this->questionnaires->findBrokeredRequest($requestId);

        if ($review === null || $current === null || $current['subject_id'] !== $reviewId || $current['owner_component'] !== 'third-party-risk' || $current['subject_type'] !== 'vendor-review') {
            return null;
        }

        $updated = $this->questionnaires->updateBrokeredRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            requestId: $requestId,
            data: $data,
        );

        if ($updated === null) {
            return null;
        }

        $eventType = match ($updated['collection_status']) {
            'in-progress' => 'plugin.third-party-risk.brokered-request.started',
            'submitted' => 'plugin.third-party-risk.brokered-request.submitted',
            'completed' => 'plugin.third-party-risk.brokered-request.completed',
            'cancelled' => 'plugin.third-party-risk.brokered-request.cancelled',
            default => 'plugin.third-party-risk.brokered-request.updated',
        };

        $this->audit->record(new AuditRecordData(
            eventType: $eventType,
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'questionnaire_brokered_request',
            targetId: $updated['id'],
            summary: [
                'review_id' => $reviewId,
                'contact_name' => $updated['contact_name'],
                'contact_email' => $updated['contact_email'],
                'collection_channel' => $updated['collection_channel'],
                'collection_status' => $updated['collection_status'],
                'broker_principal_id' => $updated['broker_principal_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $updated;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function commentsForReview(string $reviewId): array
    {
        return $this->collaboration->commentsForSubject(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function collaborationDraftsForReview(string $reviewId): array
    {
        return $this->collaboration->draftsForSubject(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function createCollaborationDraftForReview(string $reviewId, array $data): ?array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        $draft = $this->collaboration->createDraft(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-draft.saved',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['edited_by_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_draft',
            targetId: $draft['id'],
            summary: [
                'review_id' => $reviewId,
                'draft_type' => $draft['draft_type'],
                'title' => $draft['title'],
                'mentioned_actor_ids' => $draft['mentioned_actor_ids'],
                'assigned_actor_id' => $draft['assigned_actor_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateCollaborationDraftForReview(
        string $reviewId,
        string $draftId,
        array $data,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);
        $current = $this->collaboration->findDraft($draftId);

        if ($review === null || $current === null || $current['owner_component'] !== 'third-party-risk' || $current['subject_type'] !== 'vendor-review' || $current['subject_id'] !== $reviewId) {
            return null;
        }

        $updated = $this->collaboration->updateDraft(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            draftId: $draftId,
            data: $data,
        );

        if ($updated === null) {
            return null;
        }

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-draft.updated',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_draft',
            targetId: $updated['id'],
            summary: [
                'review_id' => $reviewId,
                'draft_type' => $updated['draft_type'],
                'title' => $updated['title'],
                'mentioned_actor_ids' => $updated['mentioned_actor_ids'],
                'assigned_actor_id' => $updated['assigned_actor_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $updated;
    }

    public function promoteCollaborationDraftToComment(
        string $reviewId,
        string $draftId,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);
        $draft = $this->collaboration->findDraft($draftId);

        if ($review === null || $draft === null || $draft['owner_component'] !== 'third-party-risk' || $draft['subject_type'] !== 'vendor-review' || $draft['subject_id'] !== $reviewId || $draft['draft_type'] !== 'comment') {
            return null;
        }

        $comment = $this->addCommentToReview($reviewId, [
            'author_principal_id' => $principalId,
            'body' => $draft['body'],
            'mentioned_actor_ids' => $this->csvList($draft['mentioned_actor_ids']),
        ]);

        if ($comment === null) {
            return null;
        }

        $this->collaboration->deleteDraft('third-party-risk', 'vendor-review', $reviewId, $draftId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-draft.promoted-comment',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_draft',
            targetId: $draftId,
            summary: [
                'review_id' => $reviewId,
                'draft_type' => 'comment',
                'promoted_comment_id' => $comment['id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $comment;
    }

    public function promoteCollaborationDraftToRequest(
        string $reviewId,
        string $draftId,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);
        $draft = $this->collaboration->findDraft($draftId);

        if ($review === null || $draft === null || $draft['owner_component'] !== 'third-party-risk' || $draft['subject_type'] !== 'vendor-review' || $draft['subject_id'] !== $reviewId || $draft['draft_type'] !== 'request') {
            return null;
        }

        $request = $this->createCollaborationRequestForReview($reviewId, [
            'title' => $draft['title'],
            'details' => $draft['details'],
            'status' => 'open',
            'priority' => $draft['priority'],
            'handoff_state' => $draft['handoff_state'],
            'mentioned_actor_ids' => $this->csvList($draft['mentioned_actor_ids']),
            'assigned_actor_id' => $draft['assigned_actor_id'] !== '' ? $draft['assigned_actor_id'] : null,
            'due_on' => $draft['due_on'] !== '' ? $draft['due_on'] : null,
            'requested_by_principal_id' => $principalId,
        ]);

        if ($request === null) {
            return null;
        }

        $this->collaboration->deleteDraft('third-party-risk', 'vendor-review', $reviewId, $draftId);

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-draft.promoted-request',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_draft',
            targetId: $draftId,
            summary: [
                'review_id' => $reviewId,
                'draft_type' => 'request',
                'promoted_request_id' => $request['id'],
                'title' => $request['title'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function addCommentToReview(string $reviewId, array $data): ?array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        $comment = $this->collaboration->addComment(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-comment.added',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['author_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_comment',
            targetId: $comment['id'],
            summary: [
                'review_id' => $reviewId,
                'body_excerpt' => Str::limit($comment['body'], 140),
                'mentioned_actor_ids' => $comment['mentioned_actor_ids'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $comment;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function collaborationRequestsForReview(string $reviewId): array
    {
        return $this->collaboration->requestsForSubject(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function createCollaborationRequestForReview(string $reviewId, array $data): ?array
    {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        $request = $this->collaboration->createRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.collaboration-request.created',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: ($data['requested_by_principal_id'] ?? null) ?: null,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_request',
            targetId: $request['id'],
            summary: [
                'review_id' => $reviewId,
                'title' => $request['title'],
                'status' => $request['status'],
                'priority' => $request['priority'],
                'handoff_state' => $request['handoff_state'],
                'mentioned_actor_ids' => $request['mentioned_actor_ids'],
                'assigned_actor_id' => $request['assigned_actor_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateCollaborationRequestForReview(
        string $reviewId,
        string $requestId,
        array $data,
        ?string $principalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);
        $current = $this->collaboration->findRequest($requestId);

        if ($review === null || $current === null || $current['owner_component'] !== 'third-party-risk' || $current['subject_type'] !== 'vendor-review' || $current['subject_id'] !== $reviewId) {
            return null;
        }

        $updated = $this->collaboration->updateRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            requestId: $requestId,
            data: $data,
        );

        if ($updated === null) {
            return null;
        }

        $eventType = match ($updated['status']) {
            'in-progress' => 'plugin.third-party-risk.collaboration-request.started',
            'waiting' => 'plugin.third-party-risk.collaboration-request.waiting',
            'done' => 'plugin.third-party-risk.collaboration-request.completed',
            'cancelled' => 'plugin.third-party-risk.collaboration-request.cancelled',
            default => 'plugin.third-party-risk.collaboration-request.updated',
        };

        $this->audit->record(new AuditRecordData(
            eventType: $eventType,
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            targetType: 'collaboration_request',
            targetId: $updated['id'],
            summary: [
                'review_id' => $reviewId,
                'title' => $updated['title'],
                'status' => $updated['status'],
                'priority' => $updated['priority'],
                'handoff_state' => $updated['handoff_state'],
                'mentioned_actor_ids' => $updated['mentioned_actor_ids'],
                'assigned_actor_id' => $updated['assigned_actor_id'],
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $updated;
    }

    /**
     * @return array<int, string>
     */
    private function csvList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
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
        return array_map(function (array $item) use ($reviewId): array {
            return [
                ...$item,
                'review_id' => $reviewId,
            ];
        }, $this->questionnaires->itemsForSubject('third-party-risk', 'vendor-review', $reviewId));
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

        /** @var array<string, string> $item */
        $item = $this->questionnaires->addSubjectItem(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            organizationId: $review['organization_id'],
            scopeId: $review['scope_id'] !== '' ? $review['scope_id'] : null,
            data: $data,
        );

        return [
            ...$item,
            'review_id' => $reviewId,
        ];
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

        $item = $this->questionnaires->updateSubjectItem(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            itemId: $itemId,
            data: $data,
        );

        return $item !== null
            ? [...$item, 'review_id' => $reviewId]
            : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function reviewQuestionnaireItem(
        string $reviewId,
        string $itemId,
        string $responseStatus,
        ?string $reviewNotes = null,
        ?string $reviewedByPrincipalId = null,
    ): ?array {
        $review = $this->findReview($reviewId);

        if ($review === null) {
            return null;
        }

        $item = $this->questionnaires->reviewSubjectItem(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            itemId: $itemId,
            responseStatus: $responseStatus,
            reviewNotes: $reviewNotes,
            reviewedByPrincipalId: $reviewedByPrincipalId,
        );

        return $item !== null
            ? [...$item, 'review_id' => $reviewId]
            : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function findQuestionnaireItem(string $itemId): ?array
    {
        $item = $this->questionnaires->findSubjectItem($itemId);

        if ($item === null || $item['owner_component'] !== 'third-party-risk' || $item['subject_type'] !== 'vendor-review') {
            return null;
        }

        return [
            ...$item,
            'review_id' => $item['subject_id'],
        ];
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
            'created_at' => $review->created_at !== null ? (string) $review->created_at : '',
            'updated_at' => $review->updated_at !== null ? (string) $review->updated_at : '',
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
            'section_title' => is_string($item->section_title ?? null) ? $item->section_title : '',
            'prompt' => (string) $item->prompt,
            'response_type' => (string) $item->response_type,
            'response_status' => (string) $item->response_status,
            'answer_text' => is_string($item->answer_text) ? $item->answer_text : '',
            'follow_up_notes' => is_string($item->follow_up_notes) ? $item->follow_up_notes : '',
            'created_at' => $item->created_at !== null ? (string) $item->created_at : '',
            'updated_at' => $item->updated_at !== null ? (string) $item->updated_at : '',
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
            'section_title' => is_string($item->section_title ?? null) ? $item->section_title : '',
            'prompt' => (string) $item->prompt,
            'response_type' => (string) $item->response_type,
            'guidance_text' => is_string($item->guidance_text) ? $item->guidance_text : '',
        ];
    }

    /**
     * @param  array<string, string>  $link
     * @return array<string, string>
     */
    private function mapCollaborationExternalLinkForReview(array $link): array
    {
        return [
            ...$link,
            'review_id' => $link['subject_id'],
        ];
    }

    /**
     * @param  array<string, string>  $collaborator
     * @return array<string, string>
     */
    private function mapCollaborationExternalCollaboratorForReview(array $collaborator): array
    {
        return [
            ...$collaborator,
            'review_id' => $collaborator['subject_id'],
        ];
    }
}
