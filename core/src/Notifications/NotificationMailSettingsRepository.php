<?php

namespace PymeSec\Core\Notifications;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationMailSettingsRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findForOrganization(string $organizationId): ?array
    {
        if (! Schema::hasTable('notification_mail_settings')) {
            return null;
        }

        $record = DB::table('notification_mail_settings')
            ->where('organization_id', $organizationId)
            ->first();

        return $record !== null ? $this->mapRecord($record) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(string $organizationId, array $data, ?string $updatedByPrincipalId = null): array
    {
        $existing = $this->findForOrganization($organizationId);
        $id = is_string($existing['id'] ?? null) ? (string) $existing['id'] : (string) Str::ulid();
        $encryptedPassword = $this->resolveEncryptedPassword(
            newPassword: is_string($data['smtp_password'] ?? null) ? (string) $data['smtp_password'] : null,
            existingEncryptedPassword: is_string($existing['smtp_password_encrypted'] ?? null) ? (string) $existing['smtp_password_encrypted'] : null,
        );

        $payload = [
            'organization_id' => $organizationId,
            'email_enabled' => (bool) ($data['email_enabled'] ?? false),
            'smtp_host' => $this->nullableString($data['smtp_host'] ?? null),
            'smtp_port' => is_numeric($data['smtp_port'] ?? null) ? (int) $data['smtp_port'] : null,
            'smtp_encryption' => $this->normalizeEncryption($data['smtp_encryption'] ?? null),
            'smtp_username' => $this->nullableString($data['smtp_username'] ?? null),
            'smtp_password_encrypted' => $encryptedPassword,
            'from_address' => $this->nullableString($data['from_address'] ?? null),
            'from_name' => $this->nullableString($data['from_name'] ?? null),
            'reply_to_address' => $this->nullableString($data['reply_to_address'] ?? null),
            'updated_by_principal_id' => $this->nullableString($updatedByPrincipalId),
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('notification_mail_settings')->insert([
                'id' => $id,
                ...$payload,
                'last_tested_at' => null,
                'created_at' => now(),
            ]);
        } else {
            DB::table('notification_mail_settings')
                ->where('id', $id)
                ->update($payload);
        }

        return $this->findForOrganization($organizationId) ?? [
            'id' => $id,
            ...$payload,
            'has_password' => $encryptedPassword !== null,
            'smtp_password' => $this->decryptPassword($encryptedPassword),
        ];
    }

    public function markTested(string $organizationId): void
    {
        if (! Schema::hasTable('notification_mail_settings')) {
            return;
        }

        DB::table('notification_mail_settings')
            ->where('organization_id', $organizationId)
            ->update([
                'last_tested_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deliveryConfigForOrganization(string $organizationId): ?array
    {
        $settings = $this->findForOrganization($organizationId);

        if ($settings === null || ! ($settings['email_enabled'] ?? false)) {
            return null;
        }

        return [
            ...$settings,
            'smtp_password' => $this->decryptPassword(is_string($settings['smtp_password_encrypted'] ?? null) ? $settings['smtp_password_encrypted'] : null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizeEncryption(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = trim($value);

        return $normalized === 'none' ? null : $normalized;
    }

    private function resolveEncryptedPassword(?string $newPassword, ?string $existingEncryptedPassword): ?string
    {
        if ($newPassword === null) {
            return $existingEncryptedPassword;
        }

        if (trim($newPassword) === '') {
            return $existingEncryptedPassword;
        }

        return Crypt::encryptString($newPassword);
    }

    private function decryptPassword(?string $encryptedPassword): ?string
    {
        if (! is_string($encryptedPassword) || $encryptedPassword === '') {
            return null;
        }

        return Crypt::decryptString($encryptedPassword);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecord(object $record): array
    {
        return [
            'id' => (string) $record->id,
            'organization_id' => (string) $record->organization_id,
            'email_enabled' => (bool) $record->email_enabled,
            'smtp_host' => is_string($record->smtp_host) ? $record->smtp_host : '',
            'smtp_port' => is_numeric($record->smtp_port) ? (int) $record->smtp_port : null,
            'smtp_encryption' => is_string($record->smtp_encryption) ? $record->smtp_encryption : '',
            'smtp_username' => is_string($record->smtp_username) ? $record->smtp_username : '',
            'smtp_password_encrypted' => is_string($record->smtp_password_encrypted) ? $record->smtp_password_encrypted : null,
            'has_password' => is_string($record->smtp_password_encrypted) && $record->smtp_password_encrypted !== '',
            'from_address' => is_string($record->from_address) ? $record->from_address : '',
            'from_name' => is_string($record->from_name) ? $record->from_name : '',
            'reply_to_address' => is_string($record->reply_to_address) ? $record->reply_to_address : '',
            'last_tested_at' => is_string($record->last_tested_at) ? $record->last_tested_at : '',
            'updated_by_principal_id' => is_string($record->updated_by_principal_id) ? $record->updated_by_principal_id : '',
        ];
    }
}
