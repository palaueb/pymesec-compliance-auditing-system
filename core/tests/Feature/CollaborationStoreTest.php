<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;
use Tests\TestCase;

class CollaborationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_collaboration_store_supports_comments_and_follow_up_requests(): void
    {
        $store = $this->app->make(CollaborationStoreInterface::class);

        [$externalLink, $token] = $store->issueExternalLink(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            data: [
                'contact_name' => 'Nina Walsh',
                'contact_email' => 'nina.walsh@northbridge-payroll.test',
                'can_answer_questionnaire' => true,
                'can_upload_artifacts' => true,
                'issued_by_principal_id' => 'principal-org-a',
                'expires_at' => now()->addDay()->toDateTimeString(),
            ],
        );

        $this->assertNotSame('', $token);
        $this->assertSame('vendor-review-northbridge-payroll-2026', $externalLink['subject_id']);
        $this->assertSame('manual-only', $externalLink['email_delivery_status']);
        $this->assertNotSame('', $externalLink['collaborator_id']);
        $this->assertNotEmpty($store->externalLinksForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026'));

        $collaborators = $store->externalCollaboratorsForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026');
        $this->assertCount(1, $collaborators);
        $this->assertSame('active', $collaborators[0]['lifecycle_state']);
        $this->assertSame('nina.walsh@northbridge-payroll.test', $collaborators[0]['contact_email']);

        $resolved = $store->resolveExternalLinkByToken('third-party-risk', 'vendor-review', $token);
        $this->assertNotNull($resolved);
        $this->assertSame($externalLink['id'], $resolved['id']);

        $blockedCollaborator = $store->updateExternalCollaboratorLifecycle(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            collaboratorId: $collaborators[0]['id'],
            lifecycleState: 'blocked',
            updatedByPrincipalId: 'principal-org-a',
        );
        $this->assertNotNull($blockedCollaborator);
        $this->assertSame('blocked', $blockedCollaborator['lifecycle_state']);

        $resolvedWhenBlocked = $store->resolveExternalLinkByToken('third-party-risk', 'vendor-review', $token);
        $this->assertNull($resolvedWhenBlocked);

        $reactivatedCollaborator = $store->updateExternalCollaboratorLifecycle(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            collaboratorId: $collaborators[0]['id'],
            lifecycleState: 'active',
            updatedByPrincipalId: 'principal-org-a',
        );
        $this->assertNotNull($reactivatedCollaborator);
        $this->assertSame('active', $reactivatedCollaborator['lifecycle_state']);

        $store->touchExternalLinkAccess($externalLink['id']);
        $touched = $store->findExternalLink($externalLink['id']);
        $this->assertNotNull($touched);
        $this->assertNotSame('', $touched['last_accessed_at']);

        $delivery = $store->recordExternalLinkDelivery($externalLink['id'], 'sent');
        $this->assertNotNull($delivery);
        $this->assertSame('sent', $delivery['email_delivery_status']);
        $this->assertNotSame('', $delivery['email_sent_at']);

        $revoked = $store->revokeExternalLink(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            linkId: $externalLink['id'],
            revokedByPrincipalId: 'principal-org-a',
        );
        $this->assertNotNull($revoked);
        $this->assertNotSame('', $revoked['revoked_at']);

        $resolvedAfterRevoke = $store->resolveExternalLinkByToken('third-party-risk', 'vendor-review', $token);
        $this->assertNull($resolvedAfterRevoke);

        $draft = $store->createDraft(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            data: [
                'draft_type' => 'request',
                'title' => 'Prepare approval package',
                'details' => 'Keep the final package ready for the approval handoff.',
                'priority' => 'high',
                'handoff_state' => 'approval',
                'mentioned_actor_ids' => ['actor-ava-mason'],
                'assigned_actor_id' => 'actor-compliance-office',
                'edited_by_principal_id' => 'principal-org-a',
            ],
        );

        $this->assertSame('request', $draft['draft_type']);
        $this->assertSame('Prepare approval package', $draft['title']);
        $this->assertSame('high', $draft['priority']);
        $this->assertSame('approval', $draft['handoff_state']);
        $this->assertSame('actor-ava-mason', $draft['mentioned_actor_ids']);
        $this->assertSame('actor-compliance-office', $draft['assigned_actor_id']);
        $this->assertSame('principal-org-a', $draft['edited_by_principal_id']);

        $updatedDraft = $store->updateDraft(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            draftId: $draft['id'],
            data: [
                'details' => 'Keep the final signed package ready for the approval handoff.',
                'mentioned_actor_ids' => ['actor-compliance-office'],
                'edited_by_principal_id' => 'principal-org-a',
            ],
        );

        $this->assertNotNull($updatedDraft);
        $this->assertSame('Keep the final signed package ready for the approval handoff.', $updatedDraft['details']);
        $this->assertSame('actor-compliance-office', $updatedDraft['mentioned_actor_ids']);
        $this->assertNotEmpty($store->draftsForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026'));

        $comment = $store->addComment(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            data: [
                'author_principal_id' => 'principal-org-a',
                'body' => 'Need one more signed reviewer package before approval.',
                'mentioned_actor_ids' => ['actor-compliance-office'],
            ],
        );

        $this->assertSame('principal-org-a', $comment['author_principal_id']);
        $this->assertSame('Need one more signed reviewer package before approval.', $comment['body']);
        $this->assertSame('actor-compliance-office', $comment['mentioned_actor_ids']);
        $this->assertNotEmpty($store->commentsForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026'));

        $request = $store->createRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            data: [
                'title' => 'Collect reviewer package',
                'details' => 'Follow up with payroll security and attach the signed package.',
                'status' => 'open',
                'priority' => 'high',
                'handoff_state' => 'review',
                'mentioned_actor_ids' => ['actor-compliance-office'],
                'assigned_actor_id' => 'actor-ava-mason',
                'requested_by_principal_id' => 'principal-org-a',
            ],
        );

        $this->assertSame('Collect reviewer package', $request['title']);
        $this->assertSame('open', $request['status']);
        $this->assertSame('high', $request['priority']);
        $this->assertSame('review', $request['handoff_state']);
        $this->assertSame('actor-compliance-office', $request['mentioned_actor_ids']);
        $this->assertSame('actor-ava-mason', $request['assigned_actor_id']);

        $updated = $store->updateRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            requestId: $request['id'],
            data: [
                'title' => 'Collect signed reviewer package',
                'status' => 'done',
                'priority' => 'urgent',
                'handoff_state' => 'approval',
                'mentioned_actor_ids' => ['actor-ava-mason'],
                'assigned_actor_id' => 'actor-compliance-office',
            ],
        );

        $this->assertNotNull($updated);
        $this->assertSame('Collect signed reviewer package', $updated['title']);
        $this->assertSame('done', $updated['status']);
        $this->assertSame('urgent', $updated['priority']);
        $this->assertSame('approval', $updated['handoff_state']);
        $this->assertSame('actor-ava-mason', $updated['mentioned_actor_ids']);
        $this->assertSame('actor-compliance-office', $updated['assigned_actor_id']);
        $this->assertNotSame('', $updated['completed_at']);
        $this->assertNotEmpty($store->requestsForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026'));

        $store->deleteDraft('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026', $draft['id']);
        $this->assertNull($store->findDraft($draft['id']));
    }
}
