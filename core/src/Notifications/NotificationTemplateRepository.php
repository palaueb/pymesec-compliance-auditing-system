<?php

namespace PymeSec\Core\Notifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationTemplateRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForOrganization(string $organizationId): array
    {
        if (! Schema::hasTable('notification_templates')) {
            return [];
        }

        return DB::table('notification_templates')
            ->where('organization_id', $organizationId)
            ->orderBy('notification_type')
            ->get()
            ->map(fn (object $record): array => $this->mapRecord($record))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForOrganizationAndType(string $organizationId, string $notificationType): ?array
    {
        if (! Schema::hasTable('notification_templates')) {
            return null;
        }

        $record = DB::table('notification_templates')
            ->where('organization_id', $organizationId)
            ->where('notification_type', $notificationType)
            ->first();

        return $record !== null ? $this->mapRecord($record) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(
        string $organizationId,
        string $notificationType,
        array $data,
        ?string $updatedByPrincipalId = null,
    ): array {
        $existing = $this->findForOrganizationAndType($organizationId, $notificationType);
        $id = is_string($existing['id'] ?? null) ? (string) $existing['id'] : (string) Str::ulid();

        $payload = [
            'organization_id' => $organizationId,
            'notification_type' => $notificationType,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'title_template' => $this->nullableString($data['title_template'] ?? null),
            'body_template' => $this->nullableString($data['body_template'] ?? null),
            'updated_by_principal_id' => $this->nullableString($updatedByPrincipalId),
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('notification_templates')->insert([
                'id' => $id,
                ...$payload,
                'created_at' => now(),
            ]);
        } else {
            DB::table('notification_templates')
                ->where('id', $id)
                ->update($payload);
        }

        return $this->findForOrganizationAndType($organizationId, $notificationType) ?? [
            'id' => $id,
            ...$payload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeTemplateForOrganizationAndType(string $organizationId, string $notificationType): ?array
    {
        $template = $this->findForOrganizationAndType($organizationId, $notificationType);

        if ($template === null || ! ($template['is_active'] ?? false)) {
            return null;
        }

        if (($template['title_template'] ?? '') === '' && ($template['body_template'] ?? '') === '') {
            return null;
        }

        return $template;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecord(object $record): array
    {
        return [
            'id' => (string) $record->id,
            'organization_id' => (string) $record->organization_id,
            'notification_type' => (string) $record->notification_type,
            'is_active' => (bool) $record->is_active,
            'title_template' => is_string($record->title_template) ? $record->title_template : '',
            'body_template' => is_string($record->body_template) ? $record->body_template : '',
            'updated_by_principal_id' => is_string($record->updated_by_principal_id) ? $record->updated_by_principal_id : '',
            'updated_at' => is_string($record->updated_at) ? $record->updated_at : '',
        ];
    }
}
