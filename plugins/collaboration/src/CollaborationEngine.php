<?php

namespace PymeSec\Plugins\Collaboration;

use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;

class CollaborationEngine implements CollaborationEngineInterface
{
    public function collaboratorLifecycleStates(): array
    {
        return [
            'active' => $this->translatedLabel('plugin.collaboration.collaborator_lifecycle.active', 'Active'),
            'blocked' => $this->translatedLabel('plugin.collaboration.collaborator_lifecycle.blocked', 'Blocked'),
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
            'comment' => $this->translatedLabel('plugin.collaboration.draft_type.comment', 'Comment draft'),
            'request' => $this->translatedLabel('plugin.collaboration.draft_type.request', 'Follow-up draft'),
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
            'open' => $this->translatedLabel('plugin.collaboration.request_status.open', 'Open'),
            'in-progress' => $this->translatedLabel('plugin.collaboration.request_status.in_progress', 'In progress'),
            'waiting' => $this->translatedLabel('plugin.collaboration.request_status.waiting', 'Waiting'),
            'done' => $this->translatedLabel('plugin.collaboration.request_status.done', 'Done'),
            'cancelled' => $this->translatedLabel('plugin.collaboration.request_status.cancelled', 'Cancelled'),
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
            'low' => $this->translatedLabel('plugin.collaboration.request_priority.low', 'Low'),
            'normal' => $this->translatedLabel('plugin.collaboration.request_priority.normal', 'Normal'),
            'high' => $this->translatedLabel('plugin.collaboration.request_priority.high', 'High'),
            'urgent' => $this->translatedLabel('plugin.collaboration.request_priority.urgent', 'Urgent'),
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
            'review' => $this->translatedLabel('plugin.collaboration.handoff_state.review', 'Review'),
            'remediation' => $this->translatedLabel('plugin.collaboration.handoff_state.remediation', 'Remediation'),
            'approval' => $this->translatedLabel('plugin.collaboration.handoff_state.approval', 'Approval'),
            'closed-loop' => $this->translatedLabel('plugin.collaboration.handoff_state.closed_loop', 'Closed loop'),
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

    private function translatedLabel(string $key, string $fallback): string
    {
        $catalogue = $this->catalogue();

        return is_string($catalogue[$key] ?? null) && $catalogue[$key] !== ''
            ? $catalogue[$key]
            : $fallback;
    }

    /**
     * @return array<string, string>
     */
    private function catalogue(): array
    {
        static $cache = [];

        $locale = (string) app()->getLocale();

        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        $basePath = dirname(__DIR__).'/resources/lang';
        $english = $this->loadCatalogue($basePath.'/en.json');
        $localized = $locale === 'en' ? [] : $this->loadCatalogue($basePath.'/'.$locale.'.json');

        return $cache[$locale] = array_replace($english, $localized);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalogue(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_filter($decoded, static fn (mixed $value): bool => is_string($value)) : [];
    }
}
