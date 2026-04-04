<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PymeSec\Plugins\AutomationCatalog\AutomationCatalogRepository;
use PymeSec\Plugins\AutomationCatalog\AutomationOutputMappingDeliveryService;
use PymeSec\Plugins\AutomationCatalog\AutomationPackageRepositorySyncService;

if (! function_exists('automationCatalogOfficialRepositoryPreset')) {
    /**
     * @return array{label: string, repository_url: string, repository_sign_url: string, trust_tier: string, public_key_pem: string}
     */
    function automationCatalogOfficialRepositoryPreset(): array
    {
        $config = config('plugins.automation_catalog.official_repository');
        $label = trim((string) (is_array($config) ? ($config['label'] ?? '') : ''));
        $repositoryUrl = trim((string) (is_array($config) ? ($config['url'] ?? '') : ''));
        $repositorySignUrl = trim((string) (is_array($config) ? ($config['sign_url'] ?? '') : ''));
        $trustTier = trim((string) (is_array($config) ? ($config['trust_tier'] ?? '') : ''));
        $publicKeyPem = trim((string) (is_array($config) ? ($config['public_key_pem'] ?? '') : ''));
        $publicKeyPath = trim((string) (is_array($config) ? ($config['public_key_path'] ?? '') : ''));

        if ($publicKeyPem === '' && $publicKeyPath !== '' && is_file($publicKeyPath)) {
            $publicKeyPem = trim((string) file_get_contents($publicKeyPath));
        }

        if ($publicKeyPem === '') {
            $publicKeyPem = automationCatalogOfficialRepositoryDefaultPublicKey();
        }

        if (str_contains($publicKeyPem, '\n')) {
            $publicKeyPem = str_replace('\n', "\n", $publicKeyPem);
        }

        if ($repositorySignUrl === '' && $repositoryUrl !== '') {
            $repositorySignUrl = rtrim($repositoryUrl, '/').'.sign';
        }

        return [
            'label' => $label !== '' ? $label : 'PymeSec Official Repository',
            'repository_url' => $repositoryUrl,
            'repository_sign_url' => $repositorySignUrl,
            'trust_tier' => in_array($trustTier, ['trusted-first-party', 'trusted-partner', 'community-reviewed', 'untrusted'], true)
                ? $trustTier
                : 'trusted-first-party',
            'public_key_pem' => $publicKeyPem,
        ];
    }
}

if (! function_exists('automationCatalogOfficialRepositoryDefaultPublicKey')) {
    function automationCatalogOfficialRepositoryDefaultPublicKey(): string
    {
        return <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwfsOim5JGN+PpVjZDPdZ
q35+S46276kE8RwT7eJz6zfZ3TrgDAIbX2h02AFK8HY2k8g0xm9IDPvjwDOAmGhU
GmOzStg3DLmxxlTvmX32UPFfc8vbNmbsggW+wLwh1lUGAYy6WJOfxg8JslpbVc3p
7xKsAgn68+ww3Lr9gvM0moHN3xzj+0JbqzVxEFzAgpR+BUgpiONLhauWBXOJ1AuO
fgyUk0hPQ/CskW2I+P5keqeJ66DqxwFH+G+VqnNhS0X8z61xVVBXWj96+nkLYSYI
M0+lQA9E7TpLjy9Am3LQIif/77N3+tqGHmoaGyIFrBuPbHd2WGUStzMkyUhZESRO
d8qiRiWV6iUk2aeFfYPKB+sVPWfiN/Dp/kEEX+yKcI6OT6DtHEacH5H106fhDKXY
jGCUKIs2TenzSiPDzMjQuX9y/tjqgFDKkYtBPfLBl0t8KRLjEI0ezlfiKcNWwBCu
ZuzK4SnX6aH72Wv3IkzsofxvsKjPu0nkQQ1efz1lcdACoL68FkJhvAhftiSPOjBz
xrAg5o6UJUSkClx2AsNCctAgXxnNVB/inzFXlHa3AsIwB9qY9q/hGWR44jDxV97l
4fBdFQyJM6th9diU0h+2+pol0IN/7RtLPPlR2rrNdqka1mX+U7zbvQlLo+KIE0yf
PdIw9rMdRdW+UuCpagE9m48CAwEAAQ==
-----END PUBLIC KEY-----
PEM;
    }
}

Route::get('/plugins/automation-catalog', function (Request $request, AutomationCatalogRepository $repository) {
    $organizationId = (string) $request->query('organization_id', 'org-a');
    $scopeId = $request->query('scope_id');

    return response()->json([
        'plugin' => 'automation-catalog',
        'packs' => $repository->all($organizationId, is_string($scopeId) ? $scopeId : null),
    ]);
})->name('plugin.automation-catalog.index');

