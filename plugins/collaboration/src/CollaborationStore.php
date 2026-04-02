<?php

namespace PymeSec\Plugins\Collaboration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Collaboration\Contracts\CollaborationStoreInterface;

class CollaborationStore implements CollaborationStoreInterface
{
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
}
