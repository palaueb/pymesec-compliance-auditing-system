<?php

namespace PymeSec\Core\Workflows;

class WorkflowDefinition
{
    /**
     * @param  array<int, string>  $states
     * @param  array<int, WorkflowTransitionDefinition>  $transitions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $owner,
        public readonly string $label,
        public readonly string $initialState,
        public readonly array $states,
        public readonly array $transitions,
    ) {}

    public function transition(string $transitionKey): ?WorkflowTransitionDefinition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->key === $transitionKey) {
                return $transition;
            }
        }

        return null;
    }
}
