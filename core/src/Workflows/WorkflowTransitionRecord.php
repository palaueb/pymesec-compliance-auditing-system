<?php

namespace PymeSec\Core\Workflows;

class WorkflowTransitionRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $instanceId,
        public readonly string $workflowKey,
        public readonly string $transitionKey,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?string $principalId,
        public readonly ?string $membershipId,
        public readonly string $createdAt,
    ) {
    }
}
