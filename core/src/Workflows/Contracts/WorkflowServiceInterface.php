<?php

namespace PymeSec\Core\Workflows\Contracts;

use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Core\Workflows\WorkflowInstance;
use PymeSec\Core\Workflows\WorkflowTransitionRecord;

interface WorkflowServiceInterface
{
    public function instanceFor(string $workflowKey, string $subjectType, string $subjectId, string $organizationId, ?string $scopeId = null): WorkflowInstance;

    public function transition(
        string $workflowKey,
        string $subjectType,
        string $subjectId,
        string $transitionKey,
        WorkflowExecutionContext $context,
    ): WorkflowTransitionRecord;

    /**
     * @return array<int, WorkflowTransitionRecord>
     */
    public function history(string $workflowKey, string $subjectType, string $subjectId): array;
}
