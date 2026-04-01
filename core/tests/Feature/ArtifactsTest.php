<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_artifact_upload_creates_private_artifact_and_core_traceability(): void
    {
        Storage::fake('local');

        $this->post('/plugins/controls/control-access-review/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Q1 access review pack',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('access-review.txt', 'q1 evidence bundle'),
        ])->assertFound();

        $response = $this->get('/core/artifacts?principal_id=principal-admin&organization_id=org-a&subject_type=control&subject_id=control-access-review');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'owner_component' => 'controls-catalog',
                'subject_type' => 'control',
                'subject_id' => 'control-access-review',
                'artifact_type' => 'evidence',
                'label' => 'Q1 access review pack',
                'original_filename' => 'access-review.txt',
            ]);

        $artifactPath = $response->json('artifacts.0.storage_path');

        $this->assertIsString($artifactPath);
        Storage::disk('local')->assertExists($artifactPath);

        $this->get('/core/events?principal_id=principal-admin&name=core.artifacts.created')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'core.artifacts.created',
                'origin_component' => 'core',
                'organization_id' => 'org-a',
            ]);

        $this->get('/core/audit-logs?principal_id=principal-admin&event_type=core.artifacts.created')
            ->assertOk()
            ->assertJsonFragment([
                'event_type' => 'core.artifacts.created',
                'outcome' => 'success',
                'target_type' => 'artifact',
            ]);
    }

    public function test_the_artifacts_endpoint_requires_platform_permission(): void
    {
        $this->get('/core/artifacts?principal_id=principal-org-a&organization_id=org-a')
            ->assertForbidden();
    }

    public function test_the_artifacts_list_command_reports_uploaded_artifacts(): void
    {
        Storage::fake('local');

        $this->post('/plugins/controls/control-access-review/artifacts', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'locale' => 'en',
            'menu' => 'plugin.controls-catalog.catalog',
            'membership_id' => 'membership-org-a-hello',
            'label' => 'Control evidence bundle',
            'artifact_type' => 'evidence',
            'artifact' => UploadedFile::fake()->createWithContent('bundle.txt', 'bundle'),
        ])->assertFound();

        $exitCode = Artisan::call('artifacts:list', [
            '--organization_id' => 'org-a',
            '--subject_type' => 'control',
            '--subject_id' => 'control-access-review',
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Control evidence bundle', Artisan::output());
    }

    public function test_artifact_upload_rejects_active_or_executable_file_types(): void
    {
        Storage::fake('local');

        $this->from('/app?menu=plugin.controls-catalog.catalog&principal_id=principal-org-a&organization_id=org-a')
            ->post('/plugins/controls/control-access-review/artifacts', [
                'principal_id' => 'principal-org-a',
                'organization_id' => 'org-a',
                'locale' => 'en',
                'menu' => 'plugin.controls-catalog.catalog',
                'membership_id' => 'membership-org-a-hello',
                'label' => 'Malicious payload',
                'artifact_type' => 'evidence',
                'artifact' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo "owned";'),
            ])
            ->assertSessionHasErrors('artifact');

        Storage::disk('local')->assertDirectoryEmpty('artifacts');
    }
}
