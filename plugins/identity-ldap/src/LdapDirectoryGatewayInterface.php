<?php

namespace PymeSec\Plugins\IdentityLdap;

interface LdapDirectoryGatewayInterface
{
    /**
     * @param  array<string, mixed>  $connection
     * @return array<int, array<string, mixed>>
     */
    public function fetchUsers(array $connection): array;

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, mixed>|null
     */
    public function authenticate(array $connection, string $login, string $password): ?array;
}
