<?php

namespace PymeSec\Plugins\IdentityLdap;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;

class IdentityLdapService
{
    public function __construct(
        private readonly IdentityLdapRepository $repository,
        private readonly IdentityLocalRepository $users,
        private readonly LdapDirectoryGatewayInterface $gateway,
        private readonly AuditTrailInterface $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function syncOrganization(string $organizationId, ?string $triggeredByPrincipalId = null): array
    {
        $connection = $this->repository->connectionForOrganization($organizationId);

        if ($connection === null) {
            throw new RuntimeException('No LDAP connector is configured for this organization yet.');
        }

        if (! (bool) ($connection['is_enabled'] ?? false)) {
            throw new RuntimeException('The LDAP connector is disabled for this organization.');
        }

        $connectionId = (string) $connection['id'];
        $this->repository->markSyncStarted($connectionId);

        try {
            $directoryUsers = $this->gateway->fetchUsers($connection);
            $subjects = [];
            $imported = 0;
            $updated = 0;

            foreach ($directoryUsers as $entry) {
                $result = $this->users->syncDirectoryUser([
                    'organization_id' => $organizationId,
                    'username' => (string) $entry['username'],
                    'display_name' => (string) $entry['display_name'],
                    'email' => (string) $entry['email'],
                    'job_title' => is_string($entry['job_title'] ?? null) ? $entry['job_title'] : null,
                    'auth_provider' => 'ldap',
                    'external_subject' => (string) $entry['external_subject'],
                    'directory_source' => $connectionId,
                    'directory_groups' => is_array($entry['group_names'] ?? null) ? $entry['group_names'] : [],
                    'is_active' => (bool) ($entry['is_active'] ?? true),
                    'magic_link_enabled' => (bool) ($connection['fallback_email_enabled'] ?? true),
                ]);

                $subjects[] = (string) $entry['external_subject'];

                if ($result['created']) {
                    $imported++;
                } else {
                    $updated++;
                }

                $access = $this->repository->resolveMappingAccess($connectionId, is_array($entry['group_names'] ?? null) ? $entry['group_names'] : []);
                $membershipId = $this->managedMembershipId($organizationId, (string) $result['user']['principal_id']);

                $this->users->upsertManagedMembership($membershipId, [
                    'principal_id' => (string) $result['user']['principal_id'],
                    'organization_id' => $organizationId,
                    'role_keys' => $access['roles'],
                    'scope_ids' => $access['scopes'],
                    'is_active' => (bool) ($entry['is_active'] ?? true),
                ], $triggeredByPrincipalId);
            }

            $deactivatedPrincipals = $this->users->deactivateDirectoryUsersMissingFromSync(
                organizationId: $organizationId,
                authProvider: 'ldap',
                directorySource: $connectionId,
                activeExternalSubjects: $subjects,
            );

            foreach ($deactivatedPrincipals as $principalId) {
                $membershipId = $this->managedMembershipId($organizationId, $principalId);

                $this->users->upsertManagedMembership($membershipId, [
                    'principal_id' => $principalId,
                    'organization_id' => $organizationId,
                    'role_keys' => [],
                    'scope_ids' => [],
                    'is_active' => false,
                ], $triggeredByPrincipalId);
            }

            $message = sprintf(
                '%d imported, %d refreshed, %d disabled from cache.',
                $imported,
                $updated,
                count($deactivatedPrincipals),
            );

            $this->repository->markSyncCompleted($connectionId, 'success', $message);
            $this->repository->publishSyncEvent($organizationId, [
                'connection_id' => $connectionId,
                'imported' => $imported,
                'updated' => $updated,
                'disabled' => count($deactivatedPrincipals),
            ]);

            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-ldap.sync.completed',
                outcome: 'success',
                originComponent: 'identity-ldap',
                principalId: $triggeredByPrincipalId,
                organizationId: $organizationId,
                targetType: 'identity_ldap_connection',
                targetId: $connectionId,
                summary: [
                    'imported' => $imported,
                    'updated' => $updated,
                    'disabled' => count($deactivatedPrincipals),
                ],
                executionOrigin: 'identity-ldap',
            ));

            return [
                'status' => 'success',
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'disabled' => count($deactivatedPrincipals),
            ];
        } catch (Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 1000, '');
            $this->repository->markSyncCompleted($connectionId, 'failed', $message);

            $this->audit->record(new AuditRecordData(
                eventType: 'plugin.identity-ldap.sync.completed',
                outcome: 'failure',
                originComponent: 'identity-ldap',
                principalId: $triggeredByPrincipalId,
                organizationId: $organizationId,
                targetType: 'identity_ldap_connection',
                targetId: $connectionId,
                summary: [
                    'message' => $message,
                ],
                executionOrigin: 'identity-ldap',
            ));

            throw $exception;
        }
    }

    private function managedMembershipId(string $organizationId, string $principalId): string
    {
        $base = sprintf(
            'membership-ldap-%s-%s',
            Str::slug($organizationId),
            Str::slug(Str::after($principalId, 'principal-'))
        );

        return $base !== 'membership-ldap--' ? $base : 'membership-ldap-'.Str::lower(Str::ulid());
    }
}
