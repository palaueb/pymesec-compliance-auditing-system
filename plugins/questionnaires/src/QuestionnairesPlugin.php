<?php

namespace PymeSec\Plugins\Questionnaires;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireStoreInterface;

class QuestionnairesPlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        $context->app()->singleton(QuestionnaireEngineInterface::class, function (): QuestionnaireEngineInterface {
            return new QuestionnaireEngine;
        });

        $context->app()->singleton(QuestionnaireStoreInterface::class, function (): QuestionnaireStoreInterface {
            return new QuestionnaireStore;
        });
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
