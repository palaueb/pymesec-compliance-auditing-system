<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_asset_catalog_screen_renders_inside_the_shell(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Asset Catalog')
            ->assertSee('ERP Production')
            ->assertSee('Add asset')
            ->assertSee('Open');

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-erp-prod&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Edit asset details')
            ->assertSee('Workflow');
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
            'owner_label' => 'Operations Control',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->post('/plugins/assets/asset-supplier-gateway', [
            ...$payload,
            'name' => 'Supplier Access Gateway',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'confidential',
            'scope_id' => 'scope-eu',
            'owner_label' => 'Compliance Office',
            'owner_actor_id' => 'actor-compliance-office',
        ])->assertFound();

        $this->get('/app?menu=plugin.asset-catalog.root&asset_id=asset-supplier-access-gateway&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier Access Gateway')
            ->assertSee('Compliance Office');
    }
}
