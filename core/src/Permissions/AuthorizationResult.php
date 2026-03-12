<?php

namespace PymeSec\Core\Permissions;

class AuthorizationResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly array $matchedGrants = [],
    ) {
    }

    public static function allow(array $matchedGrants = [], ?string $reason = null): self
    {
        return new self('allow', $reason, $matchedGrants);
    }

    public static function deny(?string $reason = null, array $matchedGrants = []): self
    {
        return new self('deny', $reason, $matchedGrants);
    }

    public static function unresolved(?string $reason = null, array $matchedGrants = []): self
    {
        return new self('unresolved', $reason, $matchedGrants);
    }

    public function allowed(): bool
    {
        return $this->status === 'allow';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'allowed' => $this->allowed(),
            'reason' => $this->reason,
            'matched_grants' => $this->matchedGrants,
        ];
    }
}
