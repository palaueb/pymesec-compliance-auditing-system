<?php

namespace PymeSec\Core\Workflows;

class WorkflowTransitionDefinition
{
    /**
     * @param  array<int, string>  $fromStates
     */
    public function __construct(
        public readonly string $key,
        public readonly array $fromStates,
        public readonly string $toState,
        public readonly ?string $permission = null,
        public readonly bool $auditSensitive = true,
    ) {}
}
