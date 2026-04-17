<?php

use App\Http\Requests\Api\V1\ControlCreateRequest;
use App\Http\Requests\Api\V1\ControlUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PymeSec\Core\Artifacts\ArtifactUploadData;
use PymeSec\Core\Artifacts\Contracts\ArtifactServiceInterface;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;
use PymeSec\Core\ObjectAccess\ObjectAccessService;
use PymeSec\Core\Principals\PrincipalReference;
use PymeSec\Core\Tenancy\Contracts\TenancyServiceInterface;
use PymeSec\Core\Workflows\Contracts\WorkflowServiceInterface;
use PymeSec\Core\Workflows\WorkflowExecutionContext;
use PymeSec\Plugins\ControlsCatalog\ControlsCatalogRepository;

$apiContext = require dirname(__DIR__, 3).'/core/routes/api_context.php';
extract($apiContext, EXTR_SKIP);

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

Route::get('/controls/requirements/options', function (
    Request $request,
    ControlsCatalogRepository $controls,
) use ($apiSuccess) {
    $organizationId = (string) $request->input('organization_id');
    abort_if($organizationId === '', 422);

    $frameworkId = $request->input('framework_id');
    $requirements = $controls->requirements(
        organizationId: $organizationId,
        frameworkId: is_string($frameworkId) && $frameworkId !== '' ? $frameworkId : null,
    );

    return $apiSuccess(array_values(array_map(static fn (array $requirement): array => [
        'id' => (string) ($requirement['id'] ?? ''),
        'label' => trim((string) ($requirement['code'] ?? '')) !== ''
            ? sprintf('%s · %s', (string) $requirement['code'], (string) $requirement['title'])
            : (string) ($requirement['title'] ?? ''),
        'framework_id' => (string) ($requirement['framework_id'] ?? ''),
        'framework_code' => (string) ($requirement['framework_code'] ?? ''),
        'framework_name' => (string) ($requirement['framework_name'] ?? ''),
    ], $requirements)));
})->defaults('_openapi', [
    'operation_id' => 'controlsCatalogListRequirementOptions',
    'tags' => ['controls'],
    'tag_descriptions' => [
        'controls' => 'Controls catalog and mapping API surface.',
    ],
    'summary' => 'List requirement options for control-to-requirement mapping forms',
    'responses' => [
        '200' => [
            'description' => 'Requirement options',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '422' => [
            'description' => 'Organization context is required',
        ],
    ],
])->middleware('core.permission:plugin.controls-catalog.controls.view');

Route::post('/controls/frameworks', function (
    Request $request,
    ControlsCatalogRepository $controls,
) use ($apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'code' => ['required', 'string', 'max:40'],
        'name' => ['required', 'string', 'max:120'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    return $apiSuccess($controls->createFramework($validated));
})->defaults('_openapi', [
    'operation_id' => 'controlsCatalogCreateFramework',
    'tags' => ['controls'],
    'tag_descriptions' => [
        'controls' => 'Controls catalog and mapping API surface.',
    ],
    'summary' => 'Create one custom framework',
    'responses' => [
        '200' => [
            'description' => 'Framework created',
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
        'code' => ['required', 'string', 'max:40'],
        'name' => ['required', 'string', 'max:120'],
        'description' => ['nullable', 'string', 'max:500'],
    ],
])->middleware('core.permission:plugin.controls-catalog.controls.manage');

Route::post('/controls/frameworks/{frameworkId}/adoption', function (
    Request $request,
    string $frameworkId,
    ControlsCatalogRepository $controls,
    ArtifactServiceInterface $artifacts,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'status' => ['required', 'in:active,in-progress,inactive'],
        'target_level' => ['nullable', 'in:basic,medium,high'],
        'adopted_at' => ['nullable', 'date'],
        'mandate_document' => ['nullable', 'file', 'max:10240'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    $scopeId = is_string($validated['scope_id'] ?? null) && $validated['scope_id'] !== ''
        ? (string) $validated['scope_id']
        : null;
    $existingAdoption = $controls->findFrameworkAdoption(
        organizationId: (string) $validated['organization_id'],
        frameworkId: $frameworkId,
        scopeId: $scopeId,
    );
    $hasExistingMandate = false;

    if ($existingAdoption !== null) {
        $hasExistingMandate = $artifacts->latest(1, array_filter([
            'subject_type' => 'framework-adoption',
            'subject_id' => $existingAdoption['id'],
            'artifact_type' => 'mandate-document',
            'organization_id' => (string) $validated['organization_id'],
            'scope_id' => $scopeId,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '')) !== [];
    }

    if (($validated['status'] ?? null) === 'active' && ! $request->hasFile('mandate_document') && ! $hasExistingMandate) {
        throw ValidationException::withMessages([
            'mandate_document' => 'Upload the signed mandate document before activating framework adoption.',
        ]);
    }

    $adoption = $controls->upsertFrameworkAdoption(
        organizationId: (string) $validated['organization_id'],
        frameworkId: $frameworkId,
        data: $validated,
    );
    abort_if($adoption === null, 404);

    $mandateArtifact = null;
    if ($request->hasFile('mandate_document')) {
        $framework = $controls->findFramework((string) $validated['organization_id'], $frameworkId);
        $membershipId = $request->input('membership_id');

        $mandateArtifact = $artifacts->store(new ArtifactUploadData(
            ownerComponent: 'controls-catalog',
            subjectType: 'framework-adoption',
            subjectId: $adoption['id'],
            artifactType: 'mandate-document',
            label: 'Signed mandate document',
            file: $validated['mandate_document'],
            principalId: $principalId,
            membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
            organizationId: (string) $validated['organization_id'],
            scopeId: $scopeId,
            metadata: [
                'plugin' => 'controls-catalog',
                'framework_id' => $frameworkId,
                'framework_name' => $framework['name'] ?? $frameworkId,
                'adoption_status' => $validated['status'],
            ],
        ))->toArray();
    }

    return $apiSuccess([
        'adoption' => $adoption,
        'mandate_artifact' => $mandateArtifact,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'controlsCatalogUpsertFrameworkAdoption',
    'tags' => ['controls'],
    'tag_descriptions' => [
        'controls' => 'Controls catalog and mapping API surface.',
    ],
    'summary' => 'Create or update one framework adoption profile',
    'responses' => [
        '200' => [
            'description' => 'Framework adoption saved',
        ],
        '401' => [
            'description' => 'Authentication required',
        ],
        '403' => [
            'description' => 'Caller is not authorized',
        ],
        '404' => [
            'description' => 'Framework or adoption context not found',
        ],
        '422' => [
            'description' => 'Validation failed',
        ],
    ],
    'request_body' => [
        'required' => true,
        'content' => [
            'multipart/form-data' => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['organization_id', 'status'],
                    'properties' => [
                        'organization_id' => ['type' => 'string'],
                        'scope_id' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'in-progress', 'inactive']],
                        'target_level' => ['type' => 'string', 'enum' => ['basic', 'medium', 'high']],
                        'adopted_at' => ['type' => 'string', 'format' => 'date'],
                        'mandate_document' => ['type' => 'string', 'format' => 'binary'],
                    ],
                ],
            ],
        ],
    ],
])->middleware('core.permission:plugin.controls-catalog.controls.manage');

Route::post('/controls/requirements', function (
    Request $request,
    ControlsCatalogRepository $controls,
) use ($apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'framework_id' => ['required', 'string', 'max:64'],
        'code' => ['required', 'string', 'max:60'],
        'title' => ['required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:500'],
    ]);

    return $apiSuccess($controls->createRequirement($validated));
})->defaults('_openapi', [
    'operation_id' => 'controlsCatalogCreateRequirement',
    'tags' => ['controls'],
    'tag_descriptions' => [
        'controls' => 'Controls catalog and mapping API surface.',
    ],
    'summary' => 'Create one requirement inside a framework',
    'responses' => [
        '200' => [
            'description' => 'Requirement created',
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
        'framework_id' => ['required', 'string', 'max:64'],
        'code' => ['required', 'string', 'max:60'],
        'title' => ['required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:500'],
    ],
    'lookup_fields' => [
        'framework_id' => '/api/v1/lookups/frameworks/options',
    ],
])->middleware('core.permission:plugin.controls-catalog.controls.manage');

Route::post('/controls/{controlId}/requirements', function (
    Request $request,
    string $controlId,
    ControlsCatalogRepository $controls,
    ObjectAccessService $objectAccess,
) use ($apiPrincipalId, $apiSuccess) {
    $validated = $request->validate([
        'organization_id' => ['required', 'string', 'max:64'],
        'requirement_id' => ['required', 'string', 'max:64'],
        'coverage' => ['nullable', 'in:supports,partial,full'],
        'notes' => ['nullable', 'string', 'max:500'],
    ]);

    $principalId = $apiPrincipalId($request);
    abort_unless(is_string($principalId) && $principalId !== '', 401);
    abort_unless($objectAccess->canAccessObject(
        principalId: $principalId,
        organizationId: (string) $validated['organization_id'],
        scopeId: is_string($request->input('scope_id')) && $request->input('scope_id') !== ''
            ? (string) $request->input('scope_id')
            : null,
        domainObjectType: 'control',
        domainObjectId: $controlId,
    ), 403);

    $controls->attachRequirement(
        controlId: $controlId,
        requirementId: (string) $validated['requirement_id'],
        organizationId: (string) $validated['organization_id'],
        coverage: (string) ($validated['coverage'] ?? 'supports'),
        notes: $validated['notes'] ?? null,
    );

    return $apiSuccess([
        'control_id' => $controlId,
        'requirement_id' => (string) $validated['requirement_id'],
        'coverage' => (string) ($validated['coverage'] ?? 'supports'),
        'notes' => $validated['notes'] ?? null,
    ]);
})->defaults('_openapi', [
    'operation_id' => 'controlsCatalogAttachRequirement',
    'tags' => ['controls'],
    'tag_descriptions' => [
        'controls' => 'Controls catalog and mapping API surface.',
    ],
    'summary' => 'Attach one requirement mapping to a control',
    'responses' => [
        '200' => [
            'description' => 'Requirement mapping saved',
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
        'organization_id' => ['required', 'string', 'max:64'],
        'requirement_id' => ['required', 'string', 'max:64'],
        'coverage' => ['nullable', 'in:supports,partial,full'],
        'notes' => ['nullable', 'string', 'max:500'],
    ],
    'lookup_fields' => [
        'requirement_id' => '/api/v1/controls/requirements/options',
    ],
])->middleware('core.permission:plugin.controls-catalog.controls.manage');

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
