<?php

use App\Http\Requests\Api\V1\AssessmentCreateRequest;
use App\Http\Requests\Api\V1\AssessmentReviewUpdateRequest;
use App\Http\Requests\Api\V1\AssessmentUpdateRequest;
use App\Http\Requests\Api\V1\AssetCreateRequest;
use App\Http\Requests\Api\V1\AssetUpdateRequest;
use App\Http\Requests\Api\V1\ControlCreateRequest;
use App\Http\Requests\Api\V1\ControlUpdateRequest;
use App\Http\Requests\Api\V1\FindingCreateRequest;
use App\Http\Requests\Api\V1\FindingUpdateRequest;
use App\Http\Requests\Api\V1\RemediationActionCreateRequest;
use App\Http\Requests\Api\V1\RemediationActionUpdateRequest;
use App\Http\Requests\Api\V1\RiskCreateRequest;
use App\Http\Requests\Api\V1\RiskUpdateRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Collaboration\Contracts\CollaborationEngineInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\OpenApi\OpenApiDocumentBuilder;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Questionnaires\Contracts\QuestionnaireEngineInterface;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\AssetCatalog\AssetCatalogRepository;
use PymeSec\Plugins\ContinuityBcm\ContinuityBcmRepository;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;
use PymeSec\Plugins\DataFlowsPrivacy\DataFlowsPrivacyRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;
use PymeSec\Plugins\PolicyExceptions\PolicyExceptionsRepository;
use PymeSec\Plugins\RiskManagement\RiskRepository;
use PymeSec\Plugins\ThirdPartyRisk\ThirdPartyRiskRepository;

$apiPrincipalId = static function (Request $request): ?string {
    $principalId = $request->attributes->get('core.authenticated_principal_id');

    if (is_string($principalId) && $principalId !== '') {
        return $principalId;
    }

    $fallback = $request->input('principal_id', $request->query('principal_id'));

    return is_string($fallback) && $fallback !== '' ? $fallback : null;
};

$apiSuccess = static fn (mixed $data, array $meta = []) => response()->json([
    'data' => $data,
    'meta' => array_merge([
        'request_id' => request()->attributes->get('core.request_id'),
    ], $meta),
]);

$resolveTenancy = static function (Request $request, TenancyServiceInterface $tenancy, ?string $principalId = null) use ($apiPrincipalId) {
    $principal = $principalId ?? $apiPrincipalId($request);
    $organizationId = $request->input('organization_id', $request->query('organization_id'));
    $scopeId = $request->input('scope_id', $request->query('scope_id'));
    $membershipIds = $request->input('membership_ids', $request->query('membership_ids', []));

    if (! is_array($membershipIds)) {
        $membershipIds = [];
    }

    $membershipId = $request->input('membership_id', $request->query('membership_id'));

    if (is_string($membershipId) && $membershipId !== '') {
        $membershipIds[] = $membershipId;
    }

    return $tenancy->resolveContext(
        principalId: is_string($principal) && $principal !== '' ? $principal : null,
        requestedOrganizationId: is_string($organizationId) && $organizationId !== '' ? $organizationId : null,
        requestedScopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        requestedMembershipIds: $membershipIds,
    );
};

$hasOrganizationWideMembership = static function (array $memberships): bool {
    foreach ($memberships as $membership) {
        if (is_array($membership->scopes ?? null) && $membership->scopes === []) {
            return true;
        }
    }

    return false;
};

/**
 * @return array<int, string>
 */
$resolveEffectivePermissionKeys = static function (
    string $principalId,
    ?string $organizationId,
    ?string $scopeId,
    array $requestedMembershipIds,
    AuthorizationServiceInterface $authorization,
    PermissionRegistryInterface $permissions,
    TenancyServiceInterface $tenancy
) use ($hasOrganizationWideMembership): array {
    $memberships = [];

    foreach ($requestedMembershipIds as $membershipId) {
        if (is_string($membershipId) && $membershipId !== '') {
            $memberships[] = $membershipId;
        }
    }

    $context = $tenancy->resolveContext(
        principalId: $principalId,
        requestedOrganizationId: $organizationId,
        requestedScopeId: $scopeId,
        requestedMembershipIds: $memberships,
    );

    $effectiveScopeId = $context->scope?->id;

    if ($context->organization !== null && $context->memberships !== [] && ! $hasOrganizationWideMembership($context->memberships)) {
        if (is_string($scopeId) && $scopeId !== '' && $effectiveScopeId === null) {
            return [];
        }

        if ($effectiveScopeId === null) {
            $effectiveScopeId = $context->scopes[0]->id ?? null;
        }
    }

    $keys = [];

    foreach ($permissions->all() as $permission) {
        $allowed = $authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'api',
            ),
            permission: $permission->key,
            memberships: $context->memberships,
            organizationId: $context->organization?->id,
            scopeId: $effectiveScopeId,
        ))->allowed();

        if ($allowed) {
            $keys[] = $permission->key;
        }
    }

    return $keys;
};

$assetCreateContractRules = AssetCreateRequest::contractRules();
$assetUpdateContractRules = AssetUpdateRequest::contractRules();
$riskCreateContractRules = RiskCreateRequest::contractRules();
$riskUpdateContractRules = RiskUpdateRequest::contractRules();
$controlCreateContractRules = ControlCreateRequest::contractRules();
$controlUpdateContractRules = ControlUpdateRequest::contractRules();
$assessmentCreateContractRules = AssessmentCreateRequest::contractRules();
$assessmentUpdateContractRules = AssessmentUpdateRequest::contractRules();
$findingCreateContractRules = FindingCreateRequest::contractRules();
$findingUpdateContractRules = FindingUpdateRequest::contractRules();
$remediationActionCreateContractRules = RemediationActionCreateRequest::contractRules();
$remediationActionUpdateContractRules = RemediationActionUpdateRequest::contractRules();
$assessmentReviewUpdateContractRules = AssessmentReviewUpdateRequest::contractRules();

$assetRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'type' => ['required', 'string', Rule::in($catalogs->keys('assets.types', $organizationId))],
        'criticality' => ['required', 'string', Rule::in($catalogs->keys('assets.criticality', $organizationId))],
        'classification' => ['required', 'string', Rule::in($catalogs->keys('assets.classification', $organizationId))],
    ];
};

$riskRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'category' => ['required', 'string', Rule::in($catalogs->keys('risks.categories', $organizationId))],
    ];
};

$controlRuntimeRules = static function (array $contractRules, string $organizationId, ControlsCatalogRepository $controls): array {
    $frameworkIds = array_values(array_map(
        static fn (array $framework): string => $framework['id'],
        $controls->frameworks($organizationId),
    ));

    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', 'required_without:framework', Rule::in($frameworkIds)],
        'framework' => ['nullable', 'string', 'max:80', 'required_without:framework_id'],
    ];
};

$assessmentCreateRuntimeRules = static function (
    array $contractRules,
    string $organizationId,
    ?string $scopeId,
    AssessmentsAuditsRepository $assessments,
): array {
    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($assessments->frameworkOptionIds($organizationId, $scopeId))],
        'status' => ['nullable', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
    ];
};

$assessmentUpdateRuntimeRules = static function (
    array $contractRules,
    string $organizationId,
    ?string $scopeId,
    AssessmentsAuditsRepository $assessments,
): array {
    return [
        ...$contractRules,
        'framework_id' => ['nullable', 'string', 'max:64', Rule::in($assessments->frameworkOptionIds($organizationId, $scopeId))],
        'status' => ['required', 'string', Rule::in(AssessmentReferenceData::statusKeys())],
    ];
};

$findingRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'severity' => ['required', 'string', Rule::in($catalogs->keys('findings.severity', $organizationId))],
    ];
};

$remediationActionRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'status' => ['required', 'string', Rule::in($catalogs->keys('findings.remediation_status', $organizationId))],
    ];
};

$assessmentReviewRuntimeRules = static function (array $contractRules, string $organizationId, ReferenceCatalogService $catalogs): array {
    return [
        ...$contractRules,
        'result' => ['required', 'string', Rule::in($catalogs->keys('assessments.review_result', $organizationId))],
    ];
};

