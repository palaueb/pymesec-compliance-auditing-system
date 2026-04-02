<?php

namespace PymeSec\Plugins\Questionnaires;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;

class QuestionnaireStore implements QuestionnaireStoreInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allTemplates(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        ?string $profileId = null,
    ): array {
        $query = DB::table('questionnaire_templates')
            ->where('organization_id', $organizationId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
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
            ->map(fn ($template): array => $this->mapTemplate($template))
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    public function findTemplate(string $templateId): ?array
    {
        $template = DB::table('questionnaire_templates')->where('id', $templateId)->first();

        return $template !== null ? $this->mapTemplate($template) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function templateItems(string $templateId): array
    {
        return DB::table('questionnaire_template_items')
            ->where('template_id', $templateId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($item): array => $this->mapTemplateItem($item))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function itemsForSubject(string $ownerComponent, string $subjectType, string $subjectId): array
    {
        return DB::table('questionnaire_subject_items')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($item): array => $this->mapSubjectItem($item))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function addSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $position = (int) DB::table('questionnaire_subject_items')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->max('position');

        $id = 'questionnaire-item-'.Str::lower(Str::ulid());

        DB::table('questionnaire_subject_items')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'source_template_item_id' => null,
            'position' => $position + 1,
            'section_title' => ($data['section_title'] ?? null) ?: null,
            'prompt' => $data['prompt'],
            'response_type' => $data['response_type'],
            'attachment_mode' => $this->normalizeAttachmentMode((string) ($data['attachment_mode'] ?? 'none')),
            'attachment_upload_profile' => $this->nullableAttachmentProfile($data['attachment_upload_profile'] ?? null),
            'promote_attachments_to_evidence' => (bool) ($data['promote_attachments_to_evidence'] ?? false),
            'response_status' => ($data['response_status'] ?? 'draft') ?: 'draft',
            'answer_text' => ($data['answer_text'] ?? null) ?: null,
            'follow_up_notes' => ($data['follow_up_notes'] ?? null) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $item */
        $item = $this->findSubjectItem($id);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        array $data,
    ): ?array {
        DB::table('questionnaire_subject_items')
            ->where('id', $itemId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'section_title' => ($data['section_title'] ?? null) ?: null,
                'prompt' => $data['prompt'],
                'response_type' => $data['response_type'],
                'attachment_mode' => $this->normalizeAttachmentMode((string) ($data['attachment_mode'] ?? 'none')),
                'attachment_upload_profile' => $this->nullableAttachmentProfile($data['attachment_upload_profile'] ?? null),
                'promote_attachments_to_evidence' => (bool) ($data['promote_attachments_to_evidence'] ?? false),
                'response_status' => $data['response_status'],
                'answer_text' => ($data['answer_text'] ?? null) ?: null,
                'follow_up_notes' => ($data['follow_up_notes'] ?? null) ?: null,
                'review_notes' => ($data['review_notes'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        return $this->findSubjectItem($itemId);
    }

    /**
     * @return array<string, string>|null
     */
    public function findSubjectItem(string $itemId): ?array
    {
        $item = DB::table('questionnaire_subject_items')->where('id', $itemId)->first();

        return $item !== null ? $this->mapSubjectItem($item) : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function submitSubjectAnswer(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        string $answerText,
    ): ?array {
        DB::table('questionnaire_subject_items')
            ->where('id', $itemId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'answer_text' => $answerText,
                'response_status' => 'submitted',
                'updated_at' => now(),
            ]);

        return $this->findSubjectItem($itemId);
    }

    public function applyTemplateToSubject(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        string $templateId,
    ): int {
        $template = $this->findTemplate($templateId);

        if ($template === null) {
            abort(404);
        }

        $currentPosition = (int) DB::table('questionnaire_subject_items')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->max('position');

        $existingTemplateItemIds = DB::table('questionnaire_subject_items')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNotNull('source_template_item_id')
            ->pluck('source_template_item_id')
            ->filter()
            ->all();

        $created = 0;

        foreach ($this->templateItems($templateId) as $templateItem) {
            if (in_array($templateItem['id'], $existingTemplateItemIds, true)) {
                continue;
            }

            $currentPosition++;
            $created++;

            DB::table('questionnaire_subject_items')->insert([
                'id' => 'questionnaire-item-'.Str::lower(Str::ulid()),
                'owner_component' => $ownerComponent,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'source_template_item_id' => $templateItem['id'],
                'position' => $currentPosition,
                'section_title' => $templateItem['section_title'] !== '' ? $templateItem['section_title'] : null,
                'prompt' => $templateItem['prompt'],
                'response_type' => $templateItem['response_type'],
                'attachment_mode' => $templateItem['attachment_mode'],
                'attachment_upload_profile' => $templateItem['attachment_upload_profile'] !== '' ? $templateItem['attachment_upload_profile'] : null,
                'promote_attachments_to_evidence' => $templateItem['promote_attachments_to_evidence'] === '1',
                'response_status' => 'draft',
                'answer_text' => null,
                'follow_up_notes' => $templateItem['guidance_text'] !== '' ? $templateItem['guidance_text'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $created;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function answerLibraryEntries(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        string $responseType,
        ?string $prompt = null,
        int $limit = 5,
    ): array {
        $entries = DB::table('questionnaire_answer_library_entries')
            ->where('organization_id', $organizationId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('response_type', $responseType)
            ->when($scopeId !== null && $scopeId !== '', function ($query) use ($scopeId): void {
                $query->where(function ($nested) use ($scopeId): void {
                    $nested->where('scope_id', $scopeId)->orWhereNull('scope_id');
                });
            })
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($entry): array => $this->mapAnswerLibraryEntry($entry))
            ->all();

        if ($prompt !== null && trim($prompt) !== '') {
            $fingerprint = $this->fingerprintPrompt($prompt);

            usort($entries, function (array $left, array $right) use ($fingerprint): int {
                $leftExact = $left['prompt_fingerprint'] === $fingerprint ? 1 : 0;
                $rightExact = $right['prompt_fingerprint'] === $fingerprint ? 1 : 0;

                if ($leftExact !== $rightExact) {
                    return $rightExact <=> $leftExact;
                }

                $leftUsage = (int) ($left['usage_count'] ?? '0');
                $rightUsage = (int) ($right['usage_count'] ?? '0');

                return $rightUsage <=> $leftUsage;
            });
        }

        return array_slice($entries, 0, max(1, $limit));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function saveAnswerLibraryEntry(
        string $organizationId,
        ?string $scopeId,
        string $ownerComponent,
        string $subjectType,
        array $data,
    ): array {
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $responseType = trim((string) ($data['response_type'] ?? ''));
        $answerText = trim((string) ($data['answer_text'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $fingerprint = $this->fingerprintPrompt($prompt);

        $existing = DB::table('questionnaire_answer_library_entries')
            ->where('organization_id', $organizationId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('scope_id', $scopeId)
            ->where('response_type', $responseType)
            ->where('prompt_fingerprint', $fingerprint)
            ->where('answer_text', $answerText)
            ->first();

        if ($existing !== null) {
            DB::table('questionnaire_answer_library_entries')
                ->where('id', $existing->id)
                ->update([
                    'prompt_text' => $prompt,
                    'notes' => $notes !== '' ? $notes : null,
                    'usage_count' => ((int) $existing->usage_count) + 1,
                    'last_used_at' => now(),
                    'updated_at' => now(),
                ]);

            /** @var array<string, string> $entry */
            $entry = $this->findAnswerLibraryEntry((string) $existing->id);

            return $entry;
        }

        $id = 'questionnaire-answer-library-entry-'.Str::lower(Str::ulid());

        DB::table('questionnaire_answer_library_entries')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'response_type' => $responseType,
            'prompt_fingerprint' => $fingerprint,
            'prompt_text' => $prompt,
            'answer_text' => $answerText,
            'notes' => $notes !== '' ? $notes : null,
            'usage_count' => 1,
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var array<string, string> $entry */
        $entry = $this->findAnswerLibraryEntry($id);

        return $entry;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function brokeredRequestsForSubject(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
    ): array {
        return DB::table('questionnaire_brokered_requests')
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($request): array => $this->mapBrokeredRequest($request))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function issueBrokeredRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $organizationId,
        ?string $scopeId,
        array $data,
    ): array {
        $id = 'questionnaire-brokered-request-'.Str::lower(Str::ulid());
        $status = $this->normalizeBrokeredStatus((string) ($data['collection_status'] ?? 'queued'));
        $now = now();

        DB::table('questionnaire_brokered_requests')->insert([
            'id' => $id,
            'owner_component' => $ownerComponent,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'contact_email' => $this->nullableString($data['contact_email'] ?? null),
            'collection_channel' => trim((string) ($data['collection_channel'] ?? 'email')),
            'collection_status' => $status,
            'instructions' => $this->nullableString($data['instructions'] ?? null),
            'broker_notes' => $this->nullableString($data['broker_notes'] ?? null),
            'broker_principal_id' => $this->nullableString($data['broker_principal_id'] ?? null),
            'issued_by_principal_id' => $this->nullableString($data['issued_by_principal_id'] ?? null),
            'requested_at' => $now,
            'started_at' => $status === 'in-progress' ? $now : null,
            'submitted_at' => $status === 'submitted' ? $now : null,
            'completed_at' => $status === 'completed' ? $now : null,
            'cancelled_at' => $status === 'cancelled' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var array<string, string> $request */
        $request = $this->findBrokeredRequest($id);

        return $request;
    }

    /**
     * @return array<string, string>|null
     */
    public function findBrokeredRequest(string $requestId): ?array
    {
        $request = DB::table('questionnaire_brokered_requests')->where('id', $requestId)->first();

        return $request !== null ? $this->mapBrokeredRequest($request) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    public function updateBrokeredRequest(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $requestId,
        array $data,
    ): ?array {
        $current = DB::table('questionnaire_brokered_requests')
            ->where('id', $requestId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();

        if ($current === null) {
            return null;
        }

        $status = $this->normalizeBrokeredStatus((string) ($data['collection_status'] ?? $current->collection_status));
        $now = now();

        DB::table('questionnaire_brokered_requests')
            ->where('id', $requestId)
            ->update([
                'contact_name' => trim((string) ($data['contact_name'] ?? $current->contact_name)),
                'contact_email' => $this->nullableString($data['contact_email'] ?? $current->contact_email),
                'collection_channel' => trim((string) ($data['collection_channel'] ?? $current->collection_channel)),
                'collection_status' => $status,
                'instructions' => array_key_exists('instructions', $data)
                    ? $this->nullableString($data['instructions'])
                    : $this->nullableString($current->instructions),
                'broker_notes' => array_key_exists('broker_notes', $data)
                    ? $this->nullableString($data['broker_notes'])
                    : $this->nullableString($current->broker_notes),
                'broker_principal_id' => array_key_exists('broker_principal_id', $data)
                    ? $this->nullableString($data['broker_principal_id'])
                    : $this->nullableString($current->broker_principal_id),
                'started_at' => $status === 'in-progress'
                    ? ($current->started_at ?? $now)
                    : $current->started_at,
                'submitted_at' => $status === 'submitted'
                    ? ($current->submitted_at ?? $now)
                    : $current->submitted_at,
                'completed_at' => $status === 'completed'
                    ? ($current->completed_at ?? $now)
                    : $current->completed_at,
                'cancelled_at' => $status === 'cancelled'
                    ? ($current->cancelled_at ?? $now)
                    : $current->cancelled_at,
                'updated_at' => $now,
            ]);

        return $this->findBrokeredRequest($requestId);
    }

    /**
     * @return array<string, string>|null
     */
    public function reviewSubjectItem(
        string $ownerComponent,
        string $subjectType,
        string $subjectId,
        string $itemId,
        string $responseStatus,
        ?string $reviewNotes,
        ?string $reviewedByPrincipalId,
    ): ?array {
        DB::table('questionnaire_subject_items')
            ->where('id', $itemId)
            ->where('owner_component', $ownerComponent)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->update([
                'response_status' => $responseStatus,
                'review_notes' => $reviewNotes !== null && trim($reviewNotes) !== '' ? trim($reviewNotes) : null,
                'reviewed_by_principal_id' => $reviewedByPrincipalId !== null && trim($reviewedByPrincipalId) !== '' ? trim($reviewedByPrincipalId) : null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findSubjectItem($itemId);
    }

    /**
     * @return array<string, string>
     */
    private function mapTemplate(object $template): array
    {
        return [
            'id' => (string) $template->id,
            'owner_component' => (string) $template->owner_component,
            'subject_type' => (string) $template->subject_type,
            'profile_id' => is_string($template->profile_id ?? null) ? $template->profile_id : '',
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
    private function mapTemplateItem(object $item): array
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
            'attachment_mode' => is_string($item->attachment_mode ?? null) ? $item->attachment_mode : 'none',
            'attachment_upload_profile' => is_string($item->attachment_upload_profile ?? null) ? $item->attachment_upload_profile : '',
            'promote_attachments_to_evidence' => (bool) ($item->promote_attachments_to_evidence ?? false) ? '1' : '0',
            'guidance_text' => is_string($item->guidance_text) ? $item->guidance_text : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapSubjectItem(object $item): array
    {
        return [
            'id' => (string) $item->id,
            'owner_component' => (string) $item->owner_component,
            'subject_type' => (string) $item->subject_type,
            'subject_id' => (string) $item->subject_id,
            'organization_id' => (string) $item->organization_id,
            'scope_id' => is_string($item->scope_id) ? $item->scope_id : '',
            'source_template_item_id' => is_string($item->source_template_item_id) ? $item->source_template_item_id : '',
            'position' => (string) $item->position,
            'section_title' => is_string($item->section_title ?? null) ? $item->section_title : '',
            'prompt' => (string) $item->prompt,
            'response_type' => (string) $item->response_type,
            'attachment_mode' => is_string($item->attachment_mode ?? null) ? $item->attachment_mode : 'none',
            'attachment_upload_profile' => is_string($item->attachment_upload_profile ?? null) ? $item->attachment_upload_profile : '',
            'promote_attachments_to_evidence' => (bool) ($item->promote_attachments_to_evidence ?? false) ? '1' : '0',
            'response_status' => (string) $item->response_status,
            'answer_text' => is_string($item->answer_text) ? $item->answer_text : '',
            'follow_up_notes' => is_string($item->follow_up_notes) ? $item->follow_up_notes : '',
            'review_notes' => is_string($item->review_notes ?? null) ? $item->review_notes : '',
            'reviewed_by_principal_id' => is_string($item->reviewed_by_principal_id ?? null) ? $item->reviewed_by_principal_id : '',
            'reviewed_at' => $item->reviewed_at !== null ? (string) $item->reviewed_at : '',
            'created_at' => $item->created_at !== null ? (string) $item->created_at : '',
            'updated_at' => $item->updated_at !== null ? (string) $item->updated_at : '',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function findAnswerLibraryEntry(string $entryId): ?array
    {
        $entry = DB::table('questionnaire_answer_library_entries')->where('id', $entryId)->first();

        return $entry !== null ? $this->mapAnswerLibraryEntry($entry) : null;
    }

    /**
     * @return array<string, string>
     */
    private function mapAnswerLibraryEntry(object $entry): array
    {
        return [
            'id' => (string) $entry->id,
            'owner_component' => (string) $entry->owner_component,
            'subject_type' => (string) $entry->subject_type,
            'organization_id' => (string) $entry->organization_id,
            'scope_id' => is_string($entry->scope_id) ? $entry->scope_id : '',
            'response_type' => (string) $entry->response_type,
            'prompt_fingerprint' => (string) $entry->prompt_fingerprint,
            'prompt_text' => (string) $entry->prompt_text,
            'answer_text' => (string) $entry->answer_text,
            'notes' => is_string($entry->notes) ? $entry->notes : '',
            'usage_count' => (string) $entry->usage_count,
            'last_used_at' => $entry->last_used_at !== null ? (string) $entry->last_used_at : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapBrokeredRequest(object $request): array
    {
        return [
            'id' => (string) $request->id,
            'owner_component' => (string) $request->owner_component,
            'subject_type' => (string) $request->subject_type,
            'subject_id' => (string) $request->subject_id,
            'organization_id' => (string) $request->organization_id,
            'scope_id' => is_string($request->scope_id) ? $request->scope_id : '',
            'contact_name' => (string) $request->contact_name,
            'contact_email' => is_string($request->contact_email) ? $request->contact_email : '',
            'collection_channel' => (string) $request->collection_channel,
            'collection_status' => (string) $request->collection_status,
            'instructions' => is_string($request->instructions) ? $request->instructions : '',
            'broker_notes' => is_string($request->broker_notes) ? $request->broker_notes : '',
            'broker_principal_id' => is_string($request->broker_principal_id) ? $request->broker_principal_id : '',
            'issued_by_principal_id' => is_string($request->issued_by_principal_id) ? $request->issued_by_principal_id : '',
            'requested_at' => $request->requested_at !== null ? (string) $request->requested_at : '',
            'started_at' => $request->started_at !== null ? (string) $request->started_at : '',
            'submitted_at' => $request->submitted_at !== null ? (string) $request->submitted_at : '',
            'completed_at' => $request->completed_at !== null ? (string) $request->completed_at : '',
            'cancelled_at' => $request->cancelled_at !== null ? (string) $request->cancelled_at : '',
            'created_at' => $request->created_at !== null ? (string) $request->created_at : '',
            'updated_at' => $request->updated_at !== null ? (string) $request->updated_at : '',
        ];
    }

    private function normalizeBrokeredStatus(string $status): string
    {
        return in_array($status, ['queued', 'in-progress', 'submitted', 'completed', 'cancelled'], true)
            ? $status
            : 'queued';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeAttachmentMode(string $mode): string
    {
        return in_array($mode, ['none', 'supporting-document', 'supporting-evidence'], true)
            ? $mode
            : 'none';
    }

    private function nullableAttachmentProfile(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return in_array($trimmed, ['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'], true)
            ? $trimmed
            : null;
    }

    private function fingerprintPrompt(string $prompt): string
    {
        $normalized = Str::of($prompt)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        return sha1($normalized);
    }
}
