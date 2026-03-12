<?php

namespace PymeSec\Core\Permissions;

class PermissionGrant
{
    public function __construct(
        public readonly string $targetType,
        public readonly string $targetId,
        public readonly string $grantType,
        public readonly string $value,
        public readonly string $contextType,
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'grant_type' => $this->grantType,
            'value' => $this->value,
            'context_type' => $this->contextType,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
        ];
    }
}