Route::prefix('v1')->group(function () use (
    $apiPrincipalId,
    $apiSuccess,
    $resolveTenancy,
    $resolveEffectivePermissionKeys,
    $assetCreateContractRules,
    $assetUpdateContractRules,
    $riskCreateContractRules,
    $riskUpdateContractRules,
    $controlCreateContractRules,
    $controlUpdateContractRules,
    $assessmentCreateContractRules,
    $assessmentUpdateContractRules,
    $findingCreateContractRules,
    $findingUpdateContractRules,
    $remediationActionCreateContractRules,
    $remediationActionUpdateContractRules,
    $assessmentReviewUpdateContractRules,
    $assetRuntimeRules,
    $riskRuntimeRules,
    $controlRuntimeRules,
    $assessmentCreateRuntimeRules,
    $assessmentUpdateRuntimeRules,
    $findingRuntimeRules,
    $remediationActionRuntimeRules,
    $assessmentReviewRuntimeRules,
): void {
    Route::get('/openapi', function (OpenApiDocumentBuilder $openApi) use ($apiSuccess) {
        return $apiSuccess($openApi->build());
    })->defaults('_openapi', [
        'operation_id' => 'coreGetOpenApi',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Get OpenAPI contract for active API routes',
        'responses' => [
            '200' => [
                'description' => 'OpenAPI contract payload',
            ],
        ],
    ]);

    Route::get('/meta/capabilities', function (
        Request $request,
        PermissionRegistryInterface $permissions,
        AuthorizationServiceInterface $authorization,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $allowedPermissions = [];

        foreach ($permissions->all() as $permission) {
            $allowed = $authorization->authorize(new AuthorizationContext(
                principal: new PrincipalReference(
                    id: $principalId,
                    provider: 'api',
                ),
                permission: $permission->key,
                memberships: $context->memberships,
                organizationId: $context->organization?->id,
                scopeId: $context->scope?->id,
            ))->allowed();

            if ($allowed) {
                $allowedPermissions[] = $permission->key;
            }
        }

        $tokenAbilities = $request->attributes->get('core.api_token_abilities');

        if (is_array($tokenAbilities) && $tokenAbilities !== []) {
            $allowedPermissions = array_values(array_intersect(
                $allowedPermissions,
                array_values(array_filter(
                    $tokenAbilities,
                    static fn (mixed $value): bool => is_string($value) && $value !== '',
                )),
            ));
        }

        return $apiSuccess([
            'principal_id' => $principalId,
            'organization_id' => $context->organization?->id,
            'scope_id' => $context->scope?->id,
            'membership_ids' => array_values(array_map(
                static fn ($membership): string => $membership->id,
                $context->memberships,
            )),
            'permissions' => $allowedPermissions,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreGetCapabilities',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Resolve effective capabilities for current principal',
        'responses' => [
            '200' => [
                'description' => 'Capability snapshot resolved for caller context',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
        ],
    ]);

    Route::get('/api-tokens', function (
        Request $request,
        ApiAccessTokenRepository $tokens,
        AuthorizationServiceInterface $authorization,
    ) use ($apiPrincipalId, $apiSuccess) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $ownerPrincipalId = $request->query('owner_principal_id');
        $canManageOthers = $authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $principalId,
                provider: 'api',
            ),
            permission: 'core.roles.manage',
            memberships: [],
            organizationId: null,
            scopeId: null,
        ))->allowed();

        if (! $canManageOthers) {
            if (is_string($ownerPrincipalId) && $ownerPrincipalId !== '' && $ownerPrincipalId !== $principalId) {
                abort(403, 'You can only list your own tokens.');
            }

            $ownerPrincipalId = $principalId;
        }

        $organizationId = $request->query('organization_id');
        $scopeId = $request->query('scope_id');
        $limit = max(1, min(250, (int) $request->integer('limit', 100)));

        return $apiSuccess($tokens->list(
            organizationId: is_string($organizationId) && $organizationId !== '' ? $organizationId : null,
            scopeId: is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            principalId: is_string($ownerPrincipalId) && $ownerPrincipalId !== '' ? $ownerPrincipalId : null,
            limit: $limit,
        ));
    })->defaults('_openapi', [
        'operation_id' => 'coreListApiTokens',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'List API tokens in current governance scope',
        'responses' => [
            '200' => [
                'description' => 'API token list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:core.api-tokens.view');

    Route::post('/api-tokens', function (
        Request $request,
        ApiAccessTokenRepository $tokens,
        AuthorizationServiceInterface $authorization,
        PermissionRegistryInterface $permissions,
        TenancyServiceInterface $tenancy,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess, $resolveEffectivePermissionKeys) {
        $ownerPrincipalRules = ['required', 'string', 'max:120'];

        if (Schema::hasTable('identity_local_users')) {
            $ownerPrincipalRules[] = Rule::exists('identity_local_users', 'principal_id')
                ->where(fn ($query) => $query->where('is_active', true));
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'owner_principal_id' => $ownerPrincipalRules,
            'organization_id' => ['nullable', 'string', 'max:64', 'exists:organizations,id'],
            'scope_id' => ['nullable', 'string', 'max:64', 'exists:scopes,id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['required', 'string', 'max:190'],
        ]);

        $ownerPrincipalId = (string) $validated['owner_principal_id'];
        $organizationId = is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== ''
            ? $validated['organization_id']
            : null;
        $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : null;

        if ($scopeId !== null) {
            $scopeOrganizationId = DB::table('scopes')->where('id', $scopeId)->value('organization_id');
            abort_unless(is_string($scopeOrganizationId) && $scopeOrganizationId !== '', 422, 'Scope organization is invalid.');

            if ($organizationId !== null && $organizationId !== $scopeOrganizationId) {
                throw ValidationException::withMessages([
                    'scope_id' => 'Selected scope does not belong to the selected organization.',
                ]);
            }

            $organizationId = $organizationId ?? $scopeOrganizationId;
        }

        if (Schema::hasTable('identity_local_users') && $organizationId !== null) {
            $ownerOrganizationId = DB::table('identity_local_users')
                ->where('principal_id', $ownerPrincipalId)
                ->value('organization_id');

            if (is_string($ownerOrganizationId) && $ownerOrganizationId !== '' && $ownerOrganizationId !== $organizationId) {
                throw ValidationException::withMessages([
                    'owner_principal_id' => 'Selected person does not belong to the selected organization.',
                ]);
            }
        }

        $actingPrincipalId = $apiPrincipalId($request);
        abort_unless(is_string($actingPrincipalId) && $actingPrincipalId !== '', 401);

        $canIssueForOthers = $authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $actingPrincipalId,
                provider: 'api',
            ),
            permission: 'core.roles.manage',
            memberships: [],
            organizationId: null,
            scopeId: null,
        ))->allowed();

        if (! $canIssueForOthers && $ownerPrincipalId !== $actingPrincipalId) {
            abort(403, 'You can only issue tokens for your own principal.');
        }

        $ownerPermissions = $resolveEffectivePermissionKeys(
            principalId: $ownerPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            requestedMembershipIds: $request->input('membership_ids', []),
            authorization: $authorization,
            permissions: $permissions,
            tenancy: $tenancy,
        );

        if ($ownerPermissions === []) {
            throw ValidationException::withMessages([
                'owner_principal_id' => 'Selected owner has no effective permissions in the selected context.',
            ]);
        }

        $requestedAbilities = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            is_array($validated['abilities'] ?? null) ? $validated['abilities'] : [],
        ), static fn (string $value): bool => $value !== '')));

        $effectiveAbilities = [];

        if ($requestedAbilities !== []) {
            $unknownAbilities = array_values(array_filter(
                $requestedAbilities,
                static fn (string $ability): bool => ! $permissions->has($ability),
            ));

            if ($unknownAbilities !== []) {
                throw ValidationException::withMessages([
                    'abilities' => sprintf('Unknown permission keys: %s', implode(', ', $unknownAbilities)),
                ]);
            }

            $abilitiesOutsideOwner = array_values(array_diff($requestedAbilities, $ownerPermissions));
            if ($abilitiesOutsideOwner !== []) {
                throw ValidationException::withMessages([
                    'abilities' => sprintf(
                        'Requested abilities are not granted to the owner in this context: %s',
                        implode(', ', $abilitiesOutsideOwner),
                    ),
                ]);
            }

            if (! $canIssueForOthers) {
                $issuerPermissions = $resolveEffectivePermissionKeys(
                    principalId: $actingPrincipalId,
                    organizationId: $organizationId,
                    scopeId: $scopeId,
                    requestedMembershipIds: $request->input('membership_ids', []),
                    authorization: $authorization,
                    permissions: $permissions,
                    tenancy: $tenancy,
                );

                $abilitiesOutsideIssuer = array_values(array_diff($requestedAbilities, $issuerPermissions));
                if ($abilitiesOutsideIssuer !== []) {
                    throw ValidationException::withMessages([
                        'abilities' => sprintf(
                            'Requested abilities exceed your own effective permissions: %s',
                            implode(', ', $abilitiesOutsideIssuer),
                        ),
                    ]);
                }
            }

            $effectiveAbilities = $requestedAbilities;
        } else {
            if ($canIssueForOthers) {
                $effectiveAbilities = $ownerPermissions;
            } else {
                $issuerPermissions = $resolveEffectivePermissionKeys(
                    principalId: $actingPrincipalId,
                    organizationId: $organizationId,
                    scopeId: $scopeId,
                    requestedMembershipIds: $request->input('membership_ids', []),
                    authorization: $authorization,
                    permissions: $permissions,
                    tenancy: $tenancy,
                );
                $effectiveAbilities = array_values(array_intersect($ownerPermissions, $issuerPermissions));
            }
        }

        if ($effectiveAbilities === []) {
            throw ValidationException::withMessages([
                'abilities' => 'No effective token abilities are available in this context.',
            ]);
        }

        $expiresInDays = is_numeric($validated['expires_in_days'] ?? null)
            ? (int) $validated['expires_in_days']
            : null;
        $expiresAt = is_int($expiresInDays) ? CarbonImmutable::now()->addDays($expiresInDays) : null;

        $issued = $tokens->issue(
            principalId: $ownerPrincipalId,
            label: (string) $validated['label'],
            organizationId: $organizationId,
            scopeId: $scopeId,
            createdByPrincipalId: $actingPrincipalId,
            expiresAt: $expiresAt,
            abilities: $effectiveAbilities,
        );

        $audit->record(new AuditRecordData(
            eventType: 'core.api-tokens.issued',
            outcome: 'success',
            originComponent: 'core',
            principalId: $actingPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'api-access-token',
            targetId: $issued['id'],
            summary: [
                'owner_principal_id' => $ownerPrincipalId,
                'token_prefix' => $issued['token_prefix'],
                'expires_at' => $issued['expires_at'],
                'abilities' => $effectiveAbilities,
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.api-tokens.issued',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'token_id' => $issued['id'],
                'owner_principal_id' => $ownerPrincipalId,
                'token_prefix' => $issued['token_prefix'],
                'expires_at' => $issued['expires_at'],
            ],
        ));

        return $apiSuccess([
            'id' => $issued['id'],
            'token' => $issued['token'],
            'token_prefix' => $issued['token_prefix'],
            'owner_principal_id' => $ownerPrincipalId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'expires_at' => $issued['expires_at'],
            'abilities' => $effectiveAbilities,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreIssueApiToken',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Issue an API token with least-privilege abilities',
        'responses' => [
            '200' => [
                'description' => 'API token issued',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'label' => ['required', 'string', 'max:160'],
            'owner_principal_id' => ['required', 'string', 'max:120'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['required', 'string', 'max:190'],
        ],
        'lookup_fields' => [
            'owner_principal_id' => '/api/v1/lookups/principals/options',
        ],
    ])->middleware('core.permission:core.api-tokens.manage');

    Route::post('/api-tokens/{tokenId}/rotate', function (
        Request $request,
        string $tokenId,
        ApiAccessTokenRepository $tokens,
        AuthorizationServiceInterface $authorization,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $record = $tokens->find($tokenId);
        abort_if($record === null, 404);

        $actingPrincipalId = $apiPrincipalId($request);
        abort_unless(is_string($actingPrincipalId) && $actingPrincipalId !== '', 401);

        $canManageOthers = $authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $actingPrincipalId,
                provider: 'api',
            ),
            permission: 'core.roles.manage',
            memberships: [],
            organizationId: null,
            scopeId: null,
        ))->allowed();

        if (! $canManageOthers && $record['principal_id'] !== $actingPrincipalId) {
            abort(403, 'You can only rotate your own tokens.');
        }

        $rotated = $tokens->rotate($tokenId);
        if ($rotated === null) {
            throw ValidationException::withMessages([
                'token_id' => 'Token cannot be rotated because it is revoked or expired.',
            ]);
        }

        $requestedOrganizationId = $request->input('organization_id');
        $requestedScopeId = $request->input('scope_id');
        $organizationId = is_string($record['organization_id'] ?? null) && $record['organization_id'] !== ''
            ? $record['organization_id']
            : (is_string($requestedOrganizationId) && $requestedOrganizationId !== '' ? $requestedOrganizationId : null);
        $scopeId = is_string($record['scope_id'] ?? null) && $record['scope_id'] !== ''
            ? $record['scope_id']
            : (is_string($requestedScopeId) && $requestedScopeId !== '' ? $requestedScopeId : null);

        $audit->record(new AuditRecordData(
            eventType: 'core.api-tokens.rotated',
            outcome: 'success',
            originComponent: 'core',
            principalId: $actingPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'api-access-token',
            targetId: $tokenId,
            summary: [
                'owner_principal_id' => $record['principal_id'],
                'token_prefix' => $rotated['token_prefix'],
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.api-tokens.rotated',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'token_id' => $tokenId,
                'owner_principal_id' => $record['principal_id'],
                'token_prefix' => $rotated['token_prefix'],
            ],
        ));

        return $apiSuccess([
            'id' => $rotated['id'],
            'token' => $rotated['token'],
            'token_prefix' => $rotated['token_prefix'],
            'owner_principal_id' => $record['principal_id'],
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'expires_at' => $rotated['expires_at'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreRotateApiToken',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Rotate one API token secret',
        'responses' => [
            '200' => [
                'description' => 'API token rotated',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Token not found',
            ],
            '422' => [
                'description' => 'Token cannot be rotated',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.api-tokens.manage');

    Route::post('/api-tokens/{tokenId}/revoke', function (
        Request $request,
        string $tokenId,
        ApiAccessTokenRepository $tokens,
        AuthorizationServiceInterface $authorization,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $record = $tokens->find($tokenId);
        abort_if($record === null, 404);

        $actingPrincipalId = $apiPrincipalId($request);
        abort_unless(is_string($actingPrincipalId) && $actingPrincipalId !== '', 401);

        $canManageOthers = $authorization->authorize(new AuthorizationContext(
            principal: new PrincipalReference(
                id: $actingPrincipalId,
                provider: 'api',
            ),
            permission: 'core.roles.manage',
            memberships: [],
            organizationId: null,
            scopeId: null,
        ))->allowed();

        if (! $canManageOthers && $record['principal_id'] !== $actingPrincipalId) {
            abort(403, 'You can only revoke your own tokens.');
        }

        $revoked = $tokens->revoke($tokenId);

        $requestedOrganizationId = $request->input('organization_id');
        $requestedScopeId = $request->input('scope_id');
        $organizationId = is_string($record['organization_id'] ?? null) && $record['organization_id'] !== ''
            ? $record['organization_id']
            : (is_string($requestedOrganizationId) && $requestedOrganizationId !== '' ? $requestedOrganizationId : null);
        $scopeId = is_string($record['scope_id'] ?? null) && $record['scope_id'] !== ''
            ? $record['scope_id']
            : (is_string($requestedScopeId) && $requestedScopeId !== '' ? $requestedScopeId : null);

        $audit->record(new AuditRecordData(
            eventType: 'core.api-tokens.revoked',
            outcome: 'success',
            originComponent: 'core',
            principalId: $actingPrincipalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'api-access-token',
            targetId: $tokenId,
            summary: [
                'owner_principal_id' => $record['principal_id'],
                'token_prefix' => $record['token_prefix'],
                'changed' => $revoked,
            ],
            executionOrigin: 'api',
        ));

        if ($revoked) {
            $events->publish(new PublicEvent(
                name: 'core.api-tokens.revoked',
                originComponent: 'core',
                organizationId: $organizationId,
                scopeId: $scopeId,
                payload: [
                    'token_id' => $tokenId,
                    'owner_principal_id' => $record['principal_id'],
                ],
            ));
        }

        return $apiSuccess([
            'id' => $tokenId,
            'owner_principal_id' => $record['principal_id'],
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'revoked' => $revoked,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreRevokeApiToken',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Revoke one API token',
        'responses' => [
            '200' => [
                'description' => 'Token revoked (idempotent)',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Token not found',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.api-tokens.manage');

    Route::get('/lookups/principals/options', function (
        Request $request,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;

        if (! Schema::hasTable('identity_local_users')) {
            return $apiSuccess([], [
                'organization_id' => $organizationId,
            ]);
        }

        $query = DB::table('identity_local_users')
            ->select(['principal_id', 'full_name', 'username', 'organization_id'])
            ->where('is_active', true);

        if (is_string($organizationId) && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        $rows = $query
            ->orderBy('full_name')
            ->orderBy('username')
            ->limit(500)
            ->get()
            ->map(static function ($row): array {
                $labelParts = array_values(array_filter([
                    is_string($row->full_name ?? null) ? trim($row->full_name) : null,
                    is_string($row->username ?? null) ? '@'.trim($row->username) : null,
                ], static fn (?string $value): bool => is_string($value) && $value !== '' && $value !== '@'));

                return [
                    'id' => (string) $row->principal_id,
                    'label' => $labelParts !== [] ? implode(' ', $labelParts) : (string) $row->principal_id,
                    'organization_id' => is_string($row->organization_id ?? null) ? $row->organization_id : null,
                ];
            })
            ->values()
            ->all();

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListPrincipalOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List active principal options for token ownership',
        'responses' => [
            '200' => [
                'description' => 'Principal option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:core.api-tokens.manage');

    Route::get('/lookups/reference-catalogs', function (
        Request $request,
        ReferenceCatalogService $catalogs,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;

        $rows = array_map(function (array $catalog) use ($catalogs, $organizationId): array {
            return [
                ...$catalog,
                'options' => $catalogs->optionRows($catalog['key'], $organizationId),
            ];
        }, $catalogs->manageableCatalogs());

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListCatalogs',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List managed reference catalogs with effective options',
        'responses' => [
            '200' => [
                'description' => 'Catalog list with effective options',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
        ],
    ]);

    Route::get('/lookups/reference-catalogs/{catalogKey}/options', function (
        Request $request,
        string $catalogKey,
        ReferenceCatalogService $catalogs,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $allowedCatalogKeys = array_values(array_map(
            static fn (array $catalog): string => $catalog['key'],
            $catalogs->manageableCatalogs(),
        ));
        abort_unless(in_array($catalogKey, $allowedCatalogKeys, true), 404);

        $context = $resolveTenancy($request, $tenancy, $principalId);

        return $apiSuccess([
            'catalog_key' => $catalogKey,
            'options' => $catalogs->optionRows($catalogKey, $context->organization?->id),
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListCatalogOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List effective options for one reference catalog',
        'responses' => [
            '200' => [
                'description' => 'Catalog options',
            ],
            '404' => [
                'description' => 'Unknown catalog',
            ],
        ],
    ]);

    Route::get('/lookups/actors/options', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);

        $scopeId = $context->scope?->id;
        $rows = array_map(static fn ($actor): array => [
            'id' => $actor->id,
            'label' => $actor->displayName,
            'kind' => $actor->kind,
        ], $actors->actors($organizationId, $scopeId));

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListActorOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List functional actor options for current organization and scope',
        'responses' => [
            '200' => [
                'description' => 'Actor option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.actor-directory.actors.view');

    Route::get('/lookups/frameworks/options', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);

        $scopeId = $context->scope?->id;
        $rows = $assessments->frameworkOptions($organizationId, $scopeId);

        return $apiSuccess($rows, [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListFrameworkOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List framework options for current organization and scope',
        'responses' => [
            '200' => [
                'description' => 'Framework option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/lookups/controls/options', function (
        Request $request,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $rows = $objectAccess->filterRecords(
            records: $controls->all($organizationId, $scopeId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: 'control',
        );

        $options = array_map(static fn (array $control): array => [
            'id' => (string) ($control['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s · %s',
                (string) ($control['name'] ?? ''),
                (string) ($control['framework'] ?? ''),
                (string) ($control['domain'] ?? ''),
            ), ' ·'),
        ], $rows);

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListControlOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List control options visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Control option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/lookups/risks/options', function (
        Request $request,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $rows = $objectAccess->filterRecords(
            records: $risks->all($organizationId, $scopeId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: 'risk',
        );

        $options = array_map(static fn (array $risk): array => [
            'id' => (string) ($risk['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s',
                (string) ($risk['title'] ?? ''),
                (string) ($risk['category'] ?? ''),
            ), ' ·'),
        ], $rows);

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListRiskOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List risk options visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Risk option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::get('/lookups/findings/options', function (
        Request $request,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $rows = $objectAccess->filterRecords(
            records: $findings->allFindings($organizationId, $scopeId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            domainObjectType: 'finding',
        );

        $options = array_map(static fn (array $finding): array => [
            'id' => (string) ($finding['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s',
                (string) ($finding['title'] ?? ''),
                (string) ($finding['severity'] ?? ''),
            ), ' ·'),
        ], $rows);

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListFindingOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List finding options visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Finding option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::get('/lookups/vendor-review-profiles/options', function (
        Request $request,
        ThirdPartyRiskRepository $vendors,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;

        $options = array_map(static fn (array $profile): array => [
            'id' => (string) ($profile['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s',
                (string) ($profile['name'] ?? ''),
                (string) ($profile['tier'] ?? ''),
            ), ' ·'),
        ], $vendors->allReviewProfiles($organizationId, $scopeId));

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListVendorReviewProfileOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List vendor review profile options for current context',
        'responses' => [
            '200' => [
                'description' => 'Vendor review profile option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.view');

    Route::get('/lookups/vendor-questionnaire-templates/options', function (
        Request $request,
        ThirdPartyRiskRepository $vendors,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId !== '', 422);
        $scopeId = $context->scope?->id;
        $profileId = $request->query('profile_id');

        $options = array_map(static fn (array $template): array => [
            'id' => (string) ($template['id'] ?? ''),
            'label' => trim(sprintf(
                '%s · %s',
                (string) ($template['name'] ?? ''),
                (string) ($template['profile_id'] ?? ''),
            ), ' ·'),
        ], $vendors->allQuestionnaireTemplates(
            organizationId: $organizationId,
            scopeId: $scopeId,
            profileId: is_string($profileId) && $profileId !== '' ? $profileId : null,
        ));

        return $apiSuccess(array_values($options), [
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataListVendorQuestionnaireTemplateOptions',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'List vendor questionnaire template options for current context',
        'responses' => [
            '200' => [
                'description' => 'Vendor questionnaire template option list',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.view');

    Route::get('/assets', function (
        Request $request,
        AssetCatalogRepository $assets,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $assets->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'asset',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogListAssets',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'List assets visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Asset list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.view');

    Route::get('/assets/{assetId}', function (
        Request $request,
        string $assetId,
        AssetCatalogRepository $assets,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $asset = $assets->find($assetId);
        abort_if($asset === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $asset['organization_id'] === $organizationId, 404);

        $scopeId = $request->input('scope_id');
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
        ), 403);

        if (is_string($scopeId) && $scopeId !== '' && $asset['scope_id'] !== '' && $asset['scope_id'] !== $scopeId) {
            abort(404);
        }

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogGetAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Get one asset',
        'responses' => [
            '200' => [
                'description' => 'Asset detail',
            ],
            '404' => [
                'description' => 'Asset not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.view');

    Route::post('/assets', function (
        Request $request,
        AssetCatalogRepository $assets,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $assetCreateContractRules, $assetRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($assetRuntimeRules(
            contractRules: $assetCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $asset = $assets->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'asset',
                domainObjectId: $asset['id'],
                assignmentType: 'owner',
                organizationId: $asset['organization_id'],
                scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogCreateAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Create an asset',
        'responses' => [
            '200' => [
                'description' => 'Asset created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssetCreateRequest::class,
        'governed_fields' => [
            'type' => 'assets.types',
            'criticality' => 'assets.criticality',
            'classification' => 'assets.classification',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::patch('/assets/{assetId}', function (
        Request $request,
        string $assetId,
        AssetCatalogRepository $assets,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $assetRuntimeRules, $assetUpdateContractRules) {
        $existing = $assets->find($assetId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($assetRuntimeRules(
            contractRules: $assetUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $asset = $assets->update($assetId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($asset === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'asset',
                domainObjectId: $asset['id'],
                assignmentType: 'owner',
                organizationId: $asset['organization_id'],
                scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'asset')
            ->where('domain_object_id', $asset['id'])
            ->where('organization_id', $asset['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($asset);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogUpdateAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Update one asset',
        'responses' => [
            '200' => [
                'description' => 'Asset updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Asset not found in current context',
            ],
        ],
        'request_form_request' => AssetUpdateRequest::class,
        'governed_fields' => [
            'type' => 'assets.types',
            'criticality' => 'assets.criticality',
            'classification' => 'assets.classification',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::patch('/assets/{assetId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $assetId,
        string $assignmentId,
        AssetCatalogRepository $assets,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $asset = $assets->find($assetId);
        abort_if($asset === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
        ), 403);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
        abort_if($assignment === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogRemoveAssetOwner',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Remove one owner assignment from an asset',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Asset or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::post('/assets/{assetId}/transitions/{transitionKey}', function (
        Request $request,
        string $assetId,
        string $transitionKey,
        WorkflowServiceInterface $workflows,
        AssetCatalogRepository $assets,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $asset = $assets->find($assetId);
        abort_if($asset === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $asset['organization_id'],
            scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
            domainObjectType: 'asset',
            domainObjectId: $asset['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $asset['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.asset-catalog.asset-lifecycle',
            subjectType: 'asset',
            subjectId: $assetId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $asset['scope_id'] !== '' ? $asset['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'asset' => $assets->find($assetId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'assetCatalogTransitionAsset',
        'tags' => ['assets'],
        'tag_descriptions' => [
            'assets' => 'Asset catalog API surface.',
        ],
        'summary' => 'Apply one workflow transition to an asset',
        'responses' => [
            '200' => [
                'description' => 'Transition applied',
            ],
            '404' => [
                'description' => 'Asset not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.asset-catalog.assets.manage');

    Route::get('/risks', function (
        Request $request,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $risks->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'risk',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementListRisks',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'List risks visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Risk list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::get('/risks/{riskId}', function (
        Request $request,
        string $riskId,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $risk = $risks->find($riskId);
        abort_if($risk === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $risk['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
        ), 403);

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementGetRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Get one risk',
        'responses' => [
            '200' => [
                'description' => 'Risk detail',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.view');

    Route::post('/risks', function (
        Request $request,
        RiskRepository $risks,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $riskCreateContractRules, $riskRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($riskRuntimeRules(
            contractRules: $riskCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $risk = $risks->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'risk',
                domainObjectId: $risk['id'],
                assignmentType: 'owner',
                organizationId: $risk['organization_id'],
                scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementCreateRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Create a risk',
        'responses' => [
            '200' => [
                'description' => 'Risk created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RiskCreateRequest::class,
        'governed_fields' => [
            'category' => 'risks.categories',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::patch('/risks/{riskId}', function (
        Request $request,
        string $riskId,
        RiskRepository $risks,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $riskRuntimeRules, $riskUpdateContractRules) {
        $existing = $risks->find($riskId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($riskRuntimeRules(
            contractRules: $riskUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $risk = $risks->update($riskId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($risk === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'risk',
                domainObjectId: $risk['id'],
                assignmentType: 'owner',
                organizationId: $risk['organization_id'],
                scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'risk')
            ->where('domain_object_id', $risk['id'])
            ->where('organization_id', $risk['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($risk);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementUpdateRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Update one risk',
        'responses' => [
            '200' => [
                'description' => 'Risk updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RiskUpdateRequest::class,
        'governed_fields' => [
            'category' => 'risks.categories',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::patch('/risks/{riskId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $riskId,
        string $assignmentId,
        RiskRepository $risks,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $risk = $risks->find($riskId);
        abort_if($risk === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
        ), 403);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
        abort_if($assignment === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementRemoveRiskOwner',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Remove one owner assignment from a risk',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Risk or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::post('/risks/{riskId}/artifacts', function (
        Request $request,
        string $riskId,
        RiskRepository $risks,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $risk = $risks->find($riskId);
        abort_if($risk === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'risk-management',
            subjectType: 'risk',
            subjectId: $riskId,
            artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
            label: (string) ($validated['label'] ?? 'Risk evidence'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            metadata: [
                'plugin' => 'risk-management',
                'category' => $risk['category'],
                'linked_asset_id' => $risk['linked_asset_id'],
                'linked_control_id' => $risk['linked_control_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementAttachRiskArtifact',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Upload one artifact to a risk',
        'responses' => [
            '200' => [
                'description' => 'Artifact uploaded',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::post('/risks/{riskId}/transitions/{transitionKey}', function (
        Request $request,
        string $riskId,
        string $transitionKey,
        WorkflowServiceInterface $workflows,
        RiskRepository $risks,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $risk = $risks->find($riskId);
        abort_if($risk === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $risk['organization_id'],
            scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
            domainObjectType: 'risk',
            domainObjectId: $risk['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $risk['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.risk-management.risk-lifecycle',
            subjectType: 'risk',
            subjectId: $riskId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $risk['scope_id'] !== '' ? $risk['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'risk' => $risks->find($riskId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'riskManagementTransitionRisk',
        'tags' => ['risks'],
        'tag_descriptions' => [
            'risks' => 'Risk register API surface.',
        ],
        'summary' => 'Apply one workflow transition to a risk',
        'responses' => [
            '200' => [
                'description' => 'Transition applied',
            ],
            '404' => [
                'description' => 'Risk not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.risk-management.risks.manage');

    Route::get('/controls', function (
        Request $request,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $controls->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'control',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogListControls',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'List controls visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Control list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::get('/controls/{controlId}', function (
        Request $request,
        string $controlId,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $control = $controls->find($controlId);
        abort_if($control === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $control['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $control['id'],
        ), 403);

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogGetControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Get one control',
        'responses' => [
            '200' => [
                'description' => 'Control detail',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.view');

    Route::post('/controls', function (
        Request $request,
        ControlsCatalogRepository $controls,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $controlCreateContractRules, $controlRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($controlRuntimeRules(
            contractRules: $controlCreateContractRules,
            organizationId: $organizationId,
            controls: $controls,
        ));

        $control = $controls->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'control',
                domainObjectId: $control['id'],
                assignmentType: 'owner',
                organizationId: $control['organization_id'],
                scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogCreateControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Create a control',
        'responses' => [
            '200' => [
                'description' => 'Control created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => ControlCreateRequest::class,
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::patch('/controls/{controlId}', function (
        Request $request,
        string $controlId,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $controlRuntimeRules, $controlUpdateContractRules) {
        $existing = $controls->find($controlId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($controlRuntimeRules(
            contractRules: $controlUpdateContractRules,
            organizationId: $organizationId,
            controls: $controls,
        ));

        $control = $controls->update($controlId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        abort_if($control === null, 404);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'control',
                domainObjectId: $control['id'],
                assignmentType: 'owner',
                organizationId: $control['organization_id'],
                scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'control')
            ->where('domain_object_id', $control['id'])
            ->where('organization_id', $control['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $control['scope_id'] !== '' ? $control['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($control);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogUpdateControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Update one control',
        'responses' => [
            '200' => [
                'description' => 'Control updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => ControlUpdateRequest::class,
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::patch('/controls/{controlId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $controlId,
        string $assignmentId,
        ControlsCatalogRepository $controls,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $control = $controls->find($controlId);
        abort_if($control === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $control['id'],
        ), 403);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'control',
            domainObjectId: $control['id'],
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
        abort_if($assignment === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogRemoveControlOwner',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Remove one owner assignment from a control',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Control or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::post('/controls/{controlId}/artifacts', function (
        Request $request,
        string $controlId,
        ControlsCatalogRepository $controls,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $control = $controls->find($controlId);
        abort_if($control === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $control['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'controls-catalog',
            subjectType: 'control',
            subjectId: $controlId,
            artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
            label: (string) ($validated['label'] ?? 'Evidence attachment'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            metadata: [
                'plugin' => 'controls-catalog',
                'framework' => $control['framework'],
                'control_name' => $control['name'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogAttachControlArtifact',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Upload one artifact to a control',
        'responses' => [
            '200' => [
                'description' => 'Artifact uploaded',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::post('/controls/{controlId}/transitions/{transitionKey}', function (
        Request $request,
        string $controlId,
        string $transitionKey,
        WorkflowServiceInterface $workflows,
        ControlsCatalogRepository $controls,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $control = $controls->find($controlId);
        abort_if($control === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $control['organization_id'],
            scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
            domainObjectType: 'control',
            domainObjectId: $control['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $control['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.controls-catalog.control-lifecycle',
            subjectType: 'control',
            subjectId: $controlId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $control['scope_id'] !== '' ? $control['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'control' => $controls->find($controlId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'controlsCatalogTransitionControl',
        'tags' => ['controls'],
        'tag_descriptions' => [
            'controls' => 'Controls catalog and mapping API surface.',
        ],
        'summary' => 'Apply one workflow transition to a control',
        'responses' => [
            '200' => [
                'description' => 'Transition applied',
            ],
            '404' => [
                'description' => 'Control not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.controls-catalog.controls.manage');

    Route::get('/assessments', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $assessments->all($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'assessment',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsListAssessments',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'List assessments visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Assessment list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::get('/assessments/{assessmentId}', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $assessment['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsGetAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Get one assessment',
        'responses' => [
            '200' => [
                'description' => 'Assessment detail',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::post('/assessments', function (
        Request $request,
        AssessmentsAuditsRepository $assessments,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $assessmentCreateContractRules, $assessmentCreateRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $validated = $request->validate($assessmentCreateRuntimeRules(
            contractRules: $assessmentCreateContractRules,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            assessments: $assessments,
        ));

        $assessment = $assessments->create($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'assessment',
                domainObjectId: $assessment['id'],
                assignmentType: 'owner',
                organizationId: $assessment['organization_id'],
                scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsCreateAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Create an assessment campaign',
        'responses' => [
            '200' => [
                'description' => 'Assessment created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentCreateRequest::class,
        'governed_fields' => [
            'status' => 'assessments.status',
        ],
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'control_ids' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::patch('/assessments/{assessmentId}', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $assessmentUpdateContractRules, $assessmentUpdateRuntimeRules) {
        $existing = $assessments->find($assessmentId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $scopeId = $request->input('scope_id', $existing['scope_id'] !== '' ? $existing['scope_id'] : null);
        $validated = $request->validate($assessmentUpdateRuntimeRules(
            contractRules: $assessmentUpdateContractRules,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            assessments: $assessments,
        ));

        $assessment = $assessments->update($assessmentId, $validated);
        abort_if($assessment === null, 404);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'assessment',
                domainObjectId: $assessment['id'],
                assignmentType: 'owner',
                organizationId: $assessment['organization_id'],
                scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'assessment')
            ->where('domain_object_id', $assessment['id'])
            ->where('organization_id', $assessment['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($assessment);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsUpdateAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Update one assessment campaign',
        'responses' => [
            '200' => [
                'description' => 'Assessment updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentUpdateRequest::class,
        'governed_fields' => [
            'status' => 'assessments.status',
        ],
        'lookup_fields' => [
            'framework_id' => '/api/v1/lookups/frameworks/options',
            'control_ids' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::get('/assessments/{assessmentId}/reviews', function (
        Request $request,
        string $assessmentId,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $assessment['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        return $apiSuccess($assessments->reviews($assessmentId));
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsListAssessmentReviews',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'List control reviews for one assessment',
        'responses' => [
            '200' => [
                'description' => 'Assessment review list',
            ],
            '404' => [
                'description' => 'Assessment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.view');

    Route::patch('/assessments/{assessmentId}/reviews/{controlId}', function (
        Request $request,
        string $assessmentId,
        string $controlId,
        AssessmentsAuditsRepository $assessments,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess, $assessmentReviewUpdateContractRules, $assessmentReviewRuntimeRules) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        $organizationId = (string) $request->input('organization_id', $assessment['organization_id']);
        abort_unless($organizationId === $assessment['organization_id'], 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $validated = $request->validate($assessmentReviewRuntimeRules(
            contractRules: $assessmentReviewUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $review = $assessments->upsertReview(
            assessmentId: $assessmentId,
            controlId: $controlId,
            data: $validated,
            principalId: (string) $request->input('principal_id'),
        );
        abort_if($review === null, 404);

        return $apiSuccess($review);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsUpdateAssessmentReview',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Update one assessment control review',
        'responses' => [
            '200' => [
                'description' => 'Assessment review updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment or control review not found',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => AssessmentReviewUpdateRequest::class,
        'governed_fields' => [
            'result' => 'assessments.review_result',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::patch('/assessments/{assessmentId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $assessmentId,
        string $assignmentId,
        AssessmentsAuditsRepository $assessments,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'assessment')
            ->where('domain_object_id', $assessment['id'])
            ->where('organization_id', $assessment['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $apiPrincipalId($request),
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'assessment_id' => $assessment['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsRemoveAssessmentOwner',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Remove one assessment owner assignment',
        'responses' => [
            '200' => [
                'description' => 'Assessment owner assignment removed',
            ],
            '404' => [
                'description' => 'Assessment or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::post('/assessments/{assessmentId}/reviews/{controlId}/artifacts', function (
        Request $request,
        string $assessmentId,
        string $controlId,
        AssessmentsAuditsRepository $assessments,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $review = $assessments->review($assessmentId, $controlId);
        abort_if($review === null, 404);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
        ]);

        $principalId = $apiPrincipalId($request);
        $membershipId = $request->input('membership_id');

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'assessments-audits',
            subjectType: 'assessment-review',
            subjectId: (string) $review['id'],
            artifactType: (string) ($validated['artifact_type'] ?? 'workpaper'),
            label: (string) ($validated['label'] ?? 'Assessment workpaper'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            metadata: [
                'plugin' => 'assessments-audits',
                'assessment_id' => $assessmentId,
                'control_id' => $controlId,
                'result' => $review['result'] ?? null,
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsAttachAssessmentReviewArtifact',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Attach one artifact to an assessment review',
        'responses' => [
            '200' => [
                'description' => 'Assessment review artifact attached',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment or review not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'principal_id' => ['type' => 'string'],
                            'organization_id' => ['type' => 'string'],
                            'scope_id' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::post('/assessments/{assessmentId}/reviews/{controlId}/findings', function (
        Request $request,
        string $assessmentId,
        string $controlId,
        AssessmentsAuditsRepository $assessments,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $review = $assessments->review($assessmentId, $controlId);
        abort_if($review === null, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'severity' => ['required', 'string', Rule::in($catalogs->keys('findings.severity', $assessment['organization_id']))],
            'description' => ['required', 'string', 'max:5000'],
            'due_on' => ['nullable', 'date'],
        ]);

        $controlScopeId = DB::table('controls')
            ->where('id', $controlId)
            ->where('organization_id', $assessment['organization_id'])
            ->value('scope_id');

        $findingScopeId = is_string($controlScopeId) && $controlScopeId !== ''
            ? $controlScopeId
            : ($assessment['scope_id'] !== '' ? $assessment['scope_id'] : null);

        $finding = $findings->createFinding([
            'organization_id' => $assessment['organization_id'],
            'scope_id' => $findingScopeId,
            'title' => (string) $validated['title'],
            'severity' => (string) $validated['severity'],
            'description' => (string) $validated['description'],
            'linked_control_id' => $controlId,
            'linked_risk_id' => null,
            'due_on' => is_string($validated['due_on'] ?? null) ? $validated['due_on'] : null,
        ]);

        $assessments->linkFinding($assessmentId, $controlId, $finding['id']);

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsCreateReviewFinding',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Create and link one finding from an assessment review',
        'responses' => [
            '200' => [
                'description' => 'Finding created and linked to assessment review',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment or review not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'severity' => ['required', 'string'],
            'description' => ['required', 'string', 'max:5000'],
            'due_on' => ['nullable', 'date'],
        ],
        'governed_fields' => [
            'severity' => 'findings.severity',
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::post('/assessments/{assessmentId}/transitions/{transitionKey}', function (
        Request $request,
        string $assessmentId,
        string $transitionKey,
        AssessmentsAuditsRepository $assessments,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $assessment = $assessments->find($assessmentId);
        abort_if($assessment === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $assessment['organization_id'],
            scopeId: $assessment['scope_id'] !== '' ? $assessment['scope_id'] : null,
            domainObjectType: 'assessment',
            domainObjectId: $assessment['id'],
        ), 403);

        $validated = $request->validate([
            'signoff_notes' => ['nullable', 'string', 'max:5000'],
            'signed_off_on' => ['nullable', 'date'],
            'closure_summary' => ['nullable', 'string', 'max:5000'],
            'closed_on' => ['nullable', 'date'],
        ]);

        $principalId = $apiPrincipalId($request) ?? 'principal-org-a';

        $updated = match ($transitionKey) {
            'activate' => $assessments->update($assessmentId, [...$assessment, 'status' => 'active']),
            'sign-off' => $assessments->signOff(
                $assessmentId,
                $principalId,
                is_string($validated['signoff_notes'] ?? null) ? $validated['signoff_notes'] : null,
                is_string($validated['signed_off_on'] ?? null) ? $validated['signed_off_on'] : null,
            ),
            'close' => $assessments->close(
                $assessmentId,
                $principalId,
                is_string($validated['closure_summary'] ?? null) ? $validated['closure_summary'] : null,
                is_string($validated['closed_on'] ?? null) ? $validated['closed_on'] : null,
            ),
            'reopen' => $assessments->reopen($assessmentId),
            default => null,
        };
        abort_if($updated === null, 404);

        return $apiSuccess([
            'assessment_id' => $updated['id'],
            'status' => $updated['status'] ?? null,
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'assessmentsAuditsTransitionAssessment',
        'tags' => ['assessments'],
        'tag_descriptions' => [
            'assessments' => 'Assessment campaigns and review API surface.',
        ],
        'summary' => 'Transition one assessment campaign lifecycle state',
        'responses' => [
            '200' => [
                'description' => 'Assessment transitioned',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Assessment or transition not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'signoff_notes' => ['nullable', 'string', 'max:5000'],
            'signed_off_on' => ['nullable', 'date'],
            'closure_summary' => ['nullable', 'string', 'max:5000'],
            'closed_on' => ['nullable', 'date'],
        ],
    ])->middleware('core.permission:plugin.assessments-audits.assessments.manage');

    Route::get('/findings', function (
        Request $request,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');

        $rows = $objectAccess->filterRecords(
            records: $findings->allFindings($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $apiPrincipalId($request),
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'finding',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationListFindings',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'List findings visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Findings list',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::get('/findings/{findingId}', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $finding['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationGetFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Get one finding',
        'responses' => [
            '200' => [
                'description' => 'Finding detail',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::post('/findings', function (
        Request $request,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        FunctionalActorServiceInterface $actors,
    ) use ($apiSuccess, $findingCreateContractRules, $findingRuntimeRules) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate($findingRuntimeRules(
            contractRules: $findingCreateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $finding = $findings->createFinding($validated);
        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'finding',
                domainObjectId: $finding['id'],
                assignmentType: 'owner',
                organizationId: $finding['organization_id'],
                scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationCreateFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Create a finding',
        'responses' => [
            '200' => [
                'description' => 'Finding created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => FindingCreateRequest::class,
        'governed_fields' => [
            'severity' => 'findings.severity',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::patch('/findings/{findingId}', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $findingUpdateContractRules, $findingRuntimeRules) {
        $existing = $findings->findFinding($findingId);
        abort_if($existing === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate($findingRuntimeRules(
            contractRules: $findingUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $finding = $findings->updateFinding($findingId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($finding === null, 404);

        $principalId = (string) $request->input('principal_id');

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'finding',
                domainObjectId: $finding['id'],
                assignmentType: 'owner',
                organizationId: $finding['organization_id'],
                scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'finding')
            ->where('domain_object_id', $finding['id'])
            ->where('organization_id', $finding['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($finding);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationUpdateFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Update one finding',
        'responses' => [
            '200' => [
                'description' => 'Finding updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => FindingUpdateRequest::class,
        'governed_fields' => [
            'severity' => 'findings.severity',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::patch('/findings/{findingId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $findingId,
        string $assignmentId,
        FindingsRemediationRepository $findings,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
        abort_if($assignment === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationRemoveFindingOwner',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Remove one owner assignment from a finding',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Finding or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::post('/findings/{findingId}/artifacts', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);
        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        $membershipId = $validated['membership_id'] ?? $request->input('membership_id');

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'findings-remediation',
            subjectType: 'finding',
            subjectId: $findingId,
            artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
            label: (string) ($validated['label'] ?? 'Finding evidence'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            metadata: [
                'plugin' => 'findings-remediation',
                'severity' => $finding['severity'],
                'linked_control_id' => $finding['linked_control_id'],
                'linked_risk_id' => $finding['linked_risk_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationAttachFindingArtifact',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Upload one artifact to a finding',
        'responses' => [
            '200' => [
                'description' => 'Artifact uploaded',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::post('/findings/{findingId}/transitions/{transitionKey}', function (
        Request $request,
        string $findingId,
        string $transitionKey,
        WorkflowServiceInterface $workflows,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $finding['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.findings-remediation.finding-lifecycle',
            subjectType: 'finding',
            subjectId: $findingId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'finding' => $findings->findFinding($findingId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationTransitionFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Apply one workflow transition to a finding',
        'responses' => [
            '200' => [
                'description' => 'Transition applied',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::get('/findings/{findingId}/actions', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $finding['organization_id'] === $organizationId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $rows = $objectAccess->filterRecords(
            records: $findings->actionsForFinding($findingId),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'remediation-action',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationListActionsForFinding',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'List remediation actions for one finding',
        'responses' => [
            '200' => [
                'description' => 'Remediation action list',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::post('/findings/{findingId}/actions', function (
        Request $request,
        string $findingId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $remediationActionCreateContractRules, $remediationActionRuntimeRules) {
        $finding = $findings->findFinding($findingId);
        abort_if($finding === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ), 403);

        $validated = $request->validate($remediationActionRuntimeRules(
            contractRules: $remediationActionCreateContractRules,
            organizationId: $finding['organization_id'],
            catalogs: $catalogs,
        ));

        $action = $findings->createAction($findingId, [
            ...$validated,
            'organization_id' => $finding['organization_id'],
            'scope_id' => $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
        ]);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'remediation-action',
                domainObjectId: $action['id'],
                assignmentType: 'owner',
                organizationId: $action['organization_id'],
                scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($action);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationCreateAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Create one remediation action for a finding',
        'responses' => [
            '200' => [
                'description' => 'Remediation action created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Finding not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RemediationActionCreateRequest::class,
        'governed_fields' => [
            'status' => 'findings.remediation_status',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::get('/remediation-actions/{actionId}', function (
        Request $request,
        string $actionId,
        FindingsRemediationRepository $findings,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $action = $findings->findAction($actionId);
        abort_if($action === null, 404);

        $finding = $findings->findFinding((string) $action['finding_id']);
        abort_if($finding === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $action['organization_id'] === $organizationId, 404);

        $principalId = $apiPrincipalId($request);
        $canAccess = $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
        );
        abort_unless($canAccess, 403);

        return $apiSuccess($action);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationGetAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Get one remediation action',
        'responses' => [
            '200' => [
                'description' => 'Remediation action detail',
            ],
            '404' => [
                'description' => 'Action not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.view');

    Route::patch('/remediation-actions/{actionId}', function (
        Request $request,
        string $actionId,
        FindingsRemediationRepository $findings,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess, $remediationActionUpdateContractRules, $remediationActionRuntimeRules) {
        $action = $findings->findAction($actionId);
        abort_if($action === null, 404);

        $finding = $findings->findFinding((string) $action['finding_id']);
        abort_if($finding === null, 404);

        $canAccess = $objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
        );
        abort_unless($canAccess, 403);

        $organizationId = (string) $request->input('organization_id', $action['organization_id']);
        abort_unless($organizationId === $action['organization_id'], 404);

        $validated = $request->validate($remediationActionRuntimeRules(
            contractRules: $remediationActionUpdateContractRules,
            organizationId: $organizationId,
            catalogs: $catalogs,
        ));

        $updated = $findings->updateAction($actionId, [
            ...$validated,
            'organization_id' => $action['organization_id'],
            'scope_id' => $action['scope_id'] !== '' ? $action['scope_id'] : null,
        ]);
        abort_if($updated === null, 404);

        $principalId = (string) $request->input('principal_id');
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'remediation-action',
                domainObjectId: $updated['id'],
                assignmentType: 'owner',
                organizationId: $updated['organization_id'],
                scopeId: $updated['scope_id'] !== '' ? $updated['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'remediation-action')
            ->where('domain_object_id', $updated['id'])
            ->where('organization_id', $updated['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $updated['scope_id'] !== '' ? $updated['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($updated);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationUpdateAction',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Update one remediation action',
        'responses' => [
            '200' => [
                'description' => 'Remediation action updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Action not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_form_request' => RemediationActionUpdateRequest::class,
        'governed_fields' => [
            'status' => 'findings.remediation_status',
        ],
        'lookup_fields' => [
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::patch('/findings/actions/{actionId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $actionId,
        string $assignmentId,
        FindingsRemediationRepository $findings,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $action = $findings->findAction($actionId);
        abort_if($action === null, 404);
        $finding = $findings->findFinding((string) $action['finding_id']);
        abort_if($finding === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $canAccess = $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $finding['organization_id'],
            scopeId: $finding['scope_id'] !== '' ? $finding['scope_id'] : null,
            domainObjectType: 'finding',
            domainObjectId: $finding['id'],
        ) || $objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
        );
        abort_unless($canAccess, 403);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'remediation-action',
            domainObjectId: $action['id'],
            organizationId: $action['organization_id'],
            scopeId: $action['scope_id'] !== '' ? $action['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'findingsRemediationRemoveActionOwner',
        'tags' => ['findings'],
        'tag_descriptions' => [
            'findings' => 'Findings and remediation API surface.',
        ],
        'summary' => 'Remove one owner assignment from a remediation action',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Action or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.findings-remediation.findings.manage');

    Route::get('/policies', function (
        Request $request,
        PolicyExceptionsRepository $policies,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $policies->allPolicies($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'policy',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsListPolicies',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'List policies visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Policy list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.view');

    Route::get('/policies/{policyId}', function (
        Request $request,
        string $policyId,
        PolicyExceptionsRepository $policies,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $policy = $policies->findPolicy($policyId);
        abort_if($policy === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $policy['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
        ), 403);

        return $apiSuccess($policy);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsGetPolicy',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Get one policy',
        'responses' => [
            '200' => [
                'description' => 'Policy detail',
            ],
            '404' => [
                'description' => 'Policy not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.view');

    Route::post('/policies', function (
        Request $request,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
    ) use ($apiSuccess, $apiPrincipalId) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'area' => ['required', 'string', Rule::in($catalogs->keys('policies.areas', $organizationId))],
            'version_label' => ['required', 'string', 'max:40'],
            'statement' => ['required', 'string', 'max:2000'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $policy = $policies->createPolicy([
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'policy',
                domainObjectId: $policy['id'],
                assignmentType: 'owner',
                organizationId: $policy['organization_id'],
                scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($policy);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsCreatePolicy',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Create one policy',
        'responses' => [
            '200' => [
                'description' => 'Policy created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:140'],
            'area' => ['required', 'string'],
            'version_label' => ['required', 'string', 'max:40'],
            'statement' => ['required', 'string', 'max:2000'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'area' => 'policies.areas',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::patch('/policies/{policyId}', function (
        Request $request,
        string $policyId,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $policies->findPolicy($policyId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'area' => ['required', 'string', Rule::in($catalogs->keys('policies.areas', $organizationId))],
            'version_label' => ['required', 'string', 'max:40'],
            'statement' => ['required', 'string', 'max:2000'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $policy = $policies->updatePolicy($policyId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($policy === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'policy',
                domainObjectId: $policy['id'],
                assignmentType: 'owner',
                organizationId: $policy['organization_id'],
                scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', $policy['id'])
            ->where('organization_id', $policy['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($policy);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsUpdatePolicy',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Update one policy',
        'responses' => [
            '200' => [
                'description' => 'Policy updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Policy not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:140'],
            'area' => ['required', 'string'],
            'version_label' => ['required', 'string', 'max:40'],
            'statement' => ['required', 'string', 'max:2000'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'area' => 'policies.areas',
        ],
        'lookup_fields' => [
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::patch('/policies/{policyId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $policyId,
        string $assignmentId,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $policy = $policies->findPolicy($policyId);
        abort_if($policy === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'policy')
            ->where('domain_object_id', $policy['id'])
            ->where('organization_id', $policy['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'policy_id' => $policy['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsRemovePolicyOwner',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Remove one owner assignment from a policy',
        'responses' => [
            '200' => [
                'description' => 'Policy owner assignment removed',
            ],
            '404' => [
                'description' => 'Policy or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::post('/policies/{policyId}/artifacts', function (
        Request $request,
        string $policyId,
        PolicyExceptionsRepository $policies,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $policy = $policies->findPolicy($policyId);
        abort_if($policy === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'policy-exceptions',
            subjectType: 'policy',
            subjectId: $policyId,
            artifactType: (string) ($validated['artifact_type'] ?? 'document'),
            label: (string) ($validated['label'] ?? 'Policy document'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            metadata: [
                'plugin' => 'policy-exceptions',
                'area' => $policy['area'],
                'version_label' => $policy['version_label'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsAttachPolicyArtifact',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Upload one artifact to a policy',
        'responses' => [
            '200' => [
                'description' => 'Policy artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Policy not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::post('/policies/{policyId}/transitions/{transitionKey}', function (
        Request $request,
        string $policyId,
        string $transitionKey,
        PolicyExceptionsRepository $policies,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $policy = $policies->findPolicy($policyId);
        abort_if($policy === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $policy['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.policy-exceptions.policy-lifecycle',
            subjectType: 'policy',
            subjectId: $policyId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'policy' => $policies->findPolicy($policyId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsTransitionPolicy',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Apply one workflow transition to a policy',
        'responses' => [
            '200' => [
                'description' => 'Policy transitioned',
            ],
            '404' => [
                'description' => 'Policy not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::get('/policies/exceptions', function (
        Request $request,
        PolicyExceptionsRepository $policies,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $policies->exceptions($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'policy-exception',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsListExceptions',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'List policy exceptions visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Policy exception list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.view');

    Route::get('/policies/exceptions/{exceptionId}', function (
        Request $request,
        string $exceptionId,
        PolicyExceptionsRepository $policies,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $exception = $policies->findException($exceptionId);
        abort_if($exception === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $exception['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
        ), 403);

        return $apiSuccess($exception);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsGetException',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Get one policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception detail',
            ],
            '404' => [
                'description' => 'Policy exception not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.view');

    Route::post('/policies/{policyId}/exceptions', function (
        Request $request,
        string $policyId,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $policy = $policies->findPolicy($policyId);
        abort_if($policy === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $policy['organization_id'],
            scopeId: $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
            domainObjectType: 'policy',
            domainObjectId: $policy['id'],
        ), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'rationale' => ['required', 'string', 'max:2000'],
            'compensating_control' => ['nullable', 'string', 'max:1000'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $exception = $policies->createException($policyId, [
            ...$validated,
            'organization_id' => $policy['organization_id'],
            'scope_id' => $policy['scope_id'] !== '' ? $policy['scope_id'] : null,
        ]);

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'policy-exception',
                domainObjectId: $exception['id'],
                assignmentType: 'owner',
                organizationId: $exception['organization_id'],
                scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: $principalId,
            );
        }

        return $apiSuccess($exception);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsCreateException',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Create one policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Policy not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:140'],
            'rationale' => ['required', 'string', 'max:2000'],
            'compensating_control' => ['nullable', 'string', 'max:1000'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::patch('/policies/exceptions/{exceptionId}', function (
        Request $request,
        string $exceptionId,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $policies->findException($exceptionId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'policy-exception',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'rationale' => ['required', 'string', 'max:2000'],
            'compensating_control' => ['nullable', 'string', 'max:1000'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $exception = $policies->updateException($exceptionId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($exception === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'policy-exception',
                domainObjectId: $exception['id'],
                assignmentType: 'owner',
                organizationId: $exception['organization_id'],
                scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', $exception['id'])
            ->where('organization_id', $exception['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($exception);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsUpdateException',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Update one policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Policy exception not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:140'],
            'rationale' => ['required', 'string', 'max:2000'],
            'compensating_control' => ['nullable', 'string', 'max:1000'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::patch('/policies/exceptions/{exceptionId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $exceptionId,
        string $assignmentId,
        PolicyExceptionsRepository $policies,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $exception = $policies->findException($exceptionId);
        abort_if($exception === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'policy-exception')
            ->where('domain_object_id', $exception['id'])
            ->where('organization_id', $exception['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'exception_id' => $exception['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsRemoveExceptionOwner',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Remove one owner assignment from a policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception owner assignment removed',
            ],
            '404' => [
                'description' => 'Policy exception or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::post('/policies/exceptions/{exceptionId}/artifacts', function (
        Request $request,
        string $exceptionId,
        PolicyExceptionsRepository $policies,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $exception = $policies->findException($exceptionId);
        abort_if($exception === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'policy-exceptions',
            subjectType: 'policy-exception',
            subjectId: $exceptionId,
            artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
            label: (string) ($validated['label'] ?? 'Exception evidence'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            metadata: [
                'plugin' => 'policy-exceptions',
                'policy_id' => $exception['policy_id'],
                'linked_finding_id' => $exception['linked_finding_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsAttachExceptionArtifact',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Upload one artifact to a policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Policy exception not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::post('/policies/exceptions/{exceptionId}/transitions/{transitionKey}', function (
        Request $request,
        string $exceptionId,
        string $transitionKey,
        PolicyExceptionsRepository $policies,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $exception = $policies->findException($exceptionId);
        abort_if($exception === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $exception['organization_id'],
            scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
            domainObjectType: 'policy-exception',
            domainObjectId: $exception['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $exception['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.policy-exceptions.exception-lifecycle',
            subjectType: 'policy-exception',
            subjectId: $exceptionId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $exception['scope_id'] !== '' ? $exception['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'exception' => $policies->findException($exceptionId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'policyExceptionsTransitionException',
        'tags' => ['policies'],
        'tag_descriptions' => [
            'policies' => 'Policy and exception API surface.',
        ],
        'summary' => 'Apply one workflow transition to a policy exception',
        'responses' => [
            '200' => [
                'description' => 'Policy exception transitioned',
            ],
            '404' => [
                'description' => 'Policy exception not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.policy-exceptions.policies.manage');

    Route::get('/privacy/data-flows', function (
        Request $request,
        DataFlowsPrivacyRepository $privacy,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $privacy->allDataFlows($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'privacy-data-flow',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyListDataFlows',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'List privacy data flows visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.view');

    Route::get('/privacy/data-flows/{flowId}', function (
        Request $request,
        string $flowId,
        DataFlowsPrivacyRepository $privacy,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $flow = $privacy->findDataFlow($flowId);
        abort_if($flow === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $flow['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
        ), 403);

        return $apiSuccess($flow);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyGetDataFlow',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Get one privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow detail',
            ],
            '404' => [
                'description' => 'Privacy data flow not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.view');

    Route::post('/privacy/data-flows', function (
        Request $request,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
    ) use ($apiSuccess, $apiPrincipalId) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'source' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'data_category_summary' => ['required', 'string', 'max:200'],
            'transfer_type' => ['required', 'string', Rule::in($catalogs->keys('privacy.transfer_type', $organizationId))],
            'review_due_on' => ['nullable', 'date'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $flow = $privacy->createDataFlow([
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'privacy-data-flow',
                domainObjectId: $flow['id'],
                assignmentType: 'owner',
                organizationId: $flow['organization_id'],
                scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($flow);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyCreateDataFlow',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Create one privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'source' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'data_category_summary' => ['required', 'string', 'max:200'],
            'transfer_type' => ['required', 'string'],
            'review_due_on' => ['nullable', 'date'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'transfer_type' => 'privacy.transfer_type',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::patch('/privacy/data-flows/{flowId}', function (
        Request $request,
        string $flowId,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $privacy->findDataFlow($flowId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'source' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'data_category_summary' => ['required', 'string', 'max:200'],
            'transfer_type' => ['required', 'string', Rule::in($catalogs->keys('privacy.transfer_type', $organizationId))],
            'review_due_on' => ['nullable', 'date'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $flow = $privacy->updateDataFlow($flowId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($flow === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'privacy-data-flow',
                domainObjectId: $flow['id'],
                assignmentType: 'owner',
                organizationId: $flow['organization_id'],
                scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'privacy-data-flow')
            ->where('domain_object_id', $flow['id'])
            ->where('organization_id', $flow['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($flow);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyUpdateDataFlow',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Update one privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Privacy data flow not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'source' => ['required', 'string', 'max:160'],
            'destination' => ['required', 'string', 'max:160'],
            'data_category_summary' => ['required', 'string', 'max:200'],
            'transfer_type' => ['required', 'string'],
            'review_due_on' => ['nullable', 'date'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'transfer_type' => 'privacy.transfer_type',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::patch('/privacy/data-flows/{flowId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $flowId,
        string $assignmentId,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $flow = $privacy->findDataFlow($flowId);
        abort_if($flow === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'privacy-data-flow')
            ->where('domain_object_id', $flow['id'])
            ->where('organization_id', $flow['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'data_flow_id' => $flow['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyRemoveDataFlowOwner',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Remove one owner assignment from a privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow owner assignment removed',
            ],
            '404' => [
                'description' => 'Privacy data flow or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::post('/privacy/data-flows/{flowId}/artifacts', function (
        Request $request,
        string $flowId,
        DataFlowsPrivacyRepository $privacy,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $flow = $privacy->findDataFlow($flowId);
        abort_if($flow === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'data-flows-privacy',
            subjectType: 'privacy-data-flow',
            subjectId: $flowId,
            artifactType: (string) ($validated['artifact_type'] ?? 'record'),
            label: (string) ($validated['label'] ?? 'Privacy record'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            metadata: [
                'plugin' => 'data-flows-privacy',
                'transfer_type' => $flow['transfer_type'],
                'linked_asset_id' => $flow['linked_asset_id'],
                'linked_risk_id' => $flow['linked_risk_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyAttachDataFlowArtifact',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Upload one artifact to a privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Privacy data flow not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::post('/privacy/data-flows/{flowId}/transitions/{transitionKey}', function (
        Request $request,
        string $flowId,
        string $transitionKey,
        DataFlowsPrivacyRepository $privacy,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $flow = $privacy->findDataFlow($flowId);
        abort_if($flow === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $flow['organization_id'],
            scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
            domainObjectType: 'privacy-data-flow',
            domainObjectId: $flow['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $flow['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.data-flows-privacy.data-flow-lifecycle',
            subjectType: 'privacy-data-flow',
            subjectId: $flowId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $flow['scope_id'] !== '' ? $flow['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'data_flow' => $privacy->findDataFlow($flowId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyTransitionDataFlow',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Apply one workflow transition to a privacy data flow',
        'responses' => [
            '200' => [
                'description' => 'Privacy data flow transitioned',
            ],
            '404' => [
                'description' => 'Privacy data flow not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::get('/privacy/activities', function (
        Request $request,
        DataFlowsPrivacyRepository $privacy,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $privacy->allProcessingActivities($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'privacy-processing-activity',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyListProcessingActivities',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'List processing activities visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Processing activity list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.view');

    Route::get('/privacy/activities/{activityId}', function (
        Request $request,
        string $activityId,
        DataFlowsPrivacyRepository $privacy,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $activity = $privacy->findProcessingActivity($activityId);
        abort_if($activity === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $activity['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
        ), 403);

        return $apiSuccess($activity);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyGetProcessingActivity',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Get one processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity detail',
            ],
            '404' => [
                'description' => 'Processing activity not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.view');

    Route::post('/privacy/activities', function (
        Request $request,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
    ) use ($apiSuccess, $apiPrincipalId) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:200'],
            'lawful_basis' => ['required', 'string', Rule::in($catalogs->keys('privacy.lawful_basis', $organizationId))],
            'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
            'linked_risk_ids' => ['nullable', 'string', 'max:255'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $activity = $privacy->createProcessingActivity([
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'privacy-processing-activity',
                domainObjectId: $activity['id'],
                assignmentType: 'owner',
                organizationId: $activity['organization_id'],
                scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($activity);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyCreateProcessingActivity',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Create one processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:200'],
            'lawful_basis' => ['required', 'string'],
            'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
            'linked_risk_ids' => ['nullable', 'string', 'max:255'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'lawful_basis' => 'privacy.lawful_basis',
        ],
        'lookup_fields' => [
            'linked_data_flow_ids' => '/api/v1/privacy/data-flows',
            'linked_risk_ids' => '/api/v1/lookups/risks/options',
            'linked_policy_id' => '/api/v1/policies',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::patch('/privacy/activities/{activityId}', function (
        Request $request,
        string $activityId,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $privacy->findProcessingActivity($activityId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:200'],
            'lawful_basis' => ['required', 'string', Rule::in($catalogs->keys('privacy.lawful_basis', $organizationId))],
            'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
            'linked_risk_ids' => ['nullable', 'string', 'max:255'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $activity = $privacy->updateProcessingActivity($activityId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($activity === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'privacy-processing-activity',
                domainObjectId: $activity['id'],
                assignmentType: 'owner',
                organizationId: $activity['organization_id'],
                scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'privacy-processing-activity')
            ->where('domain_object_id', $activity['id'])
            ->where('organization_id', $activity['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($activity);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyUpdateProcessingActivity',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Update one processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Processing activity not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:200'],
            'lawful_basis' => ['required', 'string'],
            'linked_data_flow_ids' => ['nullable', 'string', 'max:255'],
            'linked_risk_ids' => ['nullable', 'string', 'max:255'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'review_due_on' => ['nullable', 'date'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'lawful_basis' => 'privacy.lawful_basis',
        ],
        'lookup_fields' => [
            'linked_data_flow_ids' => '/api/v1/privacy/data-flows',
            'linked_risk_ids' => '/api/v1/lookups/risks/options',
            'linked_policy_id' => '/api/v1/policies',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::patch('/privacy/activities/{activityId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $activityId,
        string $assignmentId,
        DataFlowsPrivacyRepository $privacy,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $activity = $privacy->findProcessingActivity($activityId);
        abort_if($activity === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'privacy-processing-activity')
            ->where('domain_object_id', $activity['id'])
            ->where('organization_id', $activity['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'activity_id' => $activity['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyRemoveProcessingActivityOwner',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Remove one owner assignment from a processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity owner assignment removed',
            ],
            '404' => [
                'description' => 'Processing activity or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::post('/privacy/activities/{activityId}/artifacts', function (
        Request $request,
        string $activityId,
        DataFlowsPrivacyRepository $privacy,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $activity = $privacy->findProcessingActivity($activityId);
        abort_if($activity === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'data-flows-privacy',
            subjectType: 'privacy-processing-activity',
            subjectId: $activityId,
            artifactType: (string) ($validated['artifact_type'] ?? 'record'),
            label: (string) ($validated['label'] ?? 'Processing record'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            metadata: [
                'plugin' => 'data-flows-privacy',
                'lawful_basis' => $activity['lawful_basis'],
                'linked_policy_id' => $activity['linked_policy_id'],
                'linked_finding_id' => $activity['linked_finding_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyAttachProcessingActivityArtifact',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Upload one artifact to a processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Processing activity not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::post('/privacy/activities/{activityId}/transitions/{transitionKey}', function (
        Request $request,
        string $activityId,
        string $transitionKey,
        DataFlowsPrivacyRepository $privacy,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $activity = $privacy->findProcessingActivity($activityId);
        abort_if($activity === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $activity['organization_id'],
            scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
            domainObjectType: 'privacy-processing-activity',
            domainObjectId: $activity['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $activity['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.data-flows-privacy.processing-activity-lifecycle',
            subjectType: 'privacy-processing-activity',
            subjectId: $activityId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $activity['scope_id'] !== '' ? $activity['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'activity' => $privacy->findProcessingActivity($activityId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'dataFlowsPrivacyTransitionProcessingActivity',
        'tags' => ['privacy'],
        'tag_descriptions' => [
            'privacy' => 'Data flow and processing activity API surface.',
        ],
        'summary' => 'Apply one workflow transition to a processing activity',
        'responses' => [
            '200' => [
                'description' => 'Processing activity transitioned',
            ],
            '404' => [
                'description' => 'Processing activity not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.data-flows-privacy.records.manage');

    Route::get('/continuity/services', function (
        Request $request,
        ContinuityBcmRepository $continuity,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $continuity->allServices($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'continuity-service',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmListServices',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'List continuity services visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Continuity service list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.view');

    Route::get('/continuity/services/{serviceId}', function (
        Request $request,
        string $serviceId,
        ContinuityBcmRepository $continuity,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $service['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        return $apiSuccess($service);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmGetService',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Get one continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service detail',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.view');

    Route::post('/continuity/services', function (
        Request $request,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
    ) use ($apiSuccess, $apiPrincipalId) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'impact_tier' => ['required', 'string', Rule::in($catalogs->keys('continuity.impact_tier', $organizationId))],
            'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $service = $continuity->createService([
            ...$validated,
            'organization_id' => $organizationId,
        ]);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'continuity-service',
                domainObjectId: $service['id'],
                assignmentType: 'owner',
                organizationId: $service['organization_id'],
                scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        return $apiSuccess($service);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmCreateService',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Create one continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'impact_tier' => ['required', 'string'],
            'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'impact_tier' => 'continuity.impact_tier',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::patch('/continuity/services/{serviceId}', function (
        Request $request,
        string $serviceId,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $continuity->findService($serviceId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'impact_tier' => ['required', 'string', Rule::in($catalogs->keys('continuity.impact_tier', $organizationId))],
            'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $service = $continuity->updateService($serviceId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'continuity-service',
                domainObjectId: $service['id'],
                assignmentType: 'owner',
                organizationId: $service['organization_id'],
                scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', $service['id'])
            ->where('organization_id', $service['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $service['scope_id'] !== '' ? $service['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($service);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmUpdateService',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Update one continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'impact_tier' => ['required', 'string'],
            'recovery_time_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'recovery_point_objective_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'governed_fields' => [
            'impact_tier' => 'continuity.impact_tier',
        ],
        'lookup_fields' => [
            'linked_asset_id' => '/api/v1/assets',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/services/{serviceId}/dependencies', function (
        Request $request,
        string $serviceId,
        ContinuityBcmRepository $continuity,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        $validated = $request->validate([
            'depends_on_service_id' => ['required', 'string', 'max:120'],
            'dependency_kind' => ['required', 'string', Rule::in($catalogs->keys('continuity.dependency_kind', $service['organization_id']))],
            'recovery_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $continuity->addServiceDependency($serviceId, [
            ...$validated,
            'organization_id' => $service['organization_id'],
        ]);

        return $apiSuccess([
            'service_id' => $service['id'],
            'depends_on_service_id' => (string) $validated['depends_on_service_id'],
            'dependency_kind' => (string) $validated['dependency_kind'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmAddServiceDependency',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Create or update one continuity service dependency',
        'responses' => [
            '200' => [
                'description' => 'Dependency stored',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'depends_on_service_id' => ['required', 'string', 'max:120'],
            'dependency_kind' => ['required', 'string'],
            'recovery_notes' => ['nullable', 'string', 'max:255'],
        ],
        'governed_fields' => [
            'dependency_kind' => 'continuity.dependency_kind',
        ],
        'lookup_fields' => [
            'depends_on_service_id' => '/api/v1/continuity/services',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::patch('/continuity/services/{serviceId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $serviceId,
        string $assignmentId,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'continuity-service')
            ->where('domain_object_id', $service['id'])
            ->where('organization_id', $service['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'service_id' => $service['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmRemoveServiceOwner',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Remove one owner assignment from a continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service owner assignment removed',
            ],
            '404' => [
                'description' => 'Continuity service or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/services/{serviceId}/artifacts', function (
        Request $request,
        string $serviceId,
        ContinuityBcmRepository $continuity,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'continuity-bcm',
            subjectType: 'continuity-service',
            subjectId: $serviceId,
            artifactType: (string) ($validated['artifact_type'] ?? 'continuity-record'),
            label: (string) ($validated['label'] ?? 'Continuity record'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            metadata: [
                'plugin' => 'continuity-bcm',
                'impact_tier' => $service['impact_tier'],
                'linked_asset_id' => $service['linked_asset_id'],
                'linked_risk_id' => $service['linked_risk_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmAttachServiceArtifact',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Upload one artifact to a continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/services/{serviceId}/transitions/{transitionKey}', function (
        Request $request,
        string $serviceId,
        string $transitionKey,
        ContinuityBcmRepository $continuity,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $service['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.continuity-bcm.service-lifecycle',
            subjectType: 'continuity-service',
            subjectId: $serviceId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'service' => $continuity->findService($serviceId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmTransitionService',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Apply one workflow transition to a continuity service',
        'responses' => [
            '200' => [
                'description' => 'Continuity service transitioned',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::get('/continuity/plans', function (
        Request $request,
        ContinuityBcmRepository $continuity,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = $objectAccess->filterRecords(
            records: $continuity->allPlans($organizationId, is_string($scopeId) ? $scopeId : null),
            idKey: 'id',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: is_string($scopeId) ? $scopeId : null,
            domainObjectType: 'continuity-plan',
        );

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmListPlans',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'List recovery plans visible in current context',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.view');

    Route::get('/continuity/plans/{planId}', function (
        Request $request,
        string $planId,
        ContinuityBcmRepository $continuity,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $plan['organization_id'] === $organizationId, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        return $apiSuccess($plan);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmGetPlan',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Get one recovery plan',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan detail',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.view');

    Route::post('/continuity/services/{serviceId}/plans', function (
        Request $request,
        string $serviceId,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $service = $continuity->findService($serviceId);
        abort_if($service === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $service['organization_id'],
            scopeId: $service['scope_id'] !== '' ? $service['scope_id'] : null,
            domainObjectType: 'continuity-service',
            domainObjectId: $service['id'],
        ), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'strategy_summary' => ['required', 'string', 'max:255'],
            'test_due_on' => ['nullable', 'date'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $plan = $continuity->createPlan($serviceId, [
            ...$validated,
            'organization_id' => $service['organization_id'],
            'scope_id' => is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
                ? $validated['scope_id']
                : ($service['scope_id'] !== '' ? $service['scope_id'] : null),
        ]);

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'continuity-plan',
                domainObjectId: $plan['id'],
                assignmentType: 'owner',
                organizationId: $plan['organization_id'],
                scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: $principalId,
            );
        }

        return $apiSuccess($plan);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmCreatePlan',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Create one recovery plan for a continuity service',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Continuity service not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'strategy_summary' => ['required', 'string', 'max:255'],
            'test_due_on' => ['nullable', 'date'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'linked_policy_id' => '/api/v1/policies',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::patch('/continuity/plans/{planId}', function (
        Request $request,
        string $planId,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $existing = $continuity->findPlan($planId);
        abort_if($existing === null, 404);

        abort_unless($objectAccess->canAccessObject(
            principalId: $apiPrincipalId($request),
            organizationId: $existing['organization_id'],
            scopeId: $existing['scope_id'] !== '' ? $existing['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $existing['id'],
        ), 403);

        $organizationId = (string) $request->input('organization_id', $existing['organization_id']);
        abort_unless($organizationId === $existing['organization_id'], 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'strategy_summary' => ['required', 'string', 'max:255'],
            'test_due_on' => ['nullable', 'date'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ]);

        $plan = $continuity->updatePlan($planId, [
            ...$validated,
            'organization_id' => $organizationId,
        ]);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'continuity-plan',
                domainObjectId: $plan['id'],
                assignmentType: 'owner',
                organizationId: $plan['organization_id'],
                scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: is_string($principalId) && $principalId !== '' ? $principalId : null,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', $plan['id'])
            ->where('organization_id', $plan['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess($plan);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmUpdatePlan',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Update one recovery plan',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan updated',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:160'],
            'strategy_summary' => ['required', 'string', 'max:255'],
            'test_due_on' => ['nullable', 'date'],
            'linked_policy_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'linked_policy_id' => '/api/v1/policies',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/plans/{planId}/exercises', function (
        Request $request,
        string $planId,
        ContinuityBcmRepository $continuity,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        $validated = $request->validate([
            'exercise_date' => ['required', 'date'],
            'exercise_type' => ['required', 'string', Rule::in($catalogs->keys('continuity.exercise_type', $plan['organization_id']))],
            'scenario_summary' => ['required', 'string', 'max:255'],
            'outcome' => ['required', 'string', Rule::in($catalogs->keys('continuity.exercise_outcome', $plan['organization_id']))],
            'follow_up_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $continuity->recordExercise($planId, [
            ...$validated,
            'organization_id' => $plan['organization_id'],
        ]);

        return $apiSuccess([
            'plan_id' => $plan['id'],
            'exercise_date' => (string) $validated['exercise_date'],
            'exercise_type' => (string) $validated['exercise_type'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmRecordPlanExercise',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Record one recovery plan exercise',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan exercise recorded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'exercise_date' => ['required', 'date'],
            'exercise_type' => ['required', 'string'],
            'scenario_summary' => ['required', 'string', 'max:255'],
            'outcome' => ['required', 'string'],
            'follow_up_summary' => ['nullable', 'string', 'max:255'],
        ],
        'governed_fields' => [
            'exercise_type' => 'continuity.exercise_type',
            'outcome' => 'continuity.exercise_outcome',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/plans/{planId}/executions', function (
        Request $request,
        string $planId,
        ContinuityBcmRepository $continuity,
        ReferenceCatalogService $catalogs,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        $validated = $request->validate([
            'executed_on' => ['required', 'date'],
            'execution_type' => ['required', 'string', Rule::in($catalogs->keys('continuity.execution_type', $plan['organization_id']))],
            'status' => ['required', 'string', Rule::in($catalogs->keys('continuity.execution_status', $plan['organization_id']))],
            'participants' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $continuity->recordTestExecution($planId, [
            ...$validated,
            'organization_id' => $plan['organization_id'],
        ]);

        return $apiSuccess([
            'plan_id' => $plan['id'],
            'executed_on' => (string) $validated['executed_on'],
            'execution_type' => (string) $validated['execution_type'],
            'status' => (string) $validated['status'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmRecordPlanExecution',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Record one recovery plan test execution',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan execution recorded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'executed_on' => ['required', 'date'],
            'execution_type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'participants' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ],
        'governed_fields' => [
            'execution_type' => 'continuity.execution_type',
            'status' => 'continuity.execution_status',
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::patch('/continuity/plans/{planId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $planId,
        string $assignmentId,
        ContinuityBcmRepository $continuity,
        FunctionalActorServiceInterface $actors,
        ObjectAccessService $objectAccess,
    ) use ($apiSuccess, $apiPrincipalId) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        $assignment = DB::table('functional_assignments')
            ->where('id', $assignmentId)
            ->where('domain_object_type', 'continuity-plan')
            ->where('domain_object_id', $plan['id'])
            ->where('organization_id', $plan['organization_id'])
            ->where('assignment_type', 'owner')
            ->where('is_active', true)
            ->first(['id']);
        abort_if($assignment === null, 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'removed' => true,
            'assignment_id' => $assignmentId,
            'plan_id' => $plan['id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmRemovePlanOwner',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Remove one owner assignment from a recovery plan',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan owner assignment removed',
            ],
            '404' => [
                'description' => 'Recovery plan or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/plans/{planId}/artifacts', function (
        Request $request,
        string $planId,
        ContinuityBcmRepository $continuity,
        ArtifactServiceInterface $artifacts,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'continuity-bcm',
            subjectType: 'continuity-plan',
            subjectId: $planId,
            artifactType: (string) ($validated['artifact_type'] ?? 'recovery-plan'),
            label: (string) ($validated['label'] ?? 'Recovery plan'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($validated['membership_id'] ?? null) && $validated['membership_id'] !== ''
                ? $validated['membership_id']
                : null,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            metadata: [
                'plugin' => 'continuity-bcm',
                'linked_policy_id' => $plan['linked_policy_id'],
                'linked_finding_id' => $plan['linked_finding_id'],
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmAttachPlanArtifact',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Upload one artifact to a recovery plan',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan artifact uploaded',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => ['type' => 'string', 'format' => 'binary'],
                            'label' => ['type' => 'string'],
                            'artifact_type' => ['type' => 'string'],
                            'membership_id' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::post('/continuity/plans/{planId}/transitions/{transitionKey}', function (
        Request $request,
        string $planId,
        string $transitionKey,
        ContinuityBcmRepository $continuity,
        WorkflowServiceInterface $workflows,
        ObjectAccessService $objectAccess,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $plan = $continuity->findPlan($planId);
        abort_if($plan === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        abort_unless($objectAccess->canAccessObject(
            principalId: $principalId,
            organizationId: $plan['organization_id'],
            scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
            domainObjectType: 'continuity-plan',
            domainObjectId: $plan['id'],
        ), 403);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $plan['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.continuity-bcm.plan-lifecycle',
            subjectType: 'continuity-plan',
            subjectId: $planId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $plan['scope_id'] !== '' ? $plan['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        return $apiSuccess([
            'plan' => $continuity->findPlan($planId),
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'continuityBcmTransitionPlan',
        'tags' => ['continuity'],
        'tag_descriptions' => [
            'continuity' => 'Continuity service and recovery plan API surface.',
        ],
        'summary' => 'Apply one workflow transition to a recovery plan',
        'responses' => [
            '200' => [
                'description' => 'Recovery plan transitioned',
            ],
            '404' => [
                'description' => 'Recovery plan not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.continuity-bcm.plans.manage');

    Route::get('/vendors', function (
        Request $request,
        ThirdPartyRiskRepository $vendors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $organizationId = (string) $request->input('organization_id');
        abort_if($organizationId === '', 422);
        $scopeId = $request->input('scope_id');
        $principalId = $apiPrincipalId($request);

        $rows = [];

        foreach ($vendors->all($organizationId, is_string($scopeId) ? $scopeId : null) as $vendor) {
            $reviews = $vendors->reviewsForVendor((string) ($vendor['id'] ?? ''));
            $visibleReviews = array_values(array_filter($reviews, static function (array $review) use ($objectAccess, $principalId, $organizationId): bool {
                return $objectAccess->canAccessObject(
                    principalId: $principalId,
                    organizationId: $organizationId,
                    scopeId: ($review['scope_id'] ?? '') !== '' ? $review['scope_id'] : null,
                    domainObjectType: 'vendor-review',
                    domainObjectId: (string) ($review['id'] ?? ''),
                );
            }));

            if ($visibleReviews === []) {
                continue;
            }

            $rows[] = [
                ...$vendor,
                'current_review' => $visibleReviews[0],
                'review_count' => count($visibleReviews),
            ];
        }

        return $apiSuccess($rows);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskListVendors',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'List vendors with visible current review context',
        'responses' => [
            '200' => [
                'description' => 'Vendor list',
            ],
            '422' => [
                'description' => 'Organization context required',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.view');

    Route::get('/vendors/{vendorId}', function (
        Request $request,
        string $vendorId,
        ThirdPartyRiskRepository $vendors,
        ObjectAccessService $objectAccess,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        abort_if($vendor === null, 404);

        $organizationId = (string) $request->input('organization_id');
        abort_unless($organizationId !== '' && $vendor['organization_id'] === $organizationId, 404);

        $scopeId = $request->input('scope_id');
        if (is_string($scopeId) && $scopeId !== '' && ($vendor['scope_id'] ?? '') !== '' && $vendor['scope_id'] !== $scopeId) {
            abort(404);
        }

        $principalId = $apiPrincipalId($request);
        $reviews = $vendors->reviewsForVendor($vendorId);
        $visibleReviews = array_values(array_filter($reviews, static function (array $review) use ($objectAccess, $principalId, $organizationId): bool {
            return $objectAccess->canAccessObject(
                principalId: $principalId,
                organizationId: $organizationId,
                scopeId: ($review['scope_id'] ?? '') !== '' ? $review['scope_id'] : null,
                domainObjectType: 'vendor-review',
                domainObjectId: (string) ($review['id'] ?? ''),
            );
        }));

        abort_if($visibleReviews === [], 403);

        return $apiSuccess([
            ...$vendor,
            'current_review' => $visibleReviews[0],
            'reviews' => $visibleReviews,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskGetVendor',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Get one vendor with visible review details',
        'responses' => [
            '200' => [
                'description' => 'Vendor detail',
            ],
            '404' => [
                'description' => 'Vendor not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.view');

    Route::post('/vendors', function (
        Request $request,
        ThirdPartyRiskRepository $vendors,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:140'],
            'service_summary' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:120'],
            'primary_contact_email' => ['nullable', 'email', 'max:160'],
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'scope_id' => ['nullable', 'string', 'max:64', 'exists:scopes,id'],
            'review_profile_id' => ['nullable', 'string', 'max:120'],
            'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
            'review_title' => ['required', 'string', 'max:140'],
            'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'review_summary' => ['required', 'string', 'max:2000'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
            'linked_control_id' => ['nullable', 'string', 'max:120', 'exists:controls,id'],
            'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
            'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
            'next_review_due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        [$vendor, $review] = $vendors->createVendorWithReview([
            ...$validated,
            'created_by_principal_id' => $principalId,
        ]);

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'vendor-review',
                domainObjectId: $review['id'],
                assignmentType: 'owner',
                organizationId: $vendor['organization_id'],
                scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: $principalId,
            );
        }

        return $apiSuccess([
            'vendor' => $vendor,
            'review' => $review,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskCreateVendorWithReview',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Create a vendor with its initial review workspace',
        'responses' => [
            '200' => [
                'description' => 'Vendor and review created',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'legal_name' => ['required', 'string', 'max:140'],
            'service_summary' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:120'],
            'primary_contact_email' => ['nullable', 'email', 'max:160'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'review_profile_id' => ['nullable', 'string', 'max:120'],
            'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
            'review_title' => ['required', 'string', 'max:140'],
            'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'review_summary' => ['required', 'string', 'max:2000'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'next_review_due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'review_profile_id' => '/api/v1/lookups/vendor-review-profiles/options',
            'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:140'],
            'service_summary' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:120'],
            'primary_contact_email' => ['nullable', 'email', 'max:160'],
            'scope_id' => ['nullable', 'string', 'max:64', 'exists:scopes,id'],
            'review_profile_id' => ['nullable', 'string', 'max:120'],
            'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
            'review_title' => ['required', 'string', 'max:140'],
            'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'review_summary' => ['required', 'string', 'max:2000'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'linked_asset_id' => ['nullable', 'string', 'max:120', 'exists:assets,id'],
            'linked_control_id' => ['nullable', 'string', 'max:120', 'exists:controls,id'],
            'linked_risk_id' => ['nullable', 'string', 'max:120', 'exists:risks,id'],
            'linked_finding_id' => ['nullable', 'string', 'max:120', 'exists:findings,id'],
            'next_review_due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
        ]);

        $organizationId = (string) $request->input('organization_id', $vendor['organization_id']);
        abort_unless($organizationId === $vendor['organization_id'], 404);

        [$updatedVendor, $updatedReview] = $vendors->updateVendorWithReview($vendorId, [
            ...$validated,
            'organization_id' => $organizationId,
            'review_id' => $reviewId,
        ]);
        abort_if($updatedVendor === null || $updatedReview === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        if (is_string($validated['owner_actor_id'] ?? null) && $validated['owner_actor_id'] !== '') {
            $actors->assignActor(
                actorId: $validated['owner_actor_id'],
                domainObjectType: 'vendor-review',
                domainObjectId: $updatedReview['id'],
                assignmentType: 'owner',
                organizationId: $updatedVendor['organization_id'],
                scopeId: $updatedVendor['scope_id'] !== '' ? $updatedVendor['scope_id'] : null,
                metadata: ['source' => 'api'],
                assignedByPrincipalId: $principalId,
            );
        }

        DB::table('functional_assignments')
            ->where('domain_object_type', 'vendor-review')
            ->where('domain_object_id', $updatedReview['id'])
            ->where('organization_id', $updatedVendor['organization_id'])
            ->where('is_active', true)
            ->update([
                'scope_id' => $updatedVendor['scope_id'] !== '' ? $updatedVendor['scope_id'] : null,
                'updated_at' => now(),
            ]);

        return $apiSuccess([
            'vendor' => $updatedVendor,
            'review' => $updatedReview,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateVendorWithReview',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update a vendor and one review workspace',
        'responses' => [
            '200' => [
                'description' => 'Vendor and review updated',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'legal_name' => ['required', 'string', 'max:140'],
            'service_summary' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:120'],
            'primary_contact_email' => ['nullable', 'email', 'max:160'],
            'scope_id' => ['nullable', 'string', 'max:64'],
            'review_profile_id' => ['nullable', 'string', 'max:120'],
            'questionnaire_template_id' => ['nullable', 'string', 'max:120'],
            'review_title' => ['required', 'string', 'max:140'],
            'inherent_risk' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'review_summary' => ['required', 'string', 'max:2000'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'linked_asset_id' => ['nullable', 'string', 'max:120'],
            'linked_control_id' => ['nullable', 'string', 'max:120'],
            'linked_risk_id' => ['nullable', 'string', 'max:120'],
            'linked_finding_id' => ['nullable', 'string', 'max:120'],
            'next_review_due_on' => ['nullable', 'date'],
            'owner_actor_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'review_profile_id' => '/api/v1/lookups/vendor-review-profiles/options',
            'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
            'linked_asset_id' => '/api/v1/assets',
            'linked_control_id' => '/api/v1/lookups/controls/options',
            'linked_risk_id' => '/api/v1/lookups/risks/options',
            'linked_finding_id' => '/api/v1/lookups/findings/options',
            'owner_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/external-links', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:160'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'can_answer_questionnaire' => ['nullable', 'boolean'],
            'can_upload_artifacts' => ['nullable', 'boolean'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        [$link, $token] = $vendors->issueExternalLinkForReview($reviewId, [
            ...$validated,
            'issued_by_principal_id' => $principalId,
            'can_answer_questionnaire' => (bool) ($validated['can_answer_questionnaire'] ?? false),
            'can_upload_artifacts' => (bool) ($validated['can_upload_artifacts'] ?? false),
        ]);

        return $apiSuccess([
            'link' => $link,
            'portal_url' => route('plugin.third-party-risk.external.portal.show', ['token' => $token]),
            'portal_token' => $token,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskIssueExternalLink',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Issue one external collaboration link for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'External link issued',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:160'],
            'expires_at' => ['nullable', 'date'],
            'can_answer_questionnaire' => ['nullable', 'boolean'],
            'can_upload_artifacts' => ['nullable', 'boolean'],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/external-links/{linkId}/revoke', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $linkId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->revokeExternalLink($reviewId, $linkId, $principalId);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskRevokeExternalLink',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Revoke one external collaboration link',
        'responses' => [
            '200' => [
                'description' => 'External link revoked',
            ],
            '404' => [
                'description' => 'Vendor, review, or external link not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/external-collaborators/{collaboratorId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $collaboratorId,
        ThirdPartyRiskRepository $vendors,
        CollaborationEngineInterface $collaboration,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'lifecycle_state' => ['required', 'string', Rule::in($collaboration->collaboratorLifecycleStateKeys())],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->updateExternalCollaboratorLifecycleForReview(
            reviewId: $reviewId,
            collaboratorId: $collaboratorId,
            lifecycleState: $validated['lifecycle_state'],
            principalId: $principalId,
        );
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateExternalCollaboratorLifecycle',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update one external collaborator lifecycle state',
        'responses' => [
            '200' => [
                'description' => 'External collaborator lifecycle updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or collaborator not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'lifecycle_state' => ['required', 'string'],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
        CollaborationEngineInterface $collaboration,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'draft_type' => ['required', 'string', Rule::in($collaboration->draftTypeKeys())],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
            'details' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', 'string', Rule::in($collaboration->requestPriorityKeys())],
            'handoff_state' => ['nullable', 'string', Rule::in($collaboration->handoffStateKeys())],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        if (($validated['draft_type'] ?? 'comment') === 'comment' && trim((string) ($validated['body'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'body' => 'Comment drafts require body text.',
            ]);
        }

        if (($validated['draft_type'] ?? 'comment') !== 'comment' && trim((string) ($validated['title'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'title' => 'Follow-up drafts require a title.',
            ]);
        }

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->createCollaborationDraftForReview($reviewId, [
            ...$validated,
            'edited_by_principal_id' => $principalId,
        ]);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskCreateVendorReviewDraft',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Create one shared collaboration draft for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Collaboration draft created',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'draft_type' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
            'details' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', 'string'],
            'handoff_state' => ['nullable', 'string'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64'],
            'due_on' => ['nullable', 'date'],
        ],
        'lookup_fields' => [
            'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
            'assigned_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $draftId,
        ThirdPartyRiskRepository $vendors,
        CollaborationEngineInterface $collaboration,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'draft_type' => ['required', 'string', Rule::in($collaboration->draftTypeKeys())],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
            'details' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', 'string', Rule::in($collaboration->requestPriorityKeys())],
            'handoff_state' => ['nullable', 'string', Rule::in($collaboration->handoffStateKeys())],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        if (($validated['draft_type'] ?? 'comment') === 'comment' && trim((string) ($validated['body'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'body' => 'Comment drafts require body text.',
            ]);
        }

        if (($validated['draft_type'] ?? 'comment') !== 'comment' && trim((string) ($validated['title'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'title' => 'Follow-up drafts require a title.',
            ]);
        }

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->updateCollaborationDraftForReview(
            reviewId: $reviewId,
            draftId: $draftId,
            data: [
                ...$validated,
                'edited_by_principal_id' => $principalId,
            ],
            principalId: $principalId,
        );
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateVendorReviewDraft',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update one shared collaboration draft for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Collaboration draft updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or draft not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'draft_type' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
            'details' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', 'string'],
            'handoff_state' => ['nullable', 'string'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64'],
            'due_on' => ['nullable', 'date'],
        ],
        'lookup_fields' => [
            'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
            'assigned_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-comment', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $draftId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->promoteCollaborationDraftToComment($reviewId, $draftId, $principalId);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskPromoteVendorReviewDraftToComment',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Promote one collaboration draft to comment',
        'responses' => [
            '200' => [
                'description' => 'Draft promoted to comment',
            ],
            '404' => [
                'description' => 'Vendor, review, or draft not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/drafts/{draftId}/promote-request', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $draftId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->promoteCollaborationDraftToRequest($reviewId, $draftId, $principalId);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskPromoteVendorReviewDraftToRequest',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Promote one collaboration draft to follow-up request',
        'responses' => [
            '200' => [
                'description' => 'Draft promoted to follow-up request',
            ],
            '404' => [
                'description' => 'Vendor, review, or draft not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/owners/{assignmentId}/remove', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $assignmentId,
        ThirdPartyRiskRepository $vendors,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $assignment = collect($actors->assignmentsFor(
            domainObjectType: 'vendor-review',
            domainObjectId: $reviewId,
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
        ))->first(fn ($candidate) => $candidate->id === $assignmentId && $candidate->assignmentType === 'owner');

        abort_if($assignment === null, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $principalId,
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'removed' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskRemoveVendorReviewOwner',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Remove one owner assignment from a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Owner assignment removed',
            ],
            '404' => [
                'description' => 'Vendor, review, or assignment not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/artifacts', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
        ArtifactServiceInterface $artifacts,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'artifact_type' => ['nullable', 'string', 'max:60'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $membershipId = $validated['membership_id'] ?? $request->input('membership_id');
        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'third-party-risk',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            artifactType: (string) ($validated['artifact_type'] ?? 'evidence'),
            label: (string) ($validated['label'] ?? 'Vendor review evidence'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            uploadProfile: 'review_artifacts',
            metadata: [
                'plugin' => 'third-party-risk',
                'vendor_id' => $vendorId,
                'review_id' => $reviewId,
            ],
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskAttachVendorReviewArtifact',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Upload one artifact to a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Artifact uploaded',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => [
                                'type' => 'string',
                                'format' => 'binary',
                            ],
                            'label' => [
                                'type' => 'string',
                            ],
                            'artifact_type' => [
                                'type' => 'string',
                            ],
                            'membership_id' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/artifacts', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $itemId,
        ThirdPartyRiskRepository $vendors,
        ArtifactServiceInterface $artifacts,
        AuditTrailInterface $audit,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        $item = $vendors->findQuestionnaireItem($itemId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId || ($item['attachment_mode'] ?? 'none') === 'none', 404);

        $validated = $request->validate([
            'artifact' => ['required', 'file', 'max:10240'],
            'label' => ['nullable', 'string', 'max:120'],
            'membership_id' => ['nullable', 'string', 'max:120'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $membershipId = $validated['membership_id'] ?? $request->input('membership_id');
        $record = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'questionnaires',
            subjectType: 'questionnaire-subject-item',
            subjectId: $itemId,
            artifactType: ($item['attachment_mode'] ?? 'none') === 'supporting-evidence' ? 'evidence' : 'document',
            label: (string) ($validated['label'] ?? 'Questionnaire attachment'),
            file: $validated['artifact'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            uploadProfile: ($item['attachment_upload_profile'] ?? '') !== '' ? $item['attachment_upload_profile'] : 'documents_only',
            metadata: [
                'plugin' => 'questionnaires',
                'questionnaire_owner_component' => 'third-party-risk',
                'questionnaire_subject_type' => 'vendor-review',
                'questionnaire_subject_id' => $reviewId,
                'questionnaire_item_id' => $itemId,
                'questionnaire_prompt' => $item['prompt'],
                'vendor_id' => $vendorId,
                'review_id' => $reviewId,
            ],
            executionOrigin: 'third-party-risk-questionnaire-item',
        ));

        $audit->record(new AuditRecordData(
            eventType: 'plugin.third-party-risk.questionnaire-item.artifact-uploaded',
            outcome: 'success',
            originComponent: 'third-party-risk',
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: $vendor['organization_id'],
            scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
            targetType: 'artifact',
            targetId: $record->id,
            summary: [
                'review_id' => $reviewId,
                'questionnaire_item_id' => $itemId,
                'label' => $record->label,
            ],
            executionOrigin: 'third-party-risk',
        ));

        return $apiSuccess($record->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskAttachQuestionnaireItemArtifact',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Upload one artifact to a vendor review questionnaire item',
        'responses' => [
            '200' => [
                'description' => 'Questionnaire attachment uploaded',
            ],
            '404' => [
                'description' => 'Vendor, review, or questionnaire item not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_body' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['artifact'],
                        'properties' => [
                            'artifact' => [
                                'type' => 'string',
                                'format' => 'binary',
                            ],
                            'label' => [
                                'type' => 'string',
                            ],
                            'membership_id' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/comments', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $comment = $vendors->addCommentToReview($reviewId, [
            'author_principal_id' => $principalId,
            'body' => $validated['body'],
            'mentioned_actor_ids' => $validated['mentioned_actor_ids'] ?? [],
        ]);
        abort_if($comment === null, 404);

        return $apiSuccess($comment);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskAddVendorReviewComment',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Add one collaboration comment to a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Comment created',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'body' => ['required', 'string', 'max:4000'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64'],
        ],
        'lookup_fields' => [
            'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
        CollaborationEngineInterface $collaboration,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'string', Rule::in($collaboration->requestStatusKeys())],
            'priority' => ['required', 'string', Rule::in($collaboration->requestPriorityKeys())],
            'handoff_state' => ['required', 'string', Rule::in($collaboration->handoffStateKeys())],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->createCollaborationRequestForReview($reviewId, [
            ...$validated,
            'requested_by_principal_id' => $principalId,
        ]);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskCreateVendorReviewRequest',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Create one collaboration follow-up request for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Follow-up request created',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'handoff_state' => ['required', 'string'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64'],
            'due_on' => ['nullable', 'date'],
        ],
        'lookup_fields' => [
            'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
            'assigned_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/collaboration/requests/{requestId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $requestId,
        ThirdPartyRiskRepository $vendors,
        CollaborationEngineInterface $collaboration,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'string', Rule::in($collaboration->requestStatusKeys())],
            'priority' => ['required', 'string', Rule::in($collaboration->requestPriorityKeys())],
            'handoff_state' => ['required', 'string', Rule::in($collaboration->handoffStateKeys())],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64', 'exists:functional_actors,id'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64', 'exists:functional_actors,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->updateCollaborationRequestForReview(
            reviewId: $reviewId,
            requestId: $requestId,
            data: $validated,
            principalId: $principalId,
        );
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateVendorReviewRequest',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update one collaboration follow-up request for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Follow-up request updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or request not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'handoff_state' => ['required', 'string'],
            'mentioned_actor_ids' => ['nullable', 'array'],
            'mentioned_actor_ids.*' => ['string', 'max:64'],
            'assigned_actor_id' => ['nullable', 'string', 'max:64'],
            'due_on' => ['nullable', 'date'],
        ],
        'lookup_fields' => [
            'mentioned_actor_ids' => '/api/v1/lookups/actors/options',
            'assigned_actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/brokered-requests', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'collection_channel' => ['required', Rule::in(['email', 'meeting', 'call', 'uploaded-docs', 'broker-note'])],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'broker_principal_id' => ['nullable', 'string', 'max:64'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->issueBrokeredRequestForReview($reviewId, [
            ...$validated,
            'issued_by_principal_id' => $principalId,
            'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
                ? $validated['broker_principal_id']
                : $principalId,
        ]);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskIssueBrokeredRequest',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Issue one brokered collection request for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Brokered request created',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'collection_channel' => ['required', 'string'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'broker_principal_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'broker_principal_id' => '/api/v1/lookups/principals/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/brokered-requests/{requestId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $requestId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'collection_status' => ['required', Rule::in(['queued', 'in-progress', 'submitted', 'completed', 'cancelled'])],
            'broker_notes' => ['nullable', 'string', 'max:2000'],
            'broker_principal_id' => ['nullable', 'string', 'max:64'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->updateBrokeredRequestForReview(
            reviewId: $reviewId,
            requestId: $requestId,
            data: [
                ...$validated,
                'broker_principal_id' => is_string($validated['broker_principal_id'] ?? null) && $validated['broker_principal_id'] !== ''
                    ? $validated['broker_principal_id']
                    : $principalId,
            ],
            principalId: $principalId,
        );
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateBrokeredRequest',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update one brokered collection request for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Brokered request updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or brokered request not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'collection_status' => ['required', 'string'],
            'broker_notes' => ['nullable', 'string', 'max:2000'],
            'broker_principal_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'broker_principal_id' => '/api/v1/lookups/principals/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
        QuestionnaireEngineInterface $questionnaires,
    ) use ($apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'section_title' => ['nullable', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:2000'],
            'guidance_text' => ['nullable', 'string', 'max:2000'],
            'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
            'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
            'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $record = $vendors->addQuestionnaireItem($reviewId, $validated);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskAddQuestionnaireItem',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Add one questionnaire item to a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Questionnaire item created',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'section_title' => ['nullable', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:2000'],
            'guidance_text' => ['nullable', 'string', 'max:2000'],
            'response_type' => ['required', 'string'],
            'attachment_mode' => ['nullable', 'string'],
            'attachment_upload_profile' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $itemId,
        ThirdPartyRiskRepository $vendors,
        QuestionnaireEngineInterface $questionnaires,
    ) use ($apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        $item = $vendors->findQuestionnaireItem($itemId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

        $validated = $request->validate([
            'section_title' => ['nullable', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:2000'],
            'guidance_text' => ['nullable', 'string', 'max:2000'],
            'response_type' => ['required', 'string', Rule::in($questionnaires->responseTypeKeys())],
            'response_status' => ['nullable', 'string', Rule::in($questionnaires->responseStatusKeys())],
            'attachment_mode' => ['nullable', 'string', Rule::in($questionnaires->attachmentModeKeys())],
            'attachment_upload_profile' => ['nullable', 'string', Rule::in(['documents_only', 'documents_and_spreadsheets', 'images_only', 'review_artifacts'])],
            'is_required' => ['nullable', 'boolean'],
            'answer_text' => ['nullable', 'string', 'max:4000'],
            'follow_up_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = $vendors->updateQuestionnaireItem($reviewId, $itemId, $validated);
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskUpdateQuestionnaireItem',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Update one questionnaire item for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Questionnaire item updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or questionnaire item not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'section_title' => ['nullable', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:2000'],
            'guidance_text' => ['nullable', 'string', 'max:2000'],
            'response_type' => ['required', 'string'],
            'response_status' => ['nullable', 'string'],
            'attachment_mode' => ['nullable', 'string'],
            'attachment_upload_profile' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'answer_text' => ['nullable', 'string', 'max:4000'],
            'follow_up_notes' => ['nullable', 'string', 'max:2000'],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::patch('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-items/{itemId}/review', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $itemId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        $item = $vendors->findQuestionnaireItem($itemId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId || $item === null || $item['review_id'] !== $reviewId, 404);

        $validated = $request->validate([
            'response_status' => ['required', 'string', Rule::in(['under-review', 'accepted', 'needs-follow-up'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $record = $vendors->reviewQuestionnaireItem(
            reviewId: $reviewId,
            itemId: $itemId,
            responseStatus: $validated['response_status'],
            reviewNotes: $validated['review_notes'] ?? null,
            reviewedByPrincipalId: $principalId,
        );
        abort_if($record === null, 404);

        return $apiSuccess($record);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskReviewQuestionnaireItem',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Review one questionnaire item for a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Questionnaire review updated',
            ],
            '404' => [
                'description' => 'Vendor, review, or questionnaire item not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'response_status' => ['required', 'string'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/questionnaire-template/apply', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        ThirdPartyRiskRepository $vendors,
    ) use ($apiSuccess) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $validated = $request->validate([
            'questionnaire_template_id' => ['required', 'string', 'max:120'],
        ]);

        $created = $vendors->applyQuestionnaireTemplateToReview($reviewId, $validated['questionnaire_template_id']);

        return $apiSuccess([
            'review_id' => $reviewId,
            'questionnaire_template_id' => $validated['questionnaire_template_id'],
            'created_items' => $created,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskApplyQuestionnaireTemplate',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Apply one questionnaire template to a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Template applied',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'questionnaire_template_id' => ['required', 'string', 'max:120'],
        ],
        'lookup_fields' => [
            'questionnaire_template_id' => '/api/v1/lookups/vendor-questionnaire-templates/options',
        ],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');

    Route::post('/vendors/{vendorId}/reviews/{reviewId}/transitions/{transitionKey}', function (
        Request $request,
        string $vendorId,
        string $reviewId,
        string $transitionKey,
        ThirdPartyRiskRepository $vendors,
        WorkflowServiceInterface $workflows,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $vendor = $vendors->find($vendorId);
        $review = $vendors->findReview($reviewId);
        abort_if($vendor === null || $review === null || $review['vendor_id'] !== $vendorId, 404);

        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);
        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        abort_unless(is_string($organizationId) && $organizationId === $vendor['organization_id'], 404);

        $workflows->transition(
            workflowKey: 'plugin.third-party-risk.review-lifecycle',
            subjectType: 'vendor-review',
            subjectId: $reviewId,
            transitionKey: $transitionKey,
            context: new WorkflowExecutionContext(
                principal: new PrincipalReference(id: $principalId, provider: 'api'),
                memberships: $context->memberships,
                organizationId: $organizationId,
                scopeId: $vendor['scope_id'] !== '' ? $vendor['scope_id'] : null,
                membershipId: $context->membershipIds()[0] ?? null,
            ),
        );

        $vendors->syncVendorStatusForReview($reviewId, $transitionKey);

        $updatedVendor = $vendors->find($vendorId);
        $updatedReview = $vendors->findReview($reviewId);

        return $apiSuccess([
            'vendor' => $updatedVendor,
            'review' => $updatedReview,
            'transition' => $transitionKey,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'thirdPartyRiskTransitionReview',
        'tags' => ['vendors'],
        'tag_descriptions' => [
            'vendors' => 'Third-party risk and vendor review API surface.',
        ],
        'summary' => 'Apply one workflow transition to a vendor review',
        'responses' => [
            '200' => [
                'description' => 'Transition applied',
            ],
            '404' => [
                'description' => 'Vendor or review not found in current context',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [],
    ])->middleware('core.permission:plugin.third-party-risk.vendors.manage');
});
