<?php

namespace PymeSec\Core\Notifications;

class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly string $status,
        public readonly ?string $principalId = null,
        public readonly ?string $functionalActorId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $scopeId = null,
        public readonly ?string $sourceEventName = null,
        public readonly ?string $deliverAt = null,
        public readonly ?string $dispatchedAt = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'principal_id' => $this->principalId,
            'functional_actor_id' => $this->functionalActorId,
            'organization_id' => $this->organizationId,
            'scope_id' => $this->scopeId,
            'source_event_name' => $this->sourceEventName,
            'deliver_at' => $this->deliverAt,
            'dispatched_at' => $this->dispatchedAt,
            'metadata' => $this->metadata,
        ];
    }
}
