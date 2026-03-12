<?php

namespace PymeSec\Core\Permissions\Contracts;

use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\AuthorizationResult;

interface AuthorizationServiceInterface
{
    public function authorize(AuthorizationContext $context): AuthorizationResult;
}
