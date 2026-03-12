<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_logs_endpoint_returns_audited_records_for_platform_admin(): void
    {
        $this->artisan('plugins:enable identity-local')->assertExitCode(0);

        $this->get('/core/audit-logs?principal_id=principal-admin')
            ->assertOk()
            ->assertJsonPath('audit_logs.0.event_type', 'core.plugins.enable')
            ->assertJsonPath('audit_logs.0.outcome', 'success');
    }

    public function test_the_audit_list_command_reports_latest_records(): void
    {
        $this->artisan('plugins:disable hello-world')->assertExitCode(0);

        $this->artisan('audit:list --limit=5')
            ->expectsOutputToContain('core.plugins.disable')
            ->assertExitCode(0);
    }

    public function test_the_audit_export_endpoint_returns_ndjson_for_platform_admin(): void
    {
        $this->artisan('plugins:enable identity-local')->assertExitCode(0);

        $response = $this->get('/core/audit-logs/export?principal_id=principal-admin&format=jsonl&event_type=core.plugins.enable');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson; charset=UTF-8');

        $this->assertStringContainsString('core.plugins.enable', $response->getContent());
        $this->assertStringContainsString('"outcome":"success"', $response->getContent());
    }

    public function test_the_audit_export_endpoint_requires_export_permission(): void
    {
        $this->artisan('plugins:enable identity-local')->assertExitCode(0);

        $this->get('/core/audit-logs/export?principal_id=principal-org-a&format=jsonl')
            ->assertForbidden();
    }

    public function test_the_audit_export_command_supports_csv_output(): void
    {
        $this->artisan('plugins:disable hello-world')->assertExitCode(0);

        $exitCode = Artisan::call('audit:export', [
            '--format' => 'csv',
            '--limit' => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();

        $this->assertStringContainsString('event_type', $output);
        $this->assertGreaterThan(1, count(array_filter(explode("\n", trim($output)))));
    }
}
