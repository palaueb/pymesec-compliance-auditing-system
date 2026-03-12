<?php

namespace PymeSec\Core\Events\Contracts;

use Closure;
use PymeSec\Core\Events\PublicEvent;

interface EventBusInterface
{
    /**
     * @param  Closure(PublicEvent): void  $listener
     */
    public function subscribe(string $eventName, Closure $listener): void;

    public function publish(PublicEvent $event): PublicEvent;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, PublicEvent>
     */
    public function latest(int $limit = 50, array $filters = []): array;
}
