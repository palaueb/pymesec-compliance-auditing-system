<?php

namespace PymeSec\Core\Workflows\Contracts;

use PymeSec\Core\Workflows\WorkflowDefinition;

interface WorkflowRegistryInterface
{
    public function register(WorkflowDefinition $definition): void;

    public function has(string $workflowKey): bool;

    public function definition(string $workflowKey): ?WorkflowDefinition;

    /**
     * @return array<int, WorkflowDefinition>
     */
    public function all(): array;
}
