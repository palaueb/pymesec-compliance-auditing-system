<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;
use Tests\TestCase;

class QuestionnaireStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_questionnaire_store_supports_brokered_collection_requests(): void
    {
        $store = $this->app->make(QuestionnaireStoreInterface::class);

        $request = $store->issueBrokeredRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            organizationId: 'org-a',
            scopeId: 'scope-eu',
            data: [
                'contact_name' => 'Alicia Brown',
                'contact_email' => 'alicia.brown@northbridge-payroll.test',
                'collection_channel' => 'email',
                'instructions' => 'Collect offline confirmation of the privileged access review cadence.',
                'broker_principal_id' => 'principal-org-a',
                'issued_by_principal_id' => 'principal-org-a',
            ],
        );

        $this->assertSame('Alicia Brown', $request['contact_name']);
        $this->assertSame('queued', $request['collection_status']);
        $this->assertSame('email', $request['collection_channel']);

        $updated = $store->updateBrokeredRequest(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: 'vendor-review-northbridge-payroll-2026',
            requestId: $request['id'],
            data: [
                'collection_status' => 'submitted',
                'broker_notes' => 'Vendor answered by email and the broker copied the answer into the review.',
            ],
        );

        $this->assertNotNull($updated);
        $this->assertSame('submitted', $updated['collection_status']);
        $this->assertSame('Vendor answered by email and the broker copied the answer into the review.', $updated['broker_notes']);
        $this->assertNotSame('', $updated['submitted_at']);
        $this->assertNotEmpty($store->brokeredRequestsForSubject('third-party-risk', 'vendor-review', 'vendor-review-northbridge-payroll-2026'));
    }
}
