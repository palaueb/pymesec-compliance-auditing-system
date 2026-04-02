<?php

namespace Tests\Feature;

use Illuminate\Validation\Rules\In;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use Tests\TestCase;

class QuestionnaireEngineTest extends TestCase
{
    public function test_the_questionnaire_engine_exposes_shared_types_statuses_and_sections(): void
    {
        $engine = $this->app->make(QuestionnaireEngineInterface::class);

        $this->assertSame(
            ['yes-no', 'long-text', 'date', 'evidence-list'],
            $engine->responseTypeKeys(),
        );

        $this->assertSame(
            ['draft', 'sent', 'submitted', 'under-review', 'accepted', 'needs-follow-up'],
            $engine->responseStatusKeys(),
        );

        $this->assertSame(
            ['none', 'supporting-document', 'supporting-evidence'],
            $engine->attachmentModeKeys(),
        );

        $sections = $engine->groupItemsBySection([
            [
                'id' => 'item-1',
                'section_title' => 'Access governance',
                'prompt' => 'Is MFA enabled?',
            ],
            [
                'id' => 'item-2',
                'section_title' => '',
                'prompt' => 'Describe the exception path.',
            ],
        ]);

        $this->assertCount(2, $sections);
        $this->assertSame('Access governance', $sections[0]['title']);
        $this->assertSame('General', $sections[1]['title']);
        $this->assertSame('Yes / no', $engine->responseTypeLabel('yes-no'));
        $this->assertSame('Needs follow-up', $engine->responseStatusLabel('needs-follow-up'));
        $this->assertSame('Supporting evidence', $engine->attachmentModeLabel('supporting-evidence'));
    }

    public function test_the_questionnaire_engine_returns_answer_rules_by_response_type(): void
    {
        $engine = $this->app->make(QuestionnaireEngineInterface::class);

        $this->assertSame(['required', 'date'], $engine->answerValidationRules('date'));
        $this->assertSame(['required', 'string', 'max:4000'], $engine->answerValidationRules('long-text'));
        $this->assertInstanceOf(In::class, $engine->answerValidationRules('yes-no')[2]);
    }
}
