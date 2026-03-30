<?php

namespace PymeSec\Plugins\IdentityLdap;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Plugins\IdentityLocal\IdentityLocalAuthService;
use PymeSec\Plugins\IdentityLocal\IdentityLocalRepository;

class IdentityLdapService
{
    public function __construct(
        private readonly IdentityLdapRepository $repository,
        private readonly IdentityLocalRepository $users,
        private readonly IdentityLocalAuthService $auth,
        private readonly LdapDirectoryGatewayInterface $gateway,
        private readonly AuditTrailInterface $audit,
    ) {}

    /**
     * @return array{code:string,user:array<string,mixed>}|null
     */
    public function beginPasswordLogin(string $login, string $password, ?Request $request = null): ?array
    {
        $candidate = $this->resolveLoginCandidate($login);

        if ($candidate === null) {
            return null;
        }

        $organizationId = (string) ($candidate['organization_id'] ?? '');
        $connection = $organizationId !== '' ? $this->repository->connectionForOrganization($organizationId) : null;

        if ($connection === null || ! (bool) ($connection['is_enabled'] ?? false)) {
            $this->recordAuthAttempt('failure', $candidate, [
                'login' => $login,
                'reason' => 'connector_missing_or_disabled',
            ]);

            return null;
        }

        $directoryUser = $this->gateway->authenticate($connection, $login, $password);

        if ($directoryUser === null || ! $this->matchesDirectoryIdentity($candidate, $directoryUser)) {
            $this->recordAuthAttempt('failure', $candidate, [
                'login' => $login,
                'reason' => 'invalid_directory_credentials',
            ]);

            return null;
        }

        $challenge = $this->auth->beginPasswordChallengeForPrincipal((string) $candidate['principal_id'], 'password-2fa', $request);

        if ($challenge === null) {
            $this->recordAuthAttempt('failure', $candidate, [
                'login' => $login,
                'reason' => 'cached_user_unavailable',
            ]);

            return null;
        }

        $this->recordAuthAttempt('success', $candidate, [
            'login' => $login,
            'login_mode' => (string) ($connection['login_mode'] ?? 'username'),
            'delivery' => 'mail_code',
        ]);

        return $challenge;
    }

    /**
     * @return array{token:string,user:array<string,mixed>}|null
     */
    public function issueMagicLink(string $login, ?Request $request = null): ?array
    {
        $candidate = $this->resolveFallbackCandidate($login);

        if ($candidate === null) {
            return null;
        }

        $organizationId = (string) ($candidate['organization_id'] ?? '');
        $connection = $organizationId !== '' ? $this->repository->connectionForOrganization($organizationId) : null;

        if ($connection === null || ! (bool) ($connection['fallback_email_enabled'] ?? false)) {
            $this->recordLinkAttempt('failure', $candidate, [
                'login' => $login,
                'reason' => 'fallback_email_disabled',
            ]);

            return null;
        }

        $issued = $this->auth->issueMagicLinkForPrincipal((string) $candidate['principal_id'], $request);

        if ($issued === null) {
            $this->recordLinkAttempt('failure', $candidate, [
                'login' => $login,
                'reason' => 'cached_user_unavailable',
            ]);

            return null;
        }

        $this->recordLinkAttempt('success', $candidate, [
            'login' => $login,
            'delivery' => 'mail',
        ]);

        return $issued;
    }

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

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLoginCandidate(string $login): ?array
    {
        $normalized = trim($login);

        if ($normalized === '') {
            return null;
        }

        $emailCandidate = $this->users->findActiveDirectoryUserByLoginMode($normalized, 'ldap', 'email');
        $usernameCandidate = $this->users->findActiveDirectoryUserByLoginMode($normalized, 'ldap', 'username');

        foreach (array_filter([$emailCandidate, $usernameCandidate]) as $candidate) {
            $organizationId = (string) ($candidate['organization_id'] ?? '');
            $connection = $organizationId !== '' ? $this->repository->connectionForOrganization($organizationId) : null;

            if ($connection === null) {
                continue;
            }

            $loginMode = (string) ($connection['login_mode'] ?? 'username');

            if ($loginMode === 'email' && $emailCandidate !== null && (string) $emailCandidate['principal_id'] === (string) $candidate['principal_id']) {
                return $candidate;
            }

            if ($loginMode === 'username' && $usernameCandidate !== null && (string) $usernameCandidate['principal_id'] === (string) $candidate['principal_id']) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFallbackCandidate(string $login): ?array
    {
        $normalized = Str::lower(trim($login));

        if ($normalized === '') {
            return null;
        }

        foreach (['email', 'username'] as $loginMode) {
            $candidate = $this->users->findActiveDirectoryUserByLoginMode($normalized, 'ldap', $loginMode);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $directoryUser
     */
    private function matchesDirectoryIdentity(array $candidate, array $directoryUser): bool
    {
        $candidateSubject = Str::lower((string) ($candidate['external_subject'] ?? ''));
        $directorySubject = Str::lower((string) ($directoryUser['external_subject'] ?? ''));

        if ($candidateSubject !== '' && $directorySubject !== '') {
            return $candidateSubject === $directorySubject;
        }

        return Str::lower((string) ($candidate['email'] ?? '')) === Str::lower((string) ($directoryUser['email'] ?? ''))
            && Str::lower((string) ($candidate['username'] ?? '')) === Str::lower((string) ($directoryUser['username'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $summary
     */
    private function recordAuthAttempt(string $outcome, array $candidate, array $summary): void
    {
        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-ldap.auth.password.requested',
            outcome: $outcome,
            originComponent: 'identity-ldap',
            principalId: $outcome === 'success' ? (string) ($candidate['principal_id'] ?? '') : null,
            organizationId: is_string($candidate['organization_id'] ?? null) ? $candidate['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: is_string($candidate['id'] ?? null) ? $candidate['id'] : null,
            summary: $summary,
            executionOrigin: 'identity-ldap-auth',
        ));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $summary
     */
    private function recordLinkAttempt(string $outcome, array $candidate, array $summary): void
    {
        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-ldap.auth.link.requested',
            outcome: $outcome,
            originComponent: 'identity-ldap',
            principalId: $outcome === 'success' ? (string) ($candidate['principal_id'] ?? '') : null,
            organizationId: is_string($candidate['organization_id'] ?? null) ? $candidate['organization_id'] : null,
            targetType: 'identity_local_user',
            targetId: is_string($candidate['id'] ?? null) ? $candidate['id'] : null,
            summary: $summary,
            executionOrigin: 'identity-ldap-auth',
        ));
    }
}
