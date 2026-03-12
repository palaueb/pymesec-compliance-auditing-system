<?php

namespace PymeSec\Core\Workflows;

use Illuminate\Support\Facades\DB;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowRegistryInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use RuntimeException;

class DatabaseWorkflowService implements WorkflowServiceInterface
{
    public function __construct(
        private readonly WorkflowRegistryInterface $registry,
        private readonly AuthorizationServiceInterface $authorization,
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    public function instanceFor(string $workflowKey, string $subjectType, string $subjectId, string $organizationId, ?string $scopeId = null): WorkflowInstance
    {
        $definition = $this->registry->definition($workflowKey);

        if ($definition === null) {
            throw new RuntimeException(sprintf('Workflow [%s] is not registered.', $workflowKey));
        }

        $record = DB::table('workflow_instances')->where([
            'workflow_key' => $workflowKey,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'organization_id' => $organizationId,
        ])->first();

        if ($record === null) {
            $id = DB::table('workflow_instances')->insertGetId([
                'workflow_key' => $workflowKey,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'current_state' => $definition->initialState,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DB::table('workflow_instances')->where('id', $id)->first();
        }

        return new WorkflowInstance(
            id: (int) $record->id,
            workflowKey: (string) $record->workflow_key,
            subjectType: (string) $record->subject_type,
            subjectId: (string) $record->subject_id,
            organizationId: (string) $record->organization_id,
            scopeId: is_string($record->scope_id) ? $record->scope_id : null,
            currentState: (string) $record->current_state,
        );
    }

    public function transition(string $workflowKey, string $subjectType, string $subjectId, string $transitionKey, WorkflowExecutionContext $context): WorkflowTransitionRecord
    {
        $definition = $this->registry->definition($workflowKey);

        if ($definition === null) {
            throw new RuntimeException(sprintf('Workflow [%s] is not registered.', $workflowKey));
        }

        $instance = $this->instanceFor($workflowKey, $subjectType, $subjectId, $context->organizationId, $context->scopeId);
        $transition = $definition->transition($transitionKey);

        if ($transition === null) {
            $this->auditTransition($definition->owner, $context, $subjectType, $subjectId, $workflowKey, $transitionKey, 'failure', [
                'reason' => 'transition_not_defined',
                'from_state' => $instance->currentState,
            ]);

            throw new RuntimeException(sprintf('Transition [%s] is not defined for workflow [%s].', $transitionKey, $workflowKey));
        }

        if (! in_array($instance->currentState, $transition->fromStates, true)) {
            $this->auditTransition($definition->owner, $context, $subjectType, $subjectId, $workflowKey, $transitionKey, 'failure', [
                'reason' => 'transition_not_allowed_from_state',
                'from_state' => $instance->currentState,
                'to_state' => $transition->toState,
            ], $transition->auditSensitive);

            throw new RuntimeException(sprintf(
                'Transition [%s] is not allowed from state [%s].',
                $transitionKey,
                $instance->currentState,
            ));
        }

        if ($transition->permission !== null) {
            $authorization = $this->authorization->authorize(new AuthorizationContext(
                principal: $context->principal,
                permission: $transition->permission,
                memberships: $context->memberships,
                organizationId: $context->organizationId,
                scopeId: $context->scopeId,
            ));

            if ($authorization->status !== 'allow') {
                $this->auditTransition($definition->owner, $context, $subjectType, $subjectId, $workflowKey, $transitionKey, 'failure', [
                    'reason' => 'permission_denied',
                    'permission' => $transition->permission,
                    'from_state' => $instance->currentState,
                    'to_state' => $transition->toState,
                ], $transition->auditSensitive);

                throw new RuntimeException(sprintf(
                    'Transition [%s] denied by permission [%s].',
                    $transitionKey,
                    $transition->permission,
                ));
            }
        }

        DB::table('workflow_instances')->where('id', $instance->id)->update([
            'current_state' => $transition->toState,
            'updated_at' => now(),
        ]);

        $id = DB::table('workflow_transitions')->insertGetId([
            'workflow_instance_id' => $instance->id,
            'workflow_key' => $workflowKey,
            'transition_key' => $transition->key,
            'from_state' => $instance->currentState,
            'to_state' => $transition->toState,
            'principal_id' => $context->principal->id,
            'membership_id' => $context->membershipId,
            'created_at' => now(),
        ]);

        $record = DB::table('workflow_transitions')->where('id', $id)->first();

        $this->auditTransition($definition->owner, $context, $subjectType, $subjectId, $workflowKey, $transitionKey, 'success', [
            'from_state' => $instance->currentState,
            'to_state' => $transition->toState,
        ], $transition->auditSensitive);

        return new WorkflowTransitionRecord(
            id: (int) $record->id,
            instanceId: (int) $record->workflow_instance_id,
            workflowKey: (string) $record->workflow_key,
            transitionKey: (string) $record->transition_key,
            fromState: (string) $record->from_state,
            toState: (string) $record->to_state,
            principalId: is_string($record->principal_id) ? $record->principal_id : null,
            membershipId: is_string($record->membership_id) ? $record->membership_id : null,
            createdAt: (string) $record->created_at,
        );
    }

    public function history(string $workflowKey, string $subjectType, string $subjectId): array
    {
        return DB::table('workflow_transitions')
            ->join('workflow_instances', 'workflow_instances.id', '=', 'workflow_transitions.workflow_instance_id')
            ->where('workflow_instances.workflow_key', $workflowKey)
            ->where('workflow_instances.subject_type', $subjectType)
            ->where('workflow_instances.subject_id', $subjectId)
            ->orderBy('workflow_transitions.id')
            ->get([
                'workflow_transitions.id',
                'workflow_transitions.workflow_instance_id',
                'workflow_transitions.workflow_key',
                'workflow_transitions.transition_key',
                'workflow_transitions.from_state',
                'workflow_transitions.to_state',
                'workflow_transitions.principal_id',
                'workflow_transitions.membership_id',
                'workflow_transitions.created_at',
            ])
            ->map(static fn ($record): WorkflowTransitionRecord => new WorkflowTransitionRecord(
                id: (int) $record->id,
                instanceId: (int) $record->workflow_instance_id,
                workflowKey: (string) $record->workflow_key,
                transitionKey: (string) $record->transition_key,
                fromState: (string) $record->from_state,
                toState: (string) $record->to_state,
                principalId: is_string($record->principal_id) ? $record->principal_id : null,
                membershipId: is_string($record->membership_id) ? $record->membership_id : null,
                createdAt: (string) $record->created_at,
            ))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function auditTransition(
        string $owner,
        WorkflowExecutionContext $context,
        string $subjectType,
        string $subjectId,
        string $workflowKey,
        string $transitionKey,
        string $outcome,
        array $summary,
        bool $auditSensitive = true,
    ): void {
        if (! $auditSensitive) {
            return;
        }

        $this->audit->record(new AuditRecordData(
            eventType: $owner === 'core'
                ? 'core.workflows.transition'
                : sprintf('plugin.%s.workflows.transition', $owner),
            outcome: $outcome,
            originComponent: $owner,
            principalId: $context->principal->id,
            membershipId: $context->membershipId,
            organizationId: $context->organizationId,
            scopeId: $context->scopeId,
            targetType: $subjectType,
            targetId: $subjectId,
            summary: [
                'workflow_key' => $workflowKey,
                'transition_key' => $transitionKey,
                ...$summary,
            ],
            executionOrigin: 'workflow',
        ));

        $this->events->publish(new PublicEvent(
            name: $owner === 'core'
                ? ($outcome === 'success' ? 'core.workflows.transitioned' : 'core.workflows.transition-failed')
                : sprintf(
                    'plugin.%s.workflows.%s',
                    $owner,
                    $outcome === 'success' ? 'transitioned' : 'transition-failed',
                ),
            originComponent: $owner,
            organizationId: $context->organizationId,
            scopeId: $context->scopeId,
            payload: [
                'workflow_key' => $workflowKey,
                'transition_key' => $transitionKey,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'outcome' => $outcome,
                ...$summary,
            ],
        ));
    }
}
