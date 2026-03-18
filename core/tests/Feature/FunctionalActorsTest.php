<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FunctionalActorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_functional_actor_endpoint_returns_actors_links_and_assignments(): void
    {
        $this->get('/core/functional-actors?principal_id=principal-admin&subject_principal_id=principal-org-a&organization_id=org-a&domain_object_type=asset&domain_object_id=asset-erp-prod')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'actor-finance-ops',
                'display_name' => 'Finance Operations',
            ])
            ->assertJsonFragment([
                'id' => 'actor-ava-mason',
                'display_name' => 'Ava Mason',
            ])
            ->assertJsonFragment([
                'principal_id' => 'principal-org-a',
                'functional_actor_id' => 'actor-ava-mason',
                'organization_id' => 'org-a',
            ])
            ->assertJsonFragment([
                'functional_actor_id' => 'actor-finance-ops',
                'domain_object_type' => 'asset',
                'domain_object_id' => 'asset-erp-prod',
                'assignment_type' => 'owner',
            ]);
    }

    public function test_the_actor_directory_plugin_route_is_loaded(): void
    {
        $this->get('/plugins/actors?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'actor-finance-ops',
                'display_name' => 'Finance Operations',
            ]);
    }

    public function test_the_actor_directory_screen_renders_inside_the_shell(): void
    {
        $this->get('/admin?menu=core.functional-actors&principal_id=principal-admin&actor_id=actor-ava-mason')
            ->assertOk()
            ->assertSee('Functional Directory')
            ->assertSee('Ava Mason')
            ->assertSee('principal-org-a')
            ->assertSee('Assign responsibility');
    }

    public function test_the_asset_catalog_uses_functional_actor_assignments_for_owner_display(): void
    {
        $this->get('/app?menu=plugin.asset-catalog.root&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk()
            ->assertSee('Finance Operations')
            ->assertSee('Compliance Office')
            ->assertSee('IT Services');
    }

    public function test_the_actor_commands_support_listing_linking_and_assigning(): void
    {
        $this->artisan('actors:list org-a --principal_id=principal-org-a')
            ->expectsOutputToContain('Finance Operations')
            ->expectsOutputToContain('principal-org-a')
            ->assertExitCode(0);

        $linkExitCode = Artisan::call('actors:link', [
            'principalId' => 'principal-admin',
            'actorId' => 'actor-compliance-office',
            'organizationId' => 'org-a',
            '--linked_by' => 'principal-admin',
        ]);

        $this->assertSame(0, $linkExitCode);
        $this->assertStringContainsString('actor-compliance-office', Artisan::output());

        $assignExitCode = Artisan::call('actors:assign', [
            'actorId' => 'actor-ava-mason',
            'domainType' => 'asset',
            'domainId' => 'asset-vault-docs',
            'assignmentType' => 'reviewer',
            'organizationId' => 'org-a',
            '--principal_id' => 'principal-admin',
        ]);

        $this->assertSame(0, $assignExitCode);
        $this->assertStringContainsString('reviewer', Artisan::output());

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.functional-actors.principal-linked')
            ->assertOk()
            ->assertJsonFragment([
                'target_id' => 'actor-compliance-office',
            ]);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.functional-actors.assignment.created')
            ->assertOk()
            ->assertJsonFragment([
                'target_id' => 'asset-vault-docs',
            ]);
    }

    public function test_functional_profiles_can_be_created_linked_and_assigned_from_the_admin_ui(): void
    {
        $this->post('/core/functional-actors', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'display_name' => 'IT Team',
            'kind' => 'team',
            'subject_principal_id' => 'principal-ldap-org-a-dirkkoch',
        ])->assertFound();

        $actorId = (string) DB::table('functional_actors')
            ->where('display_name', 'IT Team')
            ->value('id');

        $this->assertNotSame('', $actorId);

        $this->post('/core/functional-actors/links', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'actor_id' => $actorId,
            'subject_principal_id' => 'principal-ldap-org-a-dirkkoch',
        ])->assertFound();

        $this->post('/core/functional-actors/assignments', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'actor_id' => $actorId,
            'assignment_type' => 'owner',
            'subject_key' => 'asset::asset-erp-prod',
        ])->assertFound();

        $this->get('/admin?menu=core.functional-actors&principal_id=principal-admin&organization_id=org-a&actor_id='.$actorId)
            ->assertOk()
            ->assertSee('IT Team')
            ->assertSee('principal-ldap-org-a-dirkkoch')
            ->assertSee('asset')
            ->assertSee('asset-erp-prod');
    }
}
