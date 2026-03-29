<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use Tests\TestCase;

class ObjectAccessAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_access_admin_screen_renders_matrix_for_a_selected_principal(): void
    {
        $this->linkPrincipalToActor('principal-it-owner', 'actor-it-services');

        $this->get('/admin?menu=core.object-access&principal_id=principal-admin&organization_id=org-a&subject_principal_id=principal-it-owner')
            ->assertOk()
            ->assertSee('Object Access Matrix');
    }

    public function test_object_access_assignments_can_be_created_and_removed_from_the_admin_ui(): void
    {
        $this->post('/core/object-access/assignments', [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.object-access',
            'subject_key' => 'asset::asset-erp-prod',
            'actor_id' => 'actor-it-services',
            'assignment_type' => 'reviewer',
        ])->assertFound()->assertSessionHas('status');

        $assignmentId = DB::table('functional_assignments')
            ->where('functional_actor_id', 'actor-it-services')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', 'asset-erp-prod')
            ->where('assignment_type', 'reviewer')
            ->value('id');

        $this->assertIsString($assignmentId);

        $this->assertDatabaseHas('functional_assignments', [
            'id' => $assignmentId,
            'organization_id' => 'org-a',
            'is_active' => true,
        ]);

        $this->post(sprintf('/core/object-access/assignments/%s/deactivate', $assignmentId), [
            'principal_id' => 'principal-admin',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'core.object-access',
            'subject_key' => 'asset::asset-erp-prod',
        ])->assertFound()->assertSessionHas('status');

        $this->assertDatabaseHas('functional_assignments', [
            'id' => $assignmentId,
            'is_active' => false,
        ]);
    }

    private function linkPrincipalToActor(string $principalId, string $actorId): void
    {
        DB::table('identity_local_users')->insert([
            'id' => 'identity-user-'.$principalId,
            'principal_id' => $principalId,
            'organization_id' => 'org-a',
            'username' => str_replace('principal-', '', $principalId),
            'display_name' => 'Scoped IT Owner',
            'email' => $principalId.'@northwind.test',
            'password_hash' => null,
            'password_enabled' => false,
            'magic_link_enabled' => true,
            'job_title' => 'IT owner',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->make(FunctionalActorServiceInterface::class)->linkPrincipal(
            principalId: $principalId,
            actorId: $actorId,
            organizationId: 'org-a',
            linkedByPrincipalId: 'principal-admin',
        );
    }
}
