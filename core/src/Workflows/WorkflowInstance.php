<?php

namespace PymeSec\Core\Workflows;

class WorkflowInstance
{
    public function __construct(
        public readonly int $id,
        public readonly string $workflowKey,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $organizationId,
        public readonly ?string $scopeId,
        public readonly string $currentState,
    ) {}
}
