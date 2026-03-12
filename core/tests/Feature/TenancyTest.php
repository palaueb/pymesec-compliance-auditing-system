<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tenancy_endpoint_returns_resolved_context_for_a_principal(): void
    {
        $this->get('/core/tenancy?principal_id=principal-admin&subject_principal_id=principal-org-a&organization_id=org-a')
            ->assertOk()
            ->assertJsonPath('selected_organization.id', 'org-a')
            ->assertJsonPath('selected_organization.name', 'Northwind Manufacturing')
            ->assertJsonCount(2, 'organizations')
            ->assertJsonCount(2, 'scopes')
            ->assertJsonCount(2, 'memberships');
    }

    public function test_the_tenancy_command_lists_organizations_for_the_principal(): void
    {
        $this->artisan('tenancy:list principal-org-a')
            ->expectsTable(
                ['Organization', 'Name', 'Locale', 'Timezone', 'Scopes', 'Memberships'],
                [
                    ['org-a', 'Northwind Manufacturing', 'en', 'Europe/Madrid', '2', '2'],
                    ['org-b', 'Bluewave Logistics', 'en', 'Europe/Berlin', '1', '1'],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_the_shell_switches_organization_and_scope_using_tenancy_context(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-b&scope_id=scope-ops')
            ->assertOk()
            ->assertSee('Bluewave Logistics')
            ->assertSee('Warehouse Mesh')
            ->assertSee('Route Planner')
            ->assertDontSee('ERP Production');
    }

    public function test_archiving_an_organization_removes_it_from_resolved_tenancy_and_writes_audit(): void
    {
        $this->artisan('tenancy:archive-organization org-b')
            ->expectsOutputToContain('Organization [org-b] archived.')
            ->assertExitCode(0);

        $this->get('/core/tenancy?principal_id=principal-admin&subject_principal_id=principal-org-a')
            ->assertOk()
            ->assertJsonCount(1, 'organizations')
            ->assertJsonMissing([
                'id' => 'org-b',
                'name' => 'Bluewave Logistics',
            ]);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.tenancy.organization.archived')
            ->assertOk()
            ->assertJsonPath('audit_logs.0.target_id', 'org-b');
    }

    public function test_archiving_a_scope_removes_it_from_tenancy_context_and_writes_audit(): void
    {
        $this->artisan('tenancy:archive-scope scope-it')
            ->expectsOutputToContain('Scope [scope-it] archived.')
            ->assertExitCode(0);

        $this->get('/core/tenancy?principal_id=principal-admin&subject_principal_id=principal-org-a&organization_id=org-a')
            ->assertOk()
            ->assertJsonCount(1, 'scopes')
            ->assertJsonMissing([
                'id' => 'scope-it',
            ]);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.tenancy.scope.archived')
            ->assertOk()
            ->assertJsonPath('audit_logs.0.target_id', 'scope-it');
    }

    public function test_activating_a_scope_restores_it_to_tenancy_context_and_writes_audit(): void
    {
        $this->artisan('tenancy:archive-scope scope-it')->assertExitCode(0);
        $this->artisan('tenancy:activate-scope scope-it')
            ->expectsOutputToContain('Scope [scope-it] activated.')
            ->assertExitCode(0);

        $this->get('/core/tenancy?principal_id=principal-admin&subject_principal_id=principal-org-a&organization_id=org-a')
            ->assertOk()
            ->assertJsonCount(2, 'scopes')
            ->assertJsonFragment([
                'id' => 'scope-it',
            ]);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.tenancy.scope.activated')
            ->assertOk()
            ->assertJsonPath('audit_logs.0.target_id', 'scope-it');
    }

    public function test_failed_scope_activation_is_audited(): void
    {
        $this->artisan('tenancy:activate-scope scope-it')
            ->expectsOutputToContain('Scope [scope-it] was not activated.')
            ->assertExitCode(1);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.tenancy.scope.activated&outcome=failure')
            ->assertOk()
            ->assertJsonPath('audit_logs.0.target_id', 'scope-it')
            ->assertJsonPath('audit_logs.0.summary.command', 'tenancy:activate-scope');
    }
}
