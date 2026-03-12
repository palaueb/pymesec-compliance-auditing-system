<?php

namespace PymeSec\Core\Audit\Contracts;

use PymeSec\Core\Audit\AuditRecord;
use PymeSec\Core\Audit\AuditRecordData;

interface AuditTrailInterface
{
    public function record(AuditRecordData $record): AuditRecord;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, AuditRecord>
     */
    public function latest(int $limit = 50, array $filters = []): array;
}
