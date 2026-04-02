<?php

namespace PymeSec\Core\Collaboration\Contracts;

interface CollaborationEngineInterface
{
    /**
     * @return array<string, string>
     */
    public function draftTypes(): array;

    /**
     * @return array<int, string>
     */
    public function draftTypeKeys(): array;

    public function draftTypeLabel(string $draftType): string;

    /**
     * @return array<string, string>
     */
    public function requestStatuses(): array;

    /**
     * @return array<int, string>
     */
    public function requestStatusKeys(): array;

    public function requestStatusLabel(string $status): string;

    /**
     * @return array<string, string>
     */
    public function requestPriorities(): array;

    /**
     * @return array<int, string>
     */
    public function requestPriorityKeys(): array;

    public function requestPriorityLabel(string $priority): string;

    /**
     * @return array<string, string>
     */
    public function handoffStates(): array;

    /**
     * @return array<int, string>
     */
    public function handoffStateKeys(): array;

    public function handoffStateLabel(string $handoffState): string;
}
