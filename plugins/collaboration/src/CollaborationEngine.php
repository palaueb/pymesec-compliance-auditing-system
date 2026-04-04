<?php

namespace PymeSec\Plugins\Collaboration;

use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;

class CollaborationEngine implements CollaborationEngineInterface
{
    public function collaboratorLifecycleStates(): array
    {
        return [
            'active' => 'Active',
            'blocked' => 'Blocked',
        ];
    }

    public function collaboratorLifecycleStateKeys(): array
    {
        return array_keys($this->collaboratorLifecycleStates());
    }

    public function collaboratorLifecycleStateLabel(string $state): string
    {
        return $this->collaboratorLifecycleStates()[$state] ?? ucwords(str_replace('-', ' ', $state));
    }

    public function draftTypes(): array
    {
        return [
            'comment' => 'Comment draft',
            'request' => 'Follow-up draft',
        ];
    }

    public function draftTypeKeys(): array
    {
        return array_keys($this->draftTypes());
    }

    public function draftTypeLabel(string $draftType): string
    {
        return $this->draftTypes()[$draftType] ?? ucwords(str_replace('-', ' ', $draftType));
    }

    public function requestStatuses(): array
    {
        return [
            'open' => 'Open',
            'in-progress' => 'In progress',
            'waiting' => 'Waiting',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ];
    }

    public function requestStatusKeys(): array
    {
        return array_keys($this->requestStatuses());
    }

    public function requestStatusLabel(string $status): string
    {
        return $this->requestStatuses()[$status] ?? ucwords(str_replace('-', ' ', $status));
    }

    public function requestPriorities(): array
    {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public function requestPriorityKeys(): array
    {
        return array_keys($this->requestPriorities());
    }

    public function requestPriorityLabel(string $priority): string
    {
        return $this->requestPriorities()[$priority] ?? ucfirst($priority);
    }

    public function handoffStates(): array
    {
        return [
            'review' => 'Review',
            'remediation' => 'Remediation',
            'approval' => 'Approval',
            'closed-loop' => 'Closed loop',
        ];
    }

    public function handoffStateKeys(): array
    {
        return array_keys($this->handoffStates());
    }

    public function handoffStateLabel(string $handoffState): string
    {
        return $this->handoffStates()[$handoffState] ?? ucwords(str_replace('-', ' ', $handoffState));
    }
}
