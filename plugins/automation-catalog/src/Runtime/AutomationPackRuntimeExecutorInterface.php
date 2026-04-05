<?php

namespace PymeSec\Plugins\AutomationCatalog\Runtime;

interface AutomationPackRuntimeExecutorInterface
{
    /**
     * @param  array<string, string>  $pack
     */
    public function supports(array $pack): bool;

    /**
     * @param  array<string, string>  $pack
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $target
     * @param  array<string, string>  $context
     * @return array{
     *   status: string,
     *   artifact_type: string,
     *   label: string,
     *   filename: string,
     *   content: string,
     *   message: string,
     *   check_outcome?: string,
     *   severity?: string,
     *   change_fingerprint?: string
     * }
     */
    public function generateEvidencePayload(array $pack, array $mapping, array $target, array $context): array;
}
