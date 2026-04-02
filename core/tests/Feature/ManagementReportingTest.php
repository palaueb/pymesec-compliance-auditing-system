<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_management_reporting_screen_renders_cross_domain_summary(): void
    {
        $this->get('/app?menu=core.management-reporting&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Management Reporting')
            ->assertSee('Cross-domain executive summary')
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Restore bridge monthly test record')
            ->assertSee('Privileged access drift')
            ->assertSee('Access review evidence gap')
            ->assertSee('Vendors')
            ->assertSee('Northbridge Payroll Services')
            ->assertSee('Vendor decisions pending')
            ->assertDontSee('Restore assurance gap')
            ->assertDontSee('Restore test traceability gap');
    }

    public function test_the_management_reporting_screen_respects_scope_specific_slices(): void
    {
        $this->get('/app?menu=core.management-reporting&principal_id=principal-org-a&organization_id=org-a&scope_id=scope-eu&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Management Reporting')
            ->assertSee('Q2 Access and Resilience Review')
            ->assertSee('Privileged access drift')
            ->assertSee('Access review evidence gap')
            ->assertSee('Northbridge Payroll Services')
            ->assertDontSee('Restore Labs Ltd')
            ->assertDontSee('Restore bridge monthly test record')
            ->assertDontSee('Restore assurance gap')
            ->assertDontSee('Restore test traceability gap');
    }
}
