<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HttpOperationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_api_requests_are_recorded_in_unified_audit_log(): void
    {
        $this->get('/app?menu=core.dashboard&principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $this->getJson('/api/v1/meta/capabilities?principal_id=principal-org-a&organization_id=org-a&membership_ids[]=membership-org-a-hello')
            ->assertOk();

        $webLog = DB::table('audit_logs')
            ->where('event_type', 'core.http.request')
            ->where('channel', 'web')
            ->where('author_type', 'principal')
            ->where('author_id', 'principal-org-a')
            ->where('status_code', 200)
            ->exists();

        $apiLog = DB::table('audit_logs')
            ->where('event_type', 'core.http.request')
            ->where('channel', 'api')
            ->where('author_type', 'principal')
            ->where('author_id', 'principal-org-a')
            ->where('status_code', 200)
            ->exists();

        $this->assertTrue($webLog, 'Expected WEB request audit log entry.');
        $this->assertTrue($apiLog, 'Expected API request audit log entry.');
    }

    public function test_api_denied_operations_are_recorded_with_denied_outcome(): void
    {
        $this->postJson('/api/v1/assets', [
            'principal_id' => 'principal-org-a',
            'organization_id' => 'org-a',
            'membership_id' => 'membership-org-a-viewer',
            'name' => 'Denied create',
            'type' => 'application',
            'criticality' => 'high',
            'classification' => 'internal',
        ])->assertForbidden();

        $denied = DB::table('audit_logs')
            ->where('event_type', 'core.http.request')
            ->where('channel', 'api')
            ->where('author_type', 'principal')
            ->where('author_id', 'principal-org-a')
            ->where('outcome', 'denied')
            ->where('status_code', 403)
            ->exists();

        $this->assertTrue($denied, 'Expected denied API request audit log entry.');
    }
}
