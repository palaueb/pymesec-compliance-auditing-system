<?php

namespace PymeSec\Core\Reporting;

class ReportingMetricCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function headline(string $key, int|float|string $value): array
    {
        $definition = $this->headlineDefinition($key);

        return [
            'key' => $key,
            'label' => $definition['label'],
            'value' => (string) $value,
            'copy' => $definition['copy'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $key, int|float|string $value): array
    {
        return [
            'key' => $key,
            'label' => $this->summaryLabel($key),
            'value' => $value,
        ];
    }

    /**
     * @return array{label: string, copy: string}
     */
    private function headlineDefinition(string $key): array
    {
        return match ($key) {
            'active_assessments' => [
                'label' => 'Active assessments',
                'copy' => 'Campaigns currently running in the visible workspace.',
            ],
            'failing_reviews' => [
                'label' => 'Failing reviews',
                'copy' => 'Assessment control reviews currently marked as fail.',
            ],
            'evidence_review_due' => [
                'label' => 'Evidence review due',
                'copy' => 'Evidence records with review due in the next 30 days.',
            ],
            'risks_in_workflow' => [
                'label' => 'Risks in workflow',
                'copy' => 'Risks still identified, assessing, or treated.',
            ],
            'overdue_findings' => [
                'label' => 'Overdue findings',
                'copy' => 'Findings past due and not yet resolved.',
            ],
            default => [
                'label' => $this->humanize($key),
                'copy' => '',
            ],
        };
    }

    private function summaryLabel(string $key): string
    {
        return match ($key) {
            'campaigns' => 'Campaigns',
            'active_assessments' => 'Active',
            'failing_reviews' => 'Failing reviews',
            'linked_findings' => 'Linked findings',
            'records' => 'Records',
            'approved' => 'Approved',
            'review_due' => 'Review due',
            'needs_validation' => 'Needs validation',
            'risks' => 'Risks',
            'risks_in_workflow' => 'In workflow',
            'assessing' => 'Assessing',
            'average_residual' => 'Average residual',
            'findings' => 'Findings',
            'open_findings' => 'Open',
            'overdue_findings' => 'Overdue',
            'open_actions' => 'Open actions',
            default => $this->humanize($key),
        };
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