Route::post('/plugins/automation-catalog', function (Request $request, AutomationCatalogRepository $repository) {
    $validated = $request->validate([
        'pack_key' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9][a-z0-9.-]*$/'],
        'name' => ['required', 'string', 'max:180'],
        'summary' => ['nullable', 'string', 'max:400'],
        'version' => ['nullable', 'string', 'max:64'],
        'provider_type' => ['required', 'string', Rule::in(['native', 'community', 'vendor', 'internal'])],
        'source_ref' => ['nullable', 'url', 'max:512'],
        'provenance_type' => ['required', 'string', Rule::in(['plugin', 'marketplace', 'git', 'manual'])],
        'scope_id' => ['nullable', 'string', 'max:64'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $automationPanel = $request->input('automation_panel');

    $pack = $repository->createPack([
        ...$validated,
        'organization_id' => $organizationId,
        'owner_principal_id' => $principalId,
    ]);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $pack['id'],
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($validated['scope_id'] ?? null) && ($validated['scope_id'] ?? '') !== '' ? $validated['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'automation_panel' => is_string($automationPanel) && $automationPanel !== '' ? $automationPanel : null,
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation pack saved.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.store');

Route::post('/plugins/automation-catalog/{packId}/install', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->installPack($packId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation pack installed.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.install');

Route::post('/plugins/automation-catalog/{packId}/enable', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->enablePack($packId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation pack enabled.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.enable');

Route::post('/plugins/automation-catalog/{packId}/disable', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->disablePack($packId, $principalId) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation pack disabled.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.disable');

Route::post('/plugins/automation-catalog/{packId}/health', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $validated = $request->validate([
        'health_state' => ['required', 'string', Rule::in(['unknown', 'healthy', 'degraded', 'failing'])],
        'last_failure_reason' => ['nullable', 'string', 'max:2000'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->updateHealth($packId, $validated) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation health updated.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.health.update');

Route::post('/plugins/automation-catalog/repositories', function (
    Request $request,
    AutomationCatalogRepository $repository,
) {
    $validated = $request->validate([
        'label' => ['required', 'string', 'max:180'],
        'repository_url' => ['required', 'url', 'max:1024'],
        'repository_sign_url' => ['nullable', 'url', 'max:1024'],
        'public_key_pem' => ['required', 'string', 'max:12000'],
        'trust_tier' => ['required', 'string', Rule::in(['trusted-first-party', 'trusted-partner', 'community-reviewed', 'untrusted'])],
        'scope_id' => ['nullable', 'string', 'max:64'],
        'is_enabled' => ['nullable', 'boolean'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $automationPanel = $request->input('automation_panel');

    $repository->saveRepository([
        ...$validated,
        'organization_id' => $organizationId,
        'created_by_principal_id' => $principalId,
        'updated_by_principal_id' => $principalId,
        'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
    ]);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($validated['scope_id'] ?? null) && ($validated['scope_id'] ?? '') !== '' ? $validated['scope_id'] : null,
        'locale' => $request->input('locale', 'en'),
        'automation_panel' => is_string($automationPanel) && $automationPanel !== '' ? $automationPanel : 'repository-editor',
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation package repository saved.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.repositories.store');

Route::post('/plugins/automation-catalog/repositories/install-official', function (
    Request $request,
    AutomationCatalogRepository $repository,
    AutomationPackageRepositorySyncService $syncService,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    $automationPanel = $request->input('automation_panel');
    $official = automationCatalogOfficialRepositoryPreset();

    if ($official['repository_url'] === '' || $official['repository_sign_url'] === '' || $official['public_key_pem'] === '') {
        return redirect()->route('core.shell.index', array_filter([
            'menu' => 'plugin.automation-catalog.root',
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
            'locale' => $request->input('locale', 'en'),
            'automation_panel' => is_string($automationPanel) && $automationPanel !== '' ? $automationPanel : 'repository-editor',
            'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        ]))->with('status', 'Official repository is not configured. Set repository URL/sign/public key in plugin config.');
    }

    $repositoryRecord = $repository->saveRepository([
        'label' => $official['label'],
        'repository_url' => $official['repository_url'],
        'repository_sign_url' => $official['repository_sign_url'],
        'public_key_pem' => $official['public_key_pem'],
        'trust_tier' => $official['trust_tier'],
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'organization_id' => $organizationId,
        'created_by_principal_id' => $principalId,
        'updated_by_principal_id' => $principalId,
        'is_enabled' => true,
    ]);

    try {
        $result = $syncService->sync($repositoryRecord);
        $repository->markRepositorySyncResult((string) $repositoryRecord['id'], 'success');
        $statusMessage = sprintf(
            'Official repository installed and refreshed: %d releases and %d latest pack rows.',
            (int) ($result['release_rows'] ?? 0),
            (int) ($result['latest_rows'] ?? 0),
        );
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult((string) $repositoryRecord['id'], 'failed', $exception->getMessage());
        $statusMessage = sprintf(
            'Official repository installed but refresh failed: %s',
            $exception->getMessage()
        );
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'automation_panel' => is_string($automationPanel) && $automationPanel !== '' ? $automationPanel : 'repository-editor',
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', $statusMessage);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.repositories.install-official');

Route::post('/plugins/automation-catalog/repositories/{repositoryId}/refresh', function (
    Request $request,
    string $repositoryId,
    AutomationCatalogRepository $repository,
    AutomationPackageRepositorySyncService $syncService,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');
    $automationPanel = $request->input('automation_panel');

    $repositoryRecord = $repository->findRepository($repositoryId);
    abort_if($repositoryRecord === null, 404);

    try {
        $result = $syncService->sync($repositoryRecord);
        $repository->markRepositorySyncResult($repositoryId, 'success');
        $statusMessage = sprintf(
            'Repository refreshed: %d releases and %d latest pack rows.',
            (int) ($result['release_rows'] ?? 0),
            (int) ($result['latest_rows'] ?? 0),
        );
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult($repositoryId, 'failed', $exception->getMessage());
        $statusMessage = sprintf('Repository refresh failed: %s', $exception->getMessage());
    }

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'automation_panel' => is_string($automationPanel) && $automationPanel !== '' ? $automationPanel : 'repository-editor',
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', $statusMessage);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.repositories.refresh');

Route::post('/plugins/automation-catalog/{packId}/output-mappings', function (
    Request $request,
    string $packId,
    AutomationCatalogRepository $repository,
) {
    $validated = $request->validate([
        'mapping_label' => ['required', 'string', 'max:180'],
        'mapping_kind' => ['required', 'string', Rule::in(['evidence-refresh', 'workflow-transition'])],
        'target_subject_type' => ['required', 'string', Rule::in([
            'asset',
            'control',
            'risk',
            'finding',
            'policy',
            'policy-exception',
            'privacy-data-flow',
            'privacy-processing-activity',
            'continuity-service',
            'continuity-plan',
            'recovery-plan',
            'assessment',
            'assessment-review',
            'vendor-review',
        ])],
        'target_subject_id' => ['required', 'string', 'max:80'],
        'workflow_key' => ['nullable', 'string', 'max:180', 'required_if:mapping_kind,workflow-transition'],
        'transition_key' => ['nullable', 'string', 'max:80', 'required_if:mapping_kind,workflow-transition'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $mapping = $repository->createOutputMapping($packId, $validated, $principalId);
    abort_if($mapping === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', 'Automation output mapping saved.');
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.output-mappings.store');

Route::post('/plugins/automation-catalog/{packId}/output-mappings/{mappingId}/apply', function (
    Request $request,
    string $packId,
    string $mappingId,
    AutomationCatalogRepository $repository,
    AutomationOutputMappingDeliveryService $delivery,
) {
    $validated = $request->validate([
        'existing_artifact_id' => ['nullable', 'string', 'max:64'],
        'output_file' => ['nullable', 'file', 'max:10240'],
        'evidence_kind' => ['nullable', 'string', Rule::in(['document', 'workpaper', 'snapshot', 'report', 'ticket', 'log-export', 'statement', 'other'])],
    ]);

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = is_string($request->input('scope_id')) && $request->input('scope_id') !== ''
        ? (string) $request->input('scope_id')
        : null;

    $pack = $repository->find($packId);
    $mapping = $repository->findOutputMapping($mappingId);
    abort_if($pack === null || $mapping === null || ($mapping['automation_pack_id'] ?? '') !== $packId, 404);

    $result = $delivery->deliver(
        mapping: $mapping,
        data: [
            ...$validated,
            'output_file' => $request->file('output_file'),
        ],
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
        organizationId: $organizationId,
        scopeId: $scopeId,
    );

    $repository->markOutputMappingDelivery($mappingId, $result['status'], $result['message']);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', $result['status'] === 'success' ? $result['message'] : sprintf('Mapping failed: %s', $result['message']));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.output-mappings.apply');
