<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceCatalogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_catalogs_screen_renders_for_platform_admin(): void
    {
        $this->get('/admin?menu=core.reference-data&principal_id=principal-admin&organization_id=org-a&catalog_key=risks.categories')
            ->assertOk()
            ->assertSee('Reference catalogs')
            ->assertSee('Catalogs')
            ->assertSee('Add option');
    }

    public function test_managed_risk_category_can_be_created_and_used(): void
    {
        $this->post('/core/reference-data/entries', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'catalog_key' => 'risks.categories',
            'option_key' => 'vendor-fraud',
            'label' => 'Vendor fraud',
            'description' => 'Fraud and collusion risks in supplier activity.',
            'sort_order' => 140,
            'locale' => 'en',
            'menu' => 'core.reference-data',
        ])->assertFound();

        $this->get('/admin?menu=core.reference-data&principal_id=principal-admin&organization_id=org-a&catalog_key=risks.categories')
            ->assertOk()
            ->assertSee('Vendor fraud');

        $this->post('/plugins/risks', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.risk-management.root',
            'membership_id' => 'membership-org-a-hello',
            'title' => 'Supplier kickback exposure',
            'category' => 'vendor-fraud',
            'inherent_score' => 33,
            'residual_score' => 21,
            'linked_asset_id' => 'asset-erp-prod',
            'linked_control_id' => 'control-access-review',
            'treatment' => 'Add supplier segregation review and whistleblowing checks.',
            'scope_id' => 'scope-eu',
            'owner_actor_id' => 'actor-ava-mason',
        ])->assertFound();

        $this->get('/app?menu=plugin.risk-management.root&risk_id=risk-supplier-kickback-exposure&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Supplier kickback exposure')
            ->assertSee('Vendor fraud');
    }
}
