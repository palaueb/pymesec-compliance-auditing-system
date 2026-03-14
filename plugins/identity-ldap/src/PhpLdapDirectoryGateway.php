<?php

namespace PymeSec\Plugins\IdentityLdap;

use RuntimeException;

class PhpLdapDirectoryGateway implements LdapDirectoryGatewayInterface
{
    public function fetchUsers(array $connection): array
    {
        if (! function_exists('ldap_connect')) {
            throw new RuntimeException('PHP LDAP support is not available in this environment.');
        }

        $host = (string) ($connection['host'] ?? '');
        $port = (int) ($connection['port'] ?? 389);
        $baseDn = (string) ($connection['base_dn'] ?? '');

        if ($host === '' || $baseDn === '') {
            throw new RuntimeException('LDAP host and base DN are required before synchronization.');
        }

        $ldap = @ldap_connect($host, $port);

        if ($ldap === false) {
            throw new RuntimeException('Unable to connect to the configured LDAP server.');
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        $bindDn = (string) ($connection['bind_dn'] ?? '');
        $bindPassword = (string) ($connection['bind_password'] ?? '');
        $bound = $bindDn !== ''
            ? @ldap_bind($ldap, $bindDn, $bindPassword)
            : @ldap_bind($ldap);

        if ($bound !== true) {
            throw new RuntimeException('Unable to bind to the LDAP server with the configured service account.');
        }

        $userDnAttribute = (string) ($connection['user_dn_attribute'] ?? 'uid');
        $mailAttribute = (string) ($connection['mail_attribute'] ?? 'mail');
        $displayNameAttribute = (string) ($connection['display_name_attribute'] ?? 'cn');
        $jobTitleAttribute = (string) ($connection['job_title_attribute'] ?? 'title');
        $groupAttribute = (string) ($connection['group_attribute'] ?? 'memberOf');
        $filter = (string) ($connection['user_filter'] ?? '(objectClass=person)');

        $search = @ldap_search(
            $ldap,
            $baseDn,
            $filter !== '' ? $filter : '(objectClass=person)',
            [$userDnAttribute, $mailAttribute, $displayNameAttribute, $jobTitleAttribute, $groupAttribute]
        );

        if ($search === false) {
            throw new RuntimeException('LDAP search failed for the configured base DN or filter.');
        }

        $entries = ldap_get_entries($ldap, $search);
        $users = [];

        for ($index = 0; $index < (int) ($entries['count'] ?? 0); $index++) {
            $entry = $entries[$index];
            $dn = is_string($entry['dn'] ?? null) ? $entry['dn'] : null;
            $username = $this->entryValue($entry, $userDnAttribute);
            $email = $this->entryValue($entry, $mailAttribute);

            if ($dn === null || $username === null || $email === null) {
                continue;
            }

            $displayName = $this->entryValue($entry, $displayNameAttribute) ?? $username;
            $jobTitle = $this->entryValue($entry, $jobTitleAttribute);

            $users[] = [
                'external_subject' => $dn,
                'username' => $username,
                'email' => $email,
                'display_name' => $displayName,
                'job_title' => $jobTitle,
                'group_names' => $this->entryValues($entry, $groupAttribute),
                'is_active' => true,
            ];
        }

        return $users;
    }

    private function entryValue(array $entry, string $attribute): ?string
    {
        $values = $this->entryValues($entry, $attribute);

        return $values[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function entryValues(array $entry, string $attribute): array
    {
        $key = strtolower($attribute);
        $record = $entry[$key] ?? null;

        if (! is_array($record)) {
            return [];
        }

        $values = [];

        for ($index = 0; $index < (int) ($record['count'] ?? 0); $index++) {
            if (is_string($record[$index] ?? null) && $record[$index] !== '') {
                $values[] = (string) $record[$index];
            }
        }

        return $values;
    }
}
