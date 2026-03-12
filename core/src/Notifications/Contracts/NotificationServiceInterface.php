<?php

namespace PymeSec\Core\Notifications\Contracts;

use PymeSec\Core\Notifications\NotificationMessage;

interface NotificationServiceInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function notify(
        string $type,
        string $title,
        string $body,
        ?string $principalId = null,
        ?string $functionalActorId = null,
        ?string $organizationId = null,
        ?string $scopeId = null,
        ?string $sourceEventName = null,
        array $metadata = [],
        ?string $deliverAt = null,
    ): NotificationMessage;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, NotificationMessage>
     */
    public function latest(int $limit = 50, array $filters = []): array;

    public function dispatchDue(): int;
}
