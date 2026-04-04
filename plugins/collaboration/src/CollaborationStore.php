<?php

namespace PymeSec\Plugins\Collaboration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;

class CollaborationStore implements CollaborationStoreInterface
{
    public function externalCollaboratorsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return DB::table('collaboration_external_collaborators')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByRaw("case when lifecycle_state = 'active' then 0 else 1 end")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($collaborator): array => $this->mapExternalCollaborator($collaborator))
            ->all();
    }

    public function upsertExternalCollaborator(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $email = $this->normalizeExternalCollaboratorEmail((string) ($data['contact_email'] ?? ''));
        $contactName = ($data['contact_name'] ?? null) ?: null;

        $existing = DB::table('collaboration_external_collaborators')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('contact_email', $email)
            ->first();

        if ($existing !== null) {
            DB::table('collaboration_external_collaborators')
                ->where('id', $existing->id)
                ->update([
                    'contact_name' => $contactName !== null ? $contactName : (is_string($existing->contact_name) ? $existing->contact_name : null),
                    'organization_id' => $organizationId,
                    'scope_id' => $scopeId,
                    'updated_at' => now(),
                ]);

            /** @var array<string, string> $collaborator */
            $collaborator = $this->findExternalCollaborator((string) $existing->id);

            return $collaborator;
        }

        $id = 'external-collaborator-'.Str::lower(Str::ulid());

        DB::table('collaboration_external_collaborators')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'contact_name' => $contactName,
            'contact_email' => $email,
            'lifecycle_state' => $this->normalizeExternalCollaboratorLifecycleState((string) ($data['lifecycle_state'] ?? 'active')),
            'blocked_at' => null,
            'blocked_by_principal_id' => null,
            'last_link_issued_at' => null,
            'last_link_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $collaborator */
        $collaborator = $this->findExternalCollaborator($id);

        return $collaborator;
    }

    public function findExternalCollaborator(string $collaboratorId): ?array
    {
        $collaborator = DB::table('collaboration_external_collaborators')
            ->where('id', $collaboratorId)
            ->first();

        return $collaborator !== null ? $this->mapExternalCollaborator($collaborator) : null;
    }

    public function updateExternalCollaboratorLifecycle(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $collaboratorId,
        string $lifecycleState,
        ?string $updatedByPrincipalId = null,
    ): ?array {
        $current = $this->findExternalCollaborator($collaboratorId);

        if ($current === null || $current['owner_component'] !== $ownerComponent || $current['subject_type'] !== $subjectType || $current['subject_id'] !== $subjectId) {
            return null;
        }

        $normalizedState = $this->normalizeExternalCollaboratorLifecycleState($lifecycleState);

        DB::table('collaboration_external_collaborators')
            ->where('id', $collaboratorId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'lifecycle_state' => $normalizedState,
                'blocked_at' => $normalizedState === 'blocked' ? now() : null,
                'blocked_by_principal_id' => $normalizedState === 'blocked' ? $updatedByPrincipalId : null,
                'updated_at' => now(),
            ]);

        return $this->findExternalCollaborator($collaboratorId);
    }

    public function externalLinksForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return $this->externalLinksQuery()
            ->where('collaboration_external_links.owner_component', $ownerComponent)
            ->where('collaboration_external_links.subject_type', $subjectType)
            ->where('collaboration_external_links.subject_id', $subjectId)
            ->orderByDesc('collaboration_external_links.created_at')
            ->get()
            ->map(fn ($link): array => $this->mapExternalLink($link))
            ->all();
    }

    public function issueExternalLink(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $token = Str::random(64);
        $id = 'external-link-'.Str::lower(Str::ulid());
        $collaborator = $this->upsertExternalCollaborator(
            ownerComponent: $ownerComponent,
            subjectType: $subjectType,
            subjectId: $subjectId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            data: $data,
        );

        DB::table('collaboration_external_links')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'collaborator_id' => $collaborator['id'],
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'contact_name' => ($data['contact_name'] ?? null) ?: null,
            'contact_email' => $this->normalizeExternalCollaboratorEmail((string) ($data['contact_email'] ?? '')),
            'token_hash' => hash('sha256', $token),
            'can_answer_questionnaire' => (bool) ($data['can_answer_questionnaire'] ?? false),
            'can_upload_artifacts' => (bool) ($data['can_upload_artifacts'] ?? false),
            'issued_by_principal_id' => ($data['issued_by_principal_id'] ?? null) ?: null,
            'email_delivery_status' => 'manual-only',
            'email_delivery_error' => null,
            'email_last_attempted_at' => null,
            'email_sent_at' => null,
            'expires_at' => ($data['expires_at'] ?? null) ?: null,
            'last_accessed_at' => null,
            'revoked_at' => null,
            'revoked_by_principal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('collaboration_external_collaborators')
            ->where('id', $collaborator['id'])
            ->update([
                'last_link_issued_at' => now(),
                'last_link_id' => $id,
                'updated_at' => now(),
            ]);

        /** @var array<string, string> $link */
        $link = $this->findExternalLink($id);

        return [$link, $token];
    }

    public function findExternalLink(string $linkId): ?array
    {
        $link = $this->externalLinksQuery()
            ->where('collaboration_external_links.id', $linkId)
            ->first();

        return $link !== null ? $this->mapExternalLink($link) : null;
    }

    public function resolveExternalLinkByToken(string $ownerComponent, string $subjectType, string $token): ?array
    {
        $link = $this->externalLinksQuery()
            ->where('collaboration_external_links.owner_component', $ownerComponent)
            ->where('collaboration_external_links.subject_type', $subjectType)
            ->where('collaboration_external_links.token_hash', hash('sha256', $token))
            ->first();

        if ($link === null) {
            return null;
        }

        $mapped = $this->mapExternalLink($link);

        return $this->externalLinkIsActive($mapped) ? $mapped : null;
    }

    public function revokeExternalLink(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $linkId,
        ?string $revokedByPrincipalId = null,
    ): ?array {
        $current = $this->findExternalLink($linkId);

        if ($current === null || $current['owner_component'] !== $ownerComponent || $current['subject_type'] !== $subjectType || $current['subject_id'] !== $subjectId) {
            return null;
        }

        DB::table('collaboration_external_links')
            ->where('id', $linkId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'revoked_by_principal_id' => $revokedByPrincipalId,
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findExternalLink($linkId);
    }

    public function touchExternalLinkAccess(string $linkId): void
    {
        DB::table('collaboration_external_links')
            ->where('id', $linkId)
            ->update([
                'last_accessed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function recordExternalLinkDelivery(string $linkId, string $status, ?string $error = null): ?array
    {
        $current = $this->findExternalLink($linkId);

        if ($current === null) {
            return null;
        }

        DB::table('collaboration_external_links')
            ->where('id', $linkId)
            ->update([
                'email_delivery_status' => trim($status) !== '' ? trim($status) : 'manual-only',
                'email_delivery_error' => $error !== null && trim($error) !== '' ? trim($error) : null,
                'email_last_attempted_at' => now(),
                'email_sent_at' => $status === 'sent' ? now() : null,
                'updated_at' => now(),
            ]);

        return $this->findExternalLink($linkId);
    }

    public function draftsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return DB::table('collaboration_drafts')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($draft): array => $this->mapDraft($draft))
            ->all();
    }

    public function createDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $id = 'collaboration-draft-'.Str::lower(Str::ulid());

        DB::table('collaboration_drafts')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'draft_type' => $this->normalizeDraftType((string) ($data['draft_type'] ?? 'comment')),
            'title' => ($data['title'] ?? null) ?: null,
            'body' => ($data['body'] ?? null) ?: null,
            'details' => ($data['details'] ?? null) ?: null,
            'priority' => $this->normalizePriority((string) ($data['priority'] ?? 'normal')),
            'handoff_state' => $this->normalizeHandoffState((string) ($data['handoff_state'] ?? 'review')),
            'mentioned_actor_ids' => $this->normalizeMentionedActorIds($data['mentioned_actor_ids'] ?? null),
            'assigned_actor_id' => ($data['assigned_actor_id'] ?? null) ?: null,
            'due_on' => ($data['due_on'] ?? null) ?: null,
            'edited_by_principal_id' => ($data['edited_by_principal_id'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $draft */
        $draft = $this->findDraft($id);

        return $draft;
    }

    public function findDraft(string $draftId): ?array
    {
        $draft = DB::table('collaboration_drafts')->where('id', $draftId)->first();

        return $draft !== null ? $this->mapDraft($draft) : null;
    }

    public function updateDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $draftId,
        array $data,
    ): ?array {
        $current = $this->findDraft($draftId);

        if ($current === null || $current['owner_component'] !== $ownerComponent || $current['subject_type'] !== $subjectType || $current['subject_id'] !== $subjectId) {
            return null;
        }

        DB::table('collaboration_drafts')
            ->where('id', $draftId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'draft_type' => $this->normalizeDraftType((string) ($data['draft_type'] ?? $current['draft_type'])),
                'title' => array_key_exists('title', $data) ? (($data['title'] ?? null) ?: null) : (($current['title'] ?? '') !== '' ? $current['title'] : null),
                'body' => array_key_exists('body', $data) ? (($data['body'] ?? null) ?: null) : (($current['body'] ?? '') !== '' ? $current['body'] : null),
                'details' => array_key_exists('details', $data) ? (($data['details'] ?? null) ?: null) : (($current['details'] ?? '') !== '' ? $current['details'] : null),
                'priority' => $this->normalizePriority((string) ($data['priority'] ?? $current['priority'])),
                'handoff_state' => $this->normalizeHandoffState((string) ($data['handoff_state'] ?? $current['handoff_state'])),
                'mentioned_actor_ids' => array_key_exists('mentioned_actor_ids', $data)
                    ? $this->normalizeMentionedActorIds($data['mentioned_actor_ids'] ?? null)
                    : (($current['mentioned_actor_ids'] ?? '') !== '' ? $current['mentioned_actor_ids'] : null),
                'assigned_actor_id' => array_key_exists('assigned_actor_id', $data) ? (($data['assigned_actor_id'] ?? null) ?: null) : (($current['assigned_actor_id'] ?? '') !== '' ? $current['assigned_actor_id'] : null),
                'due_on' => array_key_exists('due_on', $data) ? (($data['due_on'] ?? null) ?: null) : (($current['due_on'] ?? '') !== '' ? $current['due_on'] : null),
                'edited_by_principal_id' => array_key_exists('edited_by_principal_id', $data) ? (($data['edited_by_principal_id'] ?? null) ?: null) : (($current['edited_by_principal_id'] ?? '') !== '' ? $current['edited_by_principal_id'] : null),
                'updated_at' => now(),
            ]);

        return $this->findDraft($draftId);
    }

    public function deleteDraft(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $draftId,
    ): void {
        DB::table('collaboration_drafts')
            ->where('id', $draftId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->delete();
    }

    public function commentsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return DB::table('collaboration_comments')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($comment): array => $this->mapComment($comment))
            ->all();
    }

    public function addComment(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $id = 'collaboration-comment-'.Str::lower(Str::ulid());

        DB::table('collaboration_comments')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'author_principal_id' => ($data['author_principal_id'] ?? null) ?: null,
            'body' => trim((string) ($data['body'] ?? '')),
            'mentioned_actor_ids' => $this->normalizeMentionedActorIds($data['mentioned_actor_ids'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findComment($id);
    }

    public function requestsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return DB::table('collaboration_requests')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByRaw("case when status in ('open', 'in-progress', 'waiting') then 0 else 1 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderBy('due_on')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($request): array => $this->mapRequest($request))
            ->all();
    }

    public function createRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $id = 'collaboration-request-'.Str::lower(Str::ulid());
        $status = $this->normalizeStatus((string) ($data['status'] ?? 'open'));

        DB::table('collaboration_requests')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'title' => trim((string) ($data['title'] ?? '')),
            'details' => ($data['details'] ?? null) ?: null,
            'status' => $status,
            'priority' => $this->normalizePriority((string) ($data['priority'] ?? 'normal')),
            'handoff_state' => $this->normalizeHandoffState((string) ($data['handoff_state'] ?? 'review')),
            'mentioned_actor_ids' => $this->normalizeMentionedActorIds($data['mentioned_actor_ids'] ?? null),
            'assigned_actor_id' => ($data['assigned_actor_id'] ?? null) ?: null,
            'requested_by_principal_id' => ($data['requested_by_principal_id'] ?? null) ?: null,
            'due_on' => ($data['due_on'] ?? null) ?: null,
            'completed_at' => $status === 'done' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $request */
        $request = $this->findRequest($id);

        return $request;
    }

    public function findRequest(string $requestId): ?array
    {
        $request = DB::table('collaboration_requests')->where('id', $requestId)->first();

        return $request !== null ? $this->mapRequest($request) : null;
    }

    public function updateRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $requestId,
        array $data,
    ): ?array {
        $current = $this->findRequest($requestId);

        if ($current === null || $current['owner_component'] !== $ownerComponent || $current['subject_type'] !== $subjectType || $current['subject_id'] !== $subjectId) {
            return null;
        }

        $status = $this->normalizeStatus((string) ($data['status'] ?? $current['status']));
        $completedAt = $status === 'done'
            ? ($current['completed_at'] !== '' ? $current['completed_at'] : now())
            : null;
        $cancelledAt = $status === 'cancelled'
            ? ($current['cancelled_at'] !== '' ? $current['cancelled_at'] : now())
            : null;

        DB::table('collaboration_requests')
            ->where('id', $requestId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'title' => trim((string) ($data['title'] ?? $current['title'])),
                'details' => array_key_exists('details', $data) ? (($data['details'] ?? null) ?: null) : (($current['details'] ?? '') !== '' ? $current['details'] : null),
                'status' => $status,
                'priority' => $this->normalizePriority((string) ($data['priority'] ?? $current['priority'])),
                'handoff_state' => $this->normalizeHandoffState((string) ($data['handoff_state'] ?? $current['handoff_state'])),
                'mentioned_actor_ids' => array_key_exists('mentioned_actor_ids', $data)
                    ? $this->normalizeMentionedActorIds($data['mentioned_actor_ids'] ?? null)
                    : (($current['mentioned_actor_ids'] ?? '') !== '' ? $current['mentioned_actor_ids'] : null),
                'assigned_actor_id' => array_key_exists('assigned_actor_id', $data) ? (($data['assigned_actor_id'] ?? null) ?: null) : (($current['assigned_actor_id'] ?? '') !== '' ? $current['assigned_actor_id'] : null),
                'due_on' => array_key_exists('due_on', $data) ? (($data['due_on'] ?? null) ?: null) : (($current['due_on'] ?? '') !== '' ? $current['due_on'] : null),
                'completed_at' => $completedAt,
                'cancelled_at' => $cancelledAt,
                'updated_at' => now(),
            ]);

        return $this->findRequest($requestId);
    }

    /**
     * @return array<string, string>
     */
    private function findComment(string $commentId): array
    {
        $comment = DB::table('collaboration_comments')->where('id', $commentId)->first();

        return $this->mapComment($comment);
    }

    /**
     * @return array<string, string>
     */
    private function mapComment(object $comment): array
    {
        return [
            'id' => (string) $comment->id,
            'owner_component' => (string) $comment->owner_component,
            'subject_type' => (string) $comment->subject_type,
            'subject_id' => (string) $comment->subject_id,
            'organization_id' => (string) $comment->organization_id,
            'scope_id' => is_string($comment->scope_id) ? $comment->scope_id : '',
            'author_principal_id' => is_string($comment->author_principal_id) ? $comment->author_principal_id : '',
            'body' => (string) $comment->body,
            'mentioned_actor_ids' => is_string($comment->mentioned_actor_ids) ? $comment->mentioned_actor_ids : '',
            'created_at' => (string) $comment->created_at,
            'updated_at' => (string) $comment->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapExternalLink(object $link): array
    {
        return [
            'id' => (string) $link->id,
            'owner_component' => (string) $link->owner_component,
            'subject_type' => (string) $link->subject_type,
            'subject_id' => (string) $link->subject_id,
            'collaborator_id' => is_string($link->link_collaborator_id ?? null)
                ? $link->link_collaborator_id
                : (is_string($link->collaborator_record_id ?? null) ? $link->collaborator_record_id : ''),
            'collaborator_lifecycle_state' => is_string($link->collaborator_lifecycle_state ?? null) ? $link->collaborator_lifecycle_state : 'active',
            'collaborator_blocked_at' => $link->collaborator_blocked_at !== null ? (string) $link->collaborator_blocked_at : '',
            'collaborator_blocked_by_principal_id' => is_string($link->collaborator_blocked_by_principal_id ?? null) ? $link->collaborator_blocked_by_principal_id : '',
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
            'updated_at' => $link->updated_at !== null ? (string) $link->updated_at : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapExternalCollaborator(object $collaborator): array
    {
        return [
            'id' => (string) $collaborator->id,
            'owner_component' => (string) $collaborator->owner_component,
            'subject_type' => (string) $collaborator->subject_type,
            'subject_id' => (string) $collaborator->subject_id,
            'organization_id' => (string) $collaborator->organization_id,
            'scope_id' => is_string($collaborator->scope_id) ? $collaborator->scope_id : '',
            'contact_name' => is_string($collaborator->contact_name) ? $collaborator->contact_name : '',
            'contact_email' => (string) $collaborator->contact_email,
            'lifecycle_state' => is_string($collaborator->lifecycle_state ?? null) ? $collaborator->lifecycle_state : 'active',
            'blocked_at' => $collaborator->blocked_at !== null ? (string) $collaborator->blocked_at : '',
            'blocked_by_principal_id' => is_string($collaborator->blocked_by_principal_id ?? null) ? $collaborator->blocked_by_principal_id : '',
            'last_link_issued_at' => $collaborator->last_link_issued_at !== null ? (string) $collaborator->last_link_issued_at : '',
            'last_link_id' => is_string($collaborator->last_link_id ?? null) ? $collaborator->last_link_id : '',
            'created_at' => $collaborator->created_at !== null ? (string) $collaborator->created_at : '',
            'updated_at' => $collaborator->updated_at !== null ? (string) $collaborator->updated_at : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapDraft(object $draft): array
    {
        return [
            'id' => (string) $draft->id,
            'owner_component' => (string) $draft->owner_component,
            'subject_type' => (string) $draft->subject_type,
            'subject_id' => (string) $draft->subject_id,
            'organization_id' => (string) $draft->organization_id,
            'scope_id' => is_string($draft->scope_id) ? $draft->scope_id : '',
            'draft_type' => is_string($draft->draft_type) ? $draft->draft_type : 'comment',
            'title' => is_string($draft->title) ? $draft->title : '',
            'body' => is_string($draft->body) ? $draft->body : '',
            'details' => is_string($draft->details) ? $draft->details : '',
            'priority' => is_string($draft->priority) ? $draft->priority : 'normal',
            'handoff_state' => is_string($draft->handoff_state) ? $draft->handoff_state : 'review',
            'mentioned_actor_ids' => is_string($draft->mentioned_actor_ids) ? $draft->mentioned_actor_ids : '',
            'assigned_actor_id' => is_string($draft->assigned_actor_id) ? $draft->assigned_actor_id : '',
            'due_on' => is_string($draft->due_on) ? $draft->due_on : '',
            'edited_by_principal_id' => is_string($draft->edited_by_principal_id) ? $draft->edited_by_principal_id : '',
            'created_at' => (string) $draft->created_at,
            'updated_at' => (string) $draft->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapRequest(object $request): array
    {
        return [
            'id' => (string) $request->id,
            'owner_component' => (string) $request->owner_component,
            'subject_type' => (string) $request->subject_type,
            'subject_id' => (string) $request->subject_id,
            'organization_id' => (string) $request->organization_id,
            'scope_id' => is_string($request->scope_id) ? $request->scope_id : '',
            'title' => (string) $request->title,
            'details' => is_string($request->details) ? $request->details : '',
            'status' => (string) $request->status,
            'priority' => (string) $request->priority,
            'handoff_state' => is_string($request->handoff_state) ? $request->handoff_state : 'review',
            'mentioned_actor_ids' => is_string($request->mentioned_actor_ids) ? $request->mentioned_actor_ids : '',
            'assigned_actor_id' => is_string($request->assigned_actor_id) ? $request->assigned_actor_id : '',
            'requested_by_principal_id' => is_string($request->requested_by_principal_id) ? $request->requested_by_principal_id : '',
            'due_on' => is_string($request->due_on) ? $request->due_on : '',
            'completed_at' => is_string($request->completed_at) ? $request->completed_at : '',
            'cancelled_at' => is_string($request->cancelled_at) ? $request->cancelled_at : '',
            'created_at' => (string) $request->created_at,
            'updated_at' => (string) $request->updated_at,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['open', 'in-progress', 'waiting', 'done', 'cancelled'], true)
            ? $status
            : 'open';
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true)
            ? $priority
            : 'normal';
    }

    private function normalizeDraftType(string $draftType): string
    {
        return in_array($draftType, ['comment', 'request'], true)
            ? $draftType
            : 'comment';
    }

    /**
     * @param  array<string, string>  $link
     */
    private function externalLinkIsActive(array $link): bool
    {
        if (($link['collaborator_lifecycle_state'] ?? 'active') === 'blocked') {
            return false;
        }

        if (($link['revoked_at'] ?? '') !== '') {
            return false;
        }

        if (($link['expires_at'] ?? '') === '') {
            return true;
        }

        return now()->lte($link['expires_at']);
    }

    private function normalizeExternalCollaboratorLifecycleState(string $lifecycleState): string
    {
        return in_array($lifecycleState, ['active', 'blocked'], true)
            ? $lifecycleState
            : 'active';
    }

    private function normalizeExternalCollaboratorEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function normalizeHandoffState(string $handoffState): string
    {
        return in_array($handoffState, ['review', 'remediation', 'approval', 'closed-loop'], true)
            ? $handoffState
            : 'review';
    }

    /**
     * @param  mixed  $mentionedActorIds
     */
    private function normalizeMentionedActorIds($mentionedActorIds): ?string
    {
        if (! is_array($mentionedActorIds)) {
            return null;
        }

        $values = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            $mentionedActorIds,
        ))));

        return $values !== [] ? implode(',', $values) : null;
    }

    private function externalLinksQuery()
    {
        return DB::table('collaboration_external_links')
            ->leftJoin(
                'collaboration_external_collaborators',
                'collaboration_external_collaborators.id',
                '=',
                'collaboration_external_links.collaborator_id',
            )
            ->select([
                'collaboration_external_links.*',
                'collaboration_external_links.collaborator_id as link_collaborator_id',
                'collaboration_external_collaborators.id as collaborator_record_id',
                'collaboration_external_collaborators.lifecycle_state as collaborator_lifecycle_state',
                'collaboration_external_collaborators.blocked_at as collaborator_blocked_at',
                'collaboration_external_collaborators.blocked_by_principal_id as collaborator_blocked_by_principal_id',
            ]);
    }
}
