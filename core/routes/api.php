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
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\Notifications\NotificationMailSettingsRepository;
use PymeSec\Core\Notifications\NotificationTemplateRepository;
use PymeSec\Core\Notifications\OutboundNotificationMailer;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\OpenApi\OpenApiDocumentBuilder;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\AuthorizationStoreInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Plugins\PluginLifecycleManager;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Security\ApiAccessTokenRepository;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;
use PymeSec\Plugins\FindingsRemediation\FindingsRemediationRepository;
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

    Route::post('/core/plugins/{pluginId}/enable', function (
        Request $request,
        string $pluginId,
        PluginLifecycleManager $lifecycle,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $request->validate([
            'organization_id' => ['nullable', 'string', 'max:80'],
            'scope_id' => ['nullable', 'string', 'max:80'],
        ]);

        $result = $lifecycle->enable($pluginId);
        $principalId = $apiPrincipalId($request);
        $organizationId = is_string($request->input('organization_id')) && $request->input('organization_id') !== ''
            ? (string) $request->input('organization_id')
            : null;
        $scopeId = is_string($request->input('scope_id')) && $request->input('scope_id') !== ''
            ? (string) $request->input('scope_id')
            : null;

        $audit->record(new AuditRecordData(
            eventType: 'core.plugins.enable',
            outcome: $result->ok ? 'success' : 'failure',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'plugin',
            targetId: $pluginId,
            summary: [
                'reason' => $result->reason,
                'effective_before' => $result->effectiveBefore,
                'effective_after' => $result->effectiveAfter,
                ...$result->details,
            ],
            executionOrigin: 'api',
        ));

        if ($result->ok) {
            $events->publish(new PublicEvent(
                name: 'core.plugins.enable',
                originComponent: 'core',
                organizationId: $organizationId,
                scopeId: $scopeId,
                payload: [
                    'plugin_id' => $pluginId,
                    'effective_before' => $result->effectiveBefore,
                    'effective_after' => $result->effectiveAfter,
                ],
            ));
        }

        return $apiSuccess([
            'plugin_id' => $pluginId,
            'ok' => $result->ok,
            'reason' => $result->reason,
            'message' => $result->message,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            'details' => $result->details,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreEnablePlugin',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Enable one plugin for next bootstrap',
        'responses' => [
            '200' => [
                'description' => 'Plugin lifecycle enable result',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:80'],
            'scope_id' => ['nullable', 'string', 'max:80'],
        ],
    ])->middleware('core.permission:core.plugins.manage');

    Route::post('/core/plugins/{pluginId}/disable', function (
        Request $request,
        string $pluginId,
        PluginLifecycleManager $lifecycle,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $request->validate([
            'organization_id' => ['nullable', 'string', 'max:80'],
            'scope_id' => ['nullable', 'string', 'max:80'],
        ]);

        $result = $lifecycle->disable($pluginId);
        $principalId = $apiPrincipalId($request);
        $organizationId = is_string($request->input('organization_id')) && $request->input('organization_id') !== ''
            ? (string) $request->input('organization_id')
            : null;
        $scopeId = is_string($request->input('scope_id')) && $request->input('scope_id') !== ''
            ? (string) $request->input('scope_id')
            : null;

        $audit->record(new AuditRecordData(
            eventType: 'core.plugins.disable',
            outcome: $result->ok ? 'success' : 'failure',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'plugin',
            targetId: $pluginId,
            summary: [
                'reason' => $result->reason,
                'effective_before' => $result->effectiveBefore,
                'effective_after' => $result->effectiveAfter,
                ...$result->details,
            ],
            executionOrigin: 'api',
        ));

        if ($result->ok) {
            $events->publish(new PublicEvent(
                name: 'core.plugins.disable',
                originComponent: 'core',
                organizationId: $organizationId,
                scopeId: $scopeId,
                payload: [
                    'plugin_id' => $pluginId,
                    'effective_before' => $result->effectiveBefore,
                    'effective_after' => $result->effectiveAfter,
                ],
            ));
        }

        return $apiSuccess([
            'plugin_id' => $pluginId,
            'ok' => $result->ok,
            'reason' => $result->reason,
            'message' => $result->message,
            'effective_before' => $result->effectiveBefore,
            'effective_after' => $result->effectiveAfter,
            'details' => $result->details,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreDisablePlugin',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Disable one plugin for next bootstrap',
        'responses' => [
            '200' => [
                'description' => 'Plugin lifecycle disable result',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:80'],
            'scope_id' => ['nullable', 'string', 'max:80'],
        ],
    ])->middleware('core.permission:core.plugins.manage');

    Route::post('/core/roles', function (
        Request $request,
        AuthorizationStoreInterface $store,
        PermissionRegistryInterface $permissions,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9\\.-]*$/'],
            'label' => ['required', 'string', 'max:160'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:160'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $selectedPermissions = array_values(array_filter(
            $validated['permissions'] ?? [],
            static fn (mixed $permission): bool => is_string($permission) && $permission !== '' && $permissions->has($permission),
        ));

        $role = $store->upsertRole(
            key: (string) $validated['key'],
            label: (string) $validated['label'],
            permissions: $selectedPermissions,
        );

        $principalId = $apiPrincipalId($request);
        $organizationId = is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== ''
            ? $validated['organization_id']
            : null;
        $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : null;

        $audit->record(new AuditRecordData(
            eventType: 'core.roles.upserted',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'role',
            targetId: $role->key,
            summary: [
                'label' => $role->label,
                'permission_count' => count($role->permissions),
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.roles.upserted',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'role_key' => $role->key,
                'label' => $role->label,
                'permission_count' => count($role->permissions),
            ],
        ));

        return $apiSuccess([
            'role' => [
                'key' => $role->key,
                'label' => $role->label,
                'permissions' => $role->permissions,
                'is_system' => $role->isSystem,
            ],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreCreateRole',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create or update one role definition',
        'responses' => [
            '200' => [
                'description' => 'Role saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'key' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:160'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:160'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.roles.manage');

    Route::post('/core/roles/grants', function (
        Request $request,
        AuthorizationStoreInterface $store,
        PermissionRegistryInterface $permissions,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:principal,membership'],
            'target_id' => ['required', 'string', 'max:120'],
            'grant_type' => ['required', 'string', 'in:role,permission'],
            'value' => ['required', 'string', 'max:160'],
            'context_type' => ['required', 'string', 'in:platform,organization,scope'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validated['grant_type'] === 'role' && ! isset($store->roleDefinitions()[$validated['value']])) {
            throw ValidationException::withMessages([
                'value' => 'Unknown role value.',
            ]);
        }

        if ($validated['grant_type'] === 'permission' && ! $permissions->has($validated['value'])) {
            throw ValidationException::withMessages([
                'value' => 'Unknown permission value.',
            ]);
        }

        $organizationId = $validated['context_type'] !== 'platform'
            ? (is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== '' ? $validated['organization_id'] : null)
            : null;
        $scopeId = $validated['context_type'] === 'scope'
            ? (is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null)
            : null;

        $grant = $store->upsertGrant(
            id: null,
            targetType: (string) $validated['target_type'],
            targetId: (string) $validated['target_id'],
            grantType: (string) $validated['grant_type'],
            value: (string) $validated['value'],
            contextType: (string) $validated['context_type'],
            organizationId: $organizationId,
            scopeId: $scopeId,
        );

        $principalId = $apiPrincipalId($request);

        $audit->record(new AuditRecordData(
            eventType: 'core.role-grants.upserted',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'authorization_grant',
            targetId: (string) ($grant['id'] ?? ''),
            summary: $grant,
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.role-grants.upserted',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: $grant,
        ));

        return $apiSuccess($grant);
    })->defaults('_openapi', [
        'operation_id' => 'coreCreateRoleGrant',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create one role or permission grant',
        'responses' => [
            '200' => [
                'description' => 'Grant saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'target_type' => ['required', 'string', 'in:principal,membership'],
            'target_id' => ['required', 'string', 'max:120'],
            'grant_type' => ['required', 'string', 'in:role,permission'],
            'value' => ['required', 'string', 'max:160'],
            'context_type' => ['required', 'string', 'in:platform,organization,scope'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'target_id' => [
                'source' => '/api/v1/lookups/grants/targets/options',
                'description' => 'Resolve principals or memberships (set query target_type=principal|membership).',
            ],
        ],
    ])->middleware('core.permission:core.roles.manage');

    Route::post('/core/roles/grants/{grantId}', function (
        Request $request,
        string $grantId,
        AuthorizationStoreInterface $store,
        PermissionRegistryInterface $permissions,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:principal,membership'],
            'target_id' => ['required', 'string', 'max:120'],
            'grant_type' => ['required', 'string', 'in:role,permission'],
            'value' => ['required', 'string', 'max:160'],
            'context_type' => ['required', 'string', 'in:platform,organization,scope'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validated['grant_type'] === 'role' && ! isset($store->roleDefinitions()[$validated['value']])) {
            throw ValidationException::withMessages([
                'value' => 'Unknown role value.',
            ]);
        }

        if ($validated['grant_type'] === 'permission' && ! $permissions->has($validated['value'])) {
            throw ValidationException::withMessages([
                'value' => 'Unknown permission value.',
            ]);
        }

        $organizationId = $validated['context_type'] !== 'platform'
            ? (is_string($validated['organization_id'] ?? null) && $validated['organization_id'] !== '' ? $validated['organization_id'] : null)
            : null;
        $scopeId = $validated['context_type'] === 'scope'
            ? (is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null)
            : null;

        $grant = $store->upsertGrant(
            id: $grantId,
            targetType: (string) $validated['target_type'],
            targetId: (string) $validated['target_id'],
            grantType: (string) $validated['grant_type'],
            value: (string) $validated['value'],
            contextType: (string) $validated['context_type'],
            organizationId: $organizationId,
            scopeId: $scopeId,
        );

        $principalId = $apiPrincipalId($request);

        $audit->record(new AuditRecordData(
            eventType: 'core.role-grants.upserted',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'authorization_grant',
            targetId: (string) ($grant['id'] ?? ''),
            summary: $grant,
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.role-grants.upserted',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: $grant,
        ));

        return $apiSuccess($grant);
    })->defaults('_openapi', [
        'operation_id' => 'coreUpdateRoleGrant',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Update one role or permission grant',
        'responses' => [
            '200' => [
                'description' => 'Grant updated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'target_type' => ['required', 'string', 'in:principal,membership'],
            'target_id' => ['required', 'string', 'max:120'],
            'grant_type' => ['required', 'string', 'in:role,permission'],
            'value' => ['required', 'string', 'max:160'],
            'context_type' => ['required', 'string', 'in:platform,organization,scope'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'target_id' => [
                'source' => '/api/v1/lookups/grants/targets/options',
                'description' => 'Resolve principals or memberships (set query target_type=principal|membership).',
            ],
        ],
    ])->middleware('core.permission:core.roles.manage');

    Route::post('/core/reference-data/entries', function (
        Request $request,
        ReferenceCatalogService $catalogs,
    ) use ($apiPrincipalId, $apiSuccess) {
        $catalogKeys = array_map(static fn (array $catalog): string => $catalog['key'], $catalogs->manageableCatalogs());

        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'catalog_key' => ['required', 'string', Rule::in($catalogKeys)],
            'option_key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('reference_catalog_entries', 'option_key')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $request->input('organization_id'))
                        ->where('catalog_key', $request->input('catalog_key'))),
            ],
            'label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $entry = $catalogs->createManagedEntry($validated, $apiPrincipalId($request));

        return $apiSuccess($entry);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataCreateEntry',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'Create one managed reference catalog entry',
        'responses' => [
            '200' => [
                'description' => 'Catalog entry created',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'catalog_key' => ['required', 'string'],
            'option_key' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ],
    ])->middleware('core.permission:core.reference-data.manage');

    Route::post('/core/reference-data/entries/{entryId}', function (
        Request $request,
        string $entryId,
        ReferenceCatalogService $catalogs,
    ) use ($apiPrincipalId, $apiSuccess) {
        $existing = $catalogs->findManagedEntry($entryId);
        abort_if($existing === null, 404);

        $catalogKeys = array_map(static fn (array $catalog): string => $catalog['key'], $catalogs->manageableCatalogs());

        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'catalog_key' => ['required', 'string', Rule::in($catalogKeys)],
            'option_key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('reference_catalog_entries', 'option_key')
                    ->ignore($entryId, 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $request->input('organization_id'))
                        ->where('catalog_key', $request->input('catalog_key'))),
            ],
            'label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $entry = $catalogs->updateManagedEntry($entryId, $validated, $apiPrincipalId($request));
        abort_if($entry === null, 404);

        return $apiSuccess($entry);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataUpdateEntry',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'Update one managed reference catalog entry',
        'responses' => [
            '200' => [
                'description' => 'Catalog entry updated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Catalog entry not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'catalog_key' => ['required', 'string'],
            'option_key' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:10000'],
        ],
    ])->middleware('core.permission:core.reference-data.manage');

    Route::post('/core/reference-data/entries/{entryId}/archive', function (
        Request $request,
        string $entryId,
        ReferenceCatalogService $catalogs,
    ) use ($apiPrincipalId, $apiSuccess) {
        $entry = $catalogs->findManagedEntry($entryId);
        abort_if($entry === null, 404);

        abort_unless($catalogs->archiveManagedEntry($entryId, $apiPrincipalId($request)), 404);

        return $apiSuccess([
            'id' => $entryId,
            'archived' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataArchiveEntry',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'Archive one managed reference catalog entry',
        'responses' => [
            '200' => [
                'description' => 'Catalog entry archived',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Catalog entry not found',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.reference-data.manage');

    Route::post('/core/reference-data/entries/{entryId}/activate', function (
        Request $request,
        string $entryId,
        ReferenceCatalogService $catalogs,
    ) use ($apiPrincipalId, $apiSuccess) {
        $entry = $catalogs->findManagedEntry($entryId);
        abort_if($entry === null, 404);

        abort_unless($catalogs->activateManagedEntry($entryId, $apiPrincipalId($request)), 404);

        return $apiSuccess([
            'id' => $entryId,
            'activated' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'referenceDataActivateEntry',
        'tags' => ['reference-data'],
        'tag_descriptions' => [
            'reference-data' => 'Governed lookup and catalog endpoints.',
        ],
        'summary' => 'Activate one managed reference catalog entry',
        'responses' => [
            '200' => [
                'description' => 'Catalog entry activated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Catalog entry not found',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.reference-data.manage');

    Route::post('/core/tenancy/organizations', function (
        Request $request,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')],
            'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
            'default_timezone' => ['required', 'string', 'max:64'],
        ]);

        $organization = $tenancy->createOrganization($validated);

        return $apiSuccess($organization->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'tenancyCreateOrganization',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create one organization',
        'responses' => [
            '200' => [
                'description' => 'Organization created',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
            'default_timezone' => ['required', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/organizations/{organizationId}', function (
        Request $request,
        string $organizationId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations', 'slug')->ignore($organizationId, 'id')],
            'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
            'default_timezone' => ['required', 'string', 'max:64'],
        ]);

        $organization = $tenancy->updateOrganization($organizationId, $validated);
        abort_if($organization === null, 404);

        return $apiSuccess($organization->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'tenancyUpdateOrganization',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Update one organization',
        'responses' => [
            '200' => [
                'description' => 'Organization updated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Organization not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160'],
            'default_locale' => ['required', 'string', 'in:en,es,fr,de'],
            'default_timezone' => ['required', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/organizations/{organizationId}/archive', function (
        string $organizationId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        abort_unless($tenancy->archiveOrganization($organizationId), 404);

        return $apiSuccess([
            'organization_id' => $organizationId,
            'archived' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'tenancyArchiveOrganization',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Archive one organization',
        'responses' => [
            '200' => [
                'description' => 'Organization archived',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Organization not found',
            ],
        ],
        'request_rules' => [
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/organizations/{organizationId}/activate', function (
        string $organizationId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        abort_unless($tenancy->activateOrganization($organizationId), 404);

        return $apiSuccess([
            'organization_id' => $organizationId,
            'activated' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'tenancyActivateOrganization',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Activate one organization',
        'responses' => [
            '200' => [
                'description' => 'Organization activated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Organization not found',
            ],
        ],
        'request_rules' => [
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/scopes', function (
        Request $request,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('scopes', 'slug')->where(fn ($query) => $query->where('organization_id', $request->input('organization_id'))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = $tenancy->createScope($validated);

        return $apiSuccess($scope->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'tenancyCreateScope',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create one organization scope',
        'responses' => [
            '200' => [
                'description' => 'Scope created',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/scopes/{scopeId}', function (
        Request $request,
        string $scopeId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        $existingScope = DB::table('scopes')->where('id', $scopeId)->first(['organization_id']);
        abort_if($existingScope === null, 404);

        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('scopes', 'slug')
                    ->ignore($scopeId, 'id')
                    ->where(fn ($query) => $query->where('organization_id', $request->input('organization_id'))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = $tenancy->updateScope($scopeId, $validated);
        abort_if($scope === null, 404);

        return $apiSuccess($scope->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'tenancyUpdateScope',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Update one organization scope',
        'responses' => [
            '200' => [
                'description' => 'Scope updated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Scope not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/scopes/{scopeId}/archive', function (
        string $scopeId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        abort_unless($tenancy->archiveScope($scopeId), 404);

        return $apiSuccess([
            'scope_id' => $scopeId,
            'archived' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'tenancyArchiveScope',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Archive one scope',
        'responses' => [
            '200' => [
                'description' => 'Scope archived',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Scope not found',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/tenancy/scopes/{scopeId}/activate', function (
        string $scopeId,
        TenancyServiceInterface $tenancy,
    ) use ($apiSuccess) {
        abort_unless($tenancy->activateScope($scopeId), 404);

        return $apiSuccess([
            'scope_id' => $scopeId,
            'activated' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'tenancyActivateScope',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Activate one scope',
        'responses' => [
            '200' => [
                'description' => 'Scope activated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Scope not found',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.tenancy.manage');

    Route::post('/core/functional-actors', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $actor = $actors->createActor(
            provider: 'manual',
            kind: (string) $validated['kind'],
            displayName: (string) $validated['display_name'],
            organizationId: (string) $validated['organization_id'],
            scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
            metadata: [],
            createdByPrincipalId: $apiPrincipalId($request),
        );

        return $apiSuccess($actor->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'functionalActorsCreateActor',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create one functional actor profile',
        'responses' => [
            '200' => [
                'description' => 'Functional actor created',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'display_name' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'scope_id' => '/api/v1/automation-catalog/lookups/scopes/options',
        ],
    ])->middleware('core.permission:core.functional-actors.manage');

    Route::post('/core/functional-actors/links', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_principal_id' => ['required', 'string', 'max:120'],
            'organization_id' => ['required', 'string', 'max:64'],
        ]);

        abort_if($actors->findActor((string) $validated['actor_id']) === null, 404);

        $link = $actors->linkPrincipal(
            principalId: (string) $validated['subject_principal_id'],
            actorId: (string) $validated['actor_id'],
            organizationId: (string) $validated['organization_id'],
            linkedByPrincipalId: $apiPrincipalId($request),
        );

        return $apiSuccess($link->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'functionalActorsLinkPrincipal',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Link one principal to a functional actor profile',
        'responses' => [
            '200' => [
                'description' => 'Principal linked',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Actor not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_principal_id' => ['required', 'string', 'max:120'],
            'organization_id' => ['required', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'actor_id' => '/api/v1/lookups/actors/options',
            'subject_principal_id' => '/api/v1/lookups/principals/options',
        ],
    ])->middleware('core.permission:core.functional-actors.manage');

    Route::post('/core/functional-actors/assignments', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_key' => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        abort_if($actors->findActor((string) $validated['actor_id']) === null, 404);

        [$domainObjectType, $domainObjectId] = array_pad(explode('::', (string) $validated['subject_key'], 2), 2, null);
        if (! is_string($domainObjectType) || $domainObjectType === '' || ! is_string($domainObjectId) || $domainObjectId === '') {
            throw ValidationException::withMessages([
                'subject_key' => 'Choose a valid workspace item.',
            ]);
        }

        if ((string) $validated['assignment_type'] === 'owner') {
            $assignment = $actors->syncSingleAssignment(
                actorId: (string) $validated['actor_id'],
                domainObjectType: $domainObjectType,
                domainObjectId: $domainObjectId,
                assignmentType: (string) $validated['assignment_type'],
                organizationId: (string) $validated['organization_id'],
                scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
                metadata: ['source' => 'functional-actors-admin'],
                assignedByPrincipalId: $apiPrincipalId($request),
            );
        } else {
            $assignment = $actors->assignActor(
                actorId: (string) $validated['actor_id'],
                domainObjectType: $domainObjectType,
                domainObjectId: $domainObjectId,
                assignmentType: (string) $validated['assignment_type'],
                organizationId: (string) $validated['organization_id'],
                scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
                metadata: ['source' => 'functional-actors-admin'],
                assignedByPrincipalId: $apiPrincipalId($request),
            );
        }

        return $apiSuccess($assignment->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'functionalActorsCreateAssignment',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Assign one functional actor to a domain object',
        'responses' => [
            '200' => [
                'description' => 'Assignment saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Actor not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_key' => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:core.functional-actors.manage');

    Route::post('/core/object-access/assignments', function (
        Request $request,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_key' => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        abort_if($actors->findActor((string) $validated['actor_id']) === null, 404);

        [$domainObjectType, $domainObjectId] = array_pad(explode('::', (string) $validated['subject_key'], 2), 2, null);
        if (! is_string($domainObjectType) || $domainObjectType === '' || ! is_string($domainObjectId) || $domainObjectId === '') {
            throw ValidationException::withMessages([
                'subject_key' => 'Choose a valid workspace item.',
            ]);
        }

        if ((string) $validated['assignment_type'] === 'owner') {
            $assignment = $actors->syncSingleAssignment(
                actorId: (string) $validated['actor_id'],
                domainObjectType: $domainObjectType,
                domainObjectId: $domainObjectId,
                assignmentType: (string) $validated['assignment_type'],
                organizationId: (string) $validated['organization_id'],
                scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
                metadata: ['source' => 'object-access-admin'],
                assignedByPrincipalId: $apiPrincipalId($request),
            );
        } else {
            $assignment = $actors->assignActor(
                actorId: (string) $validated['actor_id'],
                domainObjectType: $domainObjectType,
                domainObjectId: $domainObjectId,
                assignmentType: (string) $validated['assignment_type'],
                organizationId: (string) $validated['organization_id'],
                scopeId: is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== '' ? $validated['scope_id'] : null,
                metadata: ['source' => 'object-access-admin'],
                assignedByPrincipalId: $apiPrincipalId($request),
            );
        }

        return $apiSuccess($assignment->toArray());
    })->defaults('_openapi', [
        'operation_id' => 'objectAccessCreateAssignment',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create one object-access assignment',
        'responses' => [
            '200' => [
                'description' => 'Object-access assignment saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Actor not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'actor_id' => ['required', 'string', 'max:120'],
            'subject_key' => ['required', 'string', 'max:255'],
            'assignment_type' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'actor_id' => '/api/v1/lookups/actors/options',
        ],
    ])->middleware('core.permission:core.functional-actors.manage');

    Route::post('/core/object-access/assignments/{assignmentId}/deactivate', function (
        Request $request,
        string $assignmentId,
        FunctionalActorServiceInterface $actors,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $record = DB::table('functional_assignments')->where('id', $assignmentId)->first();
        abort_if($record === null, 404);
        abort_if((string) ($record->organization_id ?? '') !== (string) $validated['organization_id'], 404);

        $actors->deactivateAssignment(
            assignmentId: $assignmentId,
            deactivatedByPrincipalId: $apiPrincipalId($request),
        );

        return $apiSuccess([
            'assignment_id' => $assignmentId,
            'deactivated' => true,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'objectAccessDeactivateAssignment',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Deactivate one object-access assignment',
        'responses' => [
            '200' => [
                'description' => 'Object-access assignment deactivated',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '404' => [
                'description' => 'Assignment not found',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.functional-actors.manage');

    Route::post('/core/notifications/settings', function (
        Request $request,
        NotificationMailSettingsRepository $settings,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'email_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:190', 'required_if:email_enabled,1'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:email_enabled,1'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'email:rfc', 'max:190', 'required_if:email_enabled,1'],
            'from_name' => ['nullable', 'string', 'max:190'],
            'reply_to_address' => ['nullable', 'email:rfc', 'max:190'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $principalId = $apiPrincipalId($request);
        $organizationId = (string) $validated['organization_id'];
        $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : null;

        $saved = $settings->upsert($organizationId, $validated, $principalId);

        $audit->record(new AuditRecordData(
            eventType: 'core.notifications.mail-settings.updated',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'notification-mail-settings',
            targetId: (string) ($saved['id'] ?? $organizationId),
            summary: [
                'email_enabled' => (bool) ($saved['email_enabled'] ?? false),
                'smtp_host' => is_string($saved['smtp_host'] ?? null) && $saved['smtp_host'] !== '' ? $saved['smtp_host'] : null,
                'smtp_port' => $saved['smtp_port'] ?? null,
                'smtp_encryption' => is_string($saved['smtp_encryption'] ?? null) && $saved['smtp_encryption'] !== '' ? $saved['smtp_encryption'] : 'none',
                'has_password' => (bool) ($saved['has_password'] ?? false),
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.notifications.mail-settings.updated',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'email_enabled' => (bool) ($saved['email_enabled'] ?? false),
                'has_password' => (bool) ($saved['has_password'] ?? false),
            ],
        ));

        return $apiSuccess($saved);
    })->defaults('_openapi', [
        'operation_id' => 'notificationsUpdateMailSettings',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Save outbound notification mail settings',
        'responses' => [
            '200' => [
                'description' => 'Mail settings saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'email_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:190'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'string', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:190'],
            'reply_to_address' => ['nullable', 'string', 'max:190'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.notifications.manage');

    Route::post('/core/notifications/test-email', function (
        Request $request,
        NotificationMailSettingsRepository $settings,
        OutboundNotificationMailer $mailer,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'recipient_principal_id' => [
                'required',
                'string',
                'max:120',
                Rule::exists('identity_local_users', 'principal_id')->where(
                    fn ($query) => $query->where('organization_id', $request->input('organization_id'))->where('is_active', true)
                ),
            ],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $organizationId = (string) $validated['organization_id'];
        $principalId = $apiPrincipalId($request);
        $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : null;

        $deliveryConfig = $settings->deliveryConfigForOrganization($organizationId);
        if ($deliveryConfig === null) {
            throw ValidationException::withMessages([
                'recipient_principal_id' => 'Enable and save outbound email before sending a test message.',
            ]);
        }

        $recipientEmail = DB::table('identity_local_users')
            ->where('principal_id', (string) $validated['recipient_principal_id'])
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->value('email');

        if (! is_string($recipientEmail) || trim($recipientEmail) === '') {
            throw ValidationException::withMessages([
                'recipient_principal_id' => 'The selected person does not have an email address.',
            ]);
        }

        $mailer->sendTestMessage($deliveryConfig, trim($recipientEmail), $organizationId);
        $settings->markTested($organizationId);

        $audit->record(new AuditRecordData(
            eventType: 'core.notifications.test-email.sent',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'notification-mail-settings',
            targetId: $organizationId,
            summary: [
                'recipient_principal_id' => $validated['recipient_principal_id'],
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.notifications.test-email.sent',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'recipient_principal_id' => $validated['recipient_principal_id'],
            ],
        ));

        return $apiSuccess([
            'sent' => true,
            'recipient_principal_id' => (string) $validated['recipient_principal_id'],
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'notificationsSendTestEmail',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Send a notification SMTP test email',
        'responses' => [
            '200' => [
                'description' => 'Test email sent',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'recipient_principal_id' => ['required', 'string', 'max:120'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
        'lookup_fields' => [
            'recipient_principal_id' => '/api/v1/lookups/principals/options',
        ],
    ])->middleware('core.permission:core.notifications.manage');

    Route::post('/core/notifications/templates', function (
        Request $request,
        NotificationTemplateRepository $templates,
        AuditTrailInterface $audit,
        EventBusInterface $events,
    ) use ($apiPrincipalId, $apiSuccess) {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:64', 'exists:organizations,id'],
            'notification_type' => ['required', 'string', 'max:190'],
            'is_active' => ['nullable', 'boolean'],
            'title_template' => ['nullable', 'string', 'max:1000'],
            'body_template' => ['nullable', 'string', 'max:10000'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ]);

        $organizationId = (string) $validated['organization_id'];
        $notificationType = (string) $validated['notification_type'];
        $principalId = $apiPrincipalId($request);
        $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
            ? $validated['scope_id']
            : null;

        $saved = $templates->upsert(
            organizationId: $organizationId,
            notificationType: $notificationType,
            data: $validated,
            updatedByPrincipalId: $principalId,
        );

        $audit->record(new AuditRecordData(
            eventType: 'core.notifications.templates.updated',
            outcome: 'success',
            originComponent: 'core',
            principalId: $principalId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            targetType: 'notification-template',
            targetId: (string) ($saved['id'] ?? $notificationType),
            summary: [
                'notification_type' => $notificationType,
                'is_active' => (bool) ($saved['is_active'] ?? false),
            ],
            executionOrigin: 'api',
        ));

        $events->publish(new PublicEvent(
            name: 'core.notifications.templates.updated',
            originComponent: 'core',
            organizationId: $organizationId,
            scopeId: $scopeId,
            payload: [
                'notification_type' => $notificationType,
                'is_active' => (bool) ($saved['is_active'] ?? false),
            ],
        ));

        return $apiSuccess($saved);
    })->defaults('_openapi', [
        'operation_id' => 'notificationsUpdateTemplate',
        'tags' => ['core'],
        'tag_descriptions' => [
            'core' => 'Core platform and capability endpoints.',
        ],
        'summary' => 'Create or update one notification template',
        'responses' => [
            '200' => [
                'description' => 'Notification template saved',
            ],
            '403' => [
                'description' => 'Caller is not authorized',
            ],
            '422' => [
                'description' => 'Validation failed',
            ],
        ],
        'request_rules' => [
            'organization_id' => ['required', 'string', 'max:64'],
            'notification_type' => ['required', 'string', 'max:190'],
            'is_active' => ['nullable', 'boolean'],
            'title_template' => ['nullable', 'string', 'max:1000'],
            'body_template' => ['nullable', 'string', 'max:10000'],
            'scope_id' => ['nullable', 'string', 'max:64'],
        ],
    ])->middleware('core.permission:core.notifications.manage');

    Route::get('/lookups/scopes/options', function (
        Request $request,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $query = trim((string) $request->query('q', ''));

        $rows = array_map(static fn ($scope): array => [
            'id' => $scope->id,
            'label' => $scope->name !== '' ? $scope->name : $scope->id,
            'organization_id' => $scope->organizationId,
        ], $context->scopes);

        if ($query !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($query): bool {
                return str_contains(strtolower((string) ($row['id'] ?? '')), strtolower($query))
                    || str_contains(strtolower((string) ($row['label'] ?? '')), strtolower($query));
            }));
        }

        return $apiSuccess($rows, [
            'organization_id' => $context->organization?->id,
        ]);
    })->defaults('_openapi', [
        'operation_id' => 'coreLookupScopes',
        'tags' => ['lookups'],
        'tag_descriptions' => [
            'lookups' => 'Lookup feeds for relation selectors and governed options.',
        ],
        'summary' => 'Resolve accessible scope options in current tenancy context',
        'responses' => [
            '200' => [
                'description' => 'Scope options',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
        ],
    ]);

    Route::get('/lookups/grants/targets/options', function (
        Request $request,
        TenancyServiceInterface $tenancy,
    ) use ($apiPrincipalId, $apiSuccess, $resolveTenancy) {
        $principalId = $apiPrincipalId($request);
        abort_unless(is_string($principalId) && $principalId !== '', 401);

        $context = $resolveTenancy($request, $tenancy, $principalId);
        $organizationId = $context->organization?->id;
        $scopeId = is_string($request->query('scope_id')) && $request->query('scope_id') !== ''
            ? (string) $request->query('scope_id')
            : null;
        $query = trim((string) $request->query('q', ''));
        $targetType = strtolower((string) $request->query('target_type', 'principal'));

        if (! in_array($targetType, ['principal', 'membership'], true)) {
            throw ValidationException::withMessages([
                'target_type' => 'Choose either principal or membership.',
            ]);
        }

        if (! is_string($organizationId) || $organizationId === '') {
            return $apiSuccess([], [
                'organization_id' => null,
                'scope_id' => $scopeId,
                'target_type' => $targetType,
            ]);
        }

        if ($targetType === 'membership') {
            $hasIdentityLocalUsers = Schema::hasTable('identity_local_users');
            $membershipsQuery = DB::table('memberships')
                ->when($scopeId !== null && Schema::hasTable('membership_scope'), function ($builder) use ($scopeId) {
                    $builder->where(function ($query) use ($scopeId) {
                        $query->whereExists(function ($scopeQuery) use ($scopeId) {
                            $scopeQuery->selectRaw('1')
                                ->from('membership_scope')
                                ->whereColumn('membership_scope.membership_id', 'memberships.id')
                                ->where('membership_scope.scope_id', $scopeId);
                        })->orWhereNotExists(function ($scopeQuery) {
                            $scopeQuery->selectRaw('1')
                                ->from('membership_scope')
                                ->whereColumn('membership_scope.membership_id', 'memberships.id');
                        });
                    });
                })
                ->where('memberships.organization_id', $organizationId)
                ->where('memberships.is_active', true);

            if ($hasIdentityLocalUsers) {
                $membershipsQuery
                    ->leftJoin('identity_local_users', function ($join) {
                        $join->on('identity_local_users.principal_id', '=', 'memberships.principal_id')
                            ->where('identity_local_users.is_active', true);
                    })
                    ->when($query !== '', function ($builder) use ($query) {
                        $builder->where(function ($inner) use ($query) {
                            $inner->where('memberships.id', 'like', '%'.$query.'%')
                                ->orWhere('memberships.principal_id', 'like', '%'.$query.'%')
                                ->orWhere('identity_local_users.name', 'like', '%'.$query.'%')
                                ->orWhere('identity_local_users.email', 'like', '%'.$query.'%');
                        });
                    })
                    ->orderBy('identity_local_users.name');
            } else {
                $membershipsQuery->when($query !== '', function ($builder) use ($query) {
                    $builder->where(function ($inner) use ($query) {
                        $inner->where('memberships.id', 'like', '%'.$query.'%')
                            ->orWhere('memberships.principal_id', 'like', '%'.$query.'%');
                    });
                });
            }

            $memberships = $membershipsQuery
                ->orderBy('memberships.id')
                ->limit(200)
                ->get([
                    'memberships.id',
                    'memberships.principal_id',
                    ...($hasIdentityLocalUsers ? ['identity_local_users.name', 'identity_local_users.email'] : []),
                ]);

            return $apiSuccess([
                ...$memberships->map(static function ($record): array {
                    $displayName = is_string($record->name ?? null) && trim($record->name) !== ''
                        ? trim((string) $record->name)
                        : trim((string) ($record->principal_id ?? 'Membership'));

                    return [
                        'id' => (string) $record->id,
                        'label' => $displayName,
                        'description' => is_string($record->email ?? null) && trim($record->email) !== ''
                            ? trim((string) $record->email)
                            : null,
                        'principal_id' => (string) $record->principal_id,
                    ];
                })->values()->all(),
            ], [
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'target_type' => $targetType,
            ]);
        }

        if (! Schema::hasTable('identity_local_users')) {
            return $apiSuccess([], [
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'target_type' => $targetType,
            ]);
        }

        $principals = DB::table('identity_local_users')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('principal_id', 'like', '%'.$query.'%')
                        ->orWhere('name', 'like', '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('principal_id')
            ->limit(200)
            ->get(['principal_id', 'name', 'email']);

        return $apiSuccess(
            $principals->map(static fn ($record): array => [
                'id' => (string) $record->principal_id,
                'label' => is_string($record->name ?? null) && trim($record->name) !== ''
                    ? trim((string) $record->name)
                    : (string) $record->principal_id,
                'description' => is_string($record->email ?? null) && trim($record->email) !== ''
                    ? trim((string) $record->email)
                    : null,
                'principal_id' => (string) $record->principal_id,
            ])->values()->all(),
            [
                'organization_id' => $organizationId,
                'scope_id' => $scopeId,
                'target_type' => $targetType,
            ],
        );
    })->defaults('_openapi', [
        'operation_id' => 'coreLookupGrantTargets',
        'tags' => ['lookups'],
        'tag_descriptions' => [
            'lookups' => 'Lookup feeds for relation selectors and governed options.',
        ],
        'summary' => 'Resolve grant target options for principals or memberships',
        'responses' => [
            '200' => [
                'description' => 'Grant target options',
            ],
            '401' => [
                'description' => 'Authentication required',
            ],
            '422' => [
                'description' => 'Invalid query',
            ],
        ],
    ]);

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

});
