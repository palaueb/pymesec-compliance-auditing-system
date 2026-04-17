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
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\ReferenceData\ReferenceCatalogService;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Plugins\AssessmentsAudits\AssessmentReferenceData;
use PymeSec\Plugins\AssessmentsAudits\AssessmentsAuditsRepository;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;

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

return [
    'apiPrincipalId' => $apiPrincipalId,
    'apiSuccess' => $apiSuccess,
    'resolveTenancy' => $resolveTenancy,
    'resolveEffectivePermissionKeys' => $resolveEffectivePermissionKeys,
    'assetCreateContractRules' => $assetCreateContractRules,
    'assetUpdateContractRules' => $assetUpdateContractRules,
    'riskCreateContractRules' => $riskCreateContractRules,
    'riskUpdateContractRules' => $riskUpdateContractRules,
    'controlCreateContractRules' => $controlCreateContractRules,
    'controlUpdateContractRules' => $controlUpdateContractRules,
    'assessmentCreateContractRules' => $assessmentCreateContractRules,
    'assessmentUpdateContractRules' => $assessmentUpdateContractRules,
    'findingCreateContractRules' => $findingCreateContractRules,
    'findingUpdateContractRules' => $findingUpdateContractRules,
    'remediationActionCreateContractRules' => $remediationActionCreateContractRules,
    'remediationActionUpdateContractRules' => $remediationActionUpdateContractRules,
    'assessmentReviewUpdateContractRules' => $assessmentReviewUpdateContractRules,
    'assetRuntimeRules' => $assetRuntimeRules,
    'riskRuntimeRules' => $riskRuntimeRules,
    'controlRuntimeRules' => $controlRuntimeRules,
    'assessmentCreateRuntimeRules' => $assessmentCreateRuntimeRules,
    'assessmentUpdateRuntimeRules' => $assessmentUpdateRuntimeRules,
    'findingRuntimeRules' => $findingRuntimeRules,
    'remediationActionRuntimeRules' => $remediationActionRuntimeRules,
    'assessmentReviewRuntimeRules' => $assessmentReviewRuntimeRules,
];
