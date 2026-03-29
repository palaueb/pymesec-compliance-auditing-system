<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_asset_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Asset Catalog')
            ->assertSee('business-managed catalog values')
            ->assertSee('This list stays focused on browse, compare, and open.')
            ->assertSee('ERP Production')
            ->assertSee('Add asset')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-erp-prod&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Asset Detail keeps workflow, accountability, and governed asset changes inside one record workspace.')
            ->assertSee('Accountability')
            ->assertSee('Governance actions')
            ->assertSee('Edit asset details');
    }

    public function test_assets_can_be_created_and_edited_from_the_shell_runtime(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/assets', [
            ...$payload,
            'name' => 'Supplier Gateway',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'restricted',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/assets/asset-supplier-gateway', [
            ...$payload,
            'name' => 'Supplier Access Gateway',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'confidential',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-supplier-access-gateway&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Gateway')
            ->assertSee('Compliance Office');
    }

    public function test_assets_support_multiple_owner_assignments_and_owner_removal(): void
    {
        $payload = [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-hello',
        ];

        $this->post('/plugins/assets/asset-erp-prod', [
            ...$payload,
            'name' => 'ERP Production',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'restricted',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->assertSame(2, DB::table('functional_assignments')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', 'asset-erp-prod')
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->count());

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-erp-prod&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance Operations')
            ->assertSee('Compliance Office')
            ->assertSee('Remove owner');

        $assignmentId = (string) DB::table('functional_assignments')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', 'asset-erp-prod')
            ->where('assignment_type', 'owner')
            ->where('functional_actor_id', 'actor-compliance-office')
            ->value('id');

        $this->post("/plugins/assets/asset-erp-prod/owners/{$assignmentId}/remove", $payload)->assertFound();

        $this->assertFalse((bool) DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->value('is_active'));

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-erp-prod&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance Operations')
            ->assertDontSee('+1 more owner');
    }

    public function test_assets_reject_invalid_governed_values(): void
    {
        $this->post('/plugins/assets', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.asset-catalog.root',
            'membership_id' => 'membership-org-a-hello',
            'name' => 'Unruled Asset',
            'type' => 'whatever',
            'criticality' => 'extreme',
            'classification' => 'top-secret',
        ])->assertSessionHasErrors([
            'type',
            'criticality',
            'classification',
        ]);
    }
}
