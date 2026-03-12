<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Principals\MembershipReference;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_asset_workflow_initializes_instances_with_the_default_state(): void
    {
        $instance = $this->app->make(WorkflowServiceInterface::class)->instanceFor(
            workflowKey: 'plugin.asset-catalog.asset-lifecycle',
            subjectType: 'asset',
            subjectId: 'asset-erp-prod',
            organizationId: 'org-a',
        );

        $this->assertSame('draft', $instance->currentState);
    }

    public function test_the_asset_workflow_transitions_and_records_history(): void
    {
        $service = $this->app->make(WorkflowServiceInterface::class);

        $service->transition(
            workflowKey: 'plugin.asset-catalog.asset-lifecycle',
            subjectType: 'asset',
            subjectId: 'asset-erp-prod',
            transitionKey: 'submit-review',
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: 'principal-org-a', provider: 'demo'),
                memberships: [
                    new MembershipReference(
                        id: 'membership-org-a-hello',
                        principalId: 'principal-org-a',
                        organizationId: 'org-a',
                    ),
                ],
                organizationId: 'org-a',
                membershipId: 'membership-org-a-hello',
            ),
        );

        $instance = $service->instanceFor(
            workflowKey: 'plugin.asset-catalog.asset-lifecycle',
            subjectType: 'asset',
            subjectId: 'asset-erp-prod',
            organizationId: 'org-a',
        );

        $history = $service->history('plugin.asset-catalog.asset-lifecycle', 'asset', 'asset-erp-prod');

        $this->assertSame('review', $instance->currentState);
        $this->assertCount(1, $history);
        $this->assertSame('submit-review', $history[0]->transitionKey);
        $this->assertSame('draft', $history[0]->fromState);
        $this->assertSame('review', $history[0]->toState);

        $this->get('/core/audit-logs?principal_id=principal-admin')
            ->assertOk()
            ->assertJsonFragment([
                'event_type' => 'plugin.asset-catalog.workflows.transition',
                'outcome' => 'success',
                'organization_id' => 'org-a',
                'target_type' => 'asset',
                'target_id' => 'asset-erp-prod',
            ]);
    }

    public function test_the_workflow_registry_endpoint_lists_registered_workflows(): void
    {
        $this->get('/core/workflows')
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'plugin.asset-catalog.asset-lifecycle',
                'owner' => 'asset-catalog',
                'initial_state' => 'draft',
            ]);
    }

    public function test_the_asset_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Asset Catalog')
            ->assertSee('Assets')
            ->assertSee('ERP Production')
            ->assertSee('Managed Laptop Fleet');
    }
}
