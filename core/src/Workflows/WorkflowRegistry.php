<?php

namespace PymeSec\Core\Workflows;

use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;

class WorkflowRegistry implements WorkflowRegistryInterface
{
    /**
     * @var array<string, WorkflowDefinition>
     */
    private array $definitions = [];

    public function register(WorkflowDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
        ksort($this->definitions);
    }

    public function has(string $workflowKey): bool
    {
        return isset($this->definitions[$workflowKey]);
    }

    public function definition(string $workflowKey): ?WorkflowDefinition
    {
        return $this->definitions[$workflowKey] ?? null;
    }

    public function all(): array
    {
        return array_values($this->definitions);
    }
}
