<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PymeSec\Plugins\AutomationCatalog\AutomationCatalogRepository;
use PymeSec\Plugins\AutomationCatalog\AutomationOutputMappingDeliveryService;
use PymeSec\Plugins\AutomationCatalog\AutomationPackageRepositorySyncService;
use PymeSec\Plugins\AutomationCatalog\AutomationPackRuntimeService;

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
    ]))->with('status', __('Automation pack saved.'));
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
    ]))->with('status', __('Automation pack installed.'));
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
    ]))->with('status', __('Automation pack enabled.'));
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
    ]))->with('status', __('Automation pack disabled.'));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.disable');

Route::post('/plugins/automation-catalog/{packId}/uninstall', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->uninstallPack($packId) === false, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Automation pack uninstalled and removed.'));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.uninstall');

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
    ]))->with('status', __('Automation health updated.'));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.health.update');

Route::post('/plugins/automation-catalog/{packId}/schedule', function (Request $request, string $packId, AutomationCatalogRepository $repository) {
    $validated = $request->validate([
        'runtime_schedule_enabled' => ['nullable', 'boolean'],
        'runtime_schedule_cron' => ['nullable', 'string', 'max:120'],
        'runtime_schedule_timezone' => [
            'nullable',
            'string',
            'max:64',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                if (! in_array($value, timezone_identifiers_list(), true)) {
                    $fail('Timezone must be a valid IANA timezone identifier.');
                }
            },
        ],
    ]);

    $scheduleEnabled = (bool) ($validated['runtime_schedule_enabled'] ?? false);
    $scheduleCron = trim((string) ($validated['runtime_schedule_cron'] ?? ''));
    $scheduleTimezone = trim((string) ($validated['runtime_schedule_timezone'] ?? ''));

    if ($scheduleEnabled && $scheduleCron === '') {
        throw ValidationException::withMessages([
            'runtime_schedule_cron' => __('Cron expression is required when runtime schedule is enabled.'),
        ]);
    }

    if ($scheduleEnabled && $scheduleTimezone === '') {
        $scheduleTimezone = 'UTC';
    }

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    abort_if($repository->updatePackRuntimeSchedule($packId, [
        'runtime_schedule_enabled' => $scheduleEnabled,
        'runtime_schedule_cron' => $scheduleCron !== '' ? $scheduleCron : null,
        'runtime_schedule_timezone' => $scheduleTimezone !== '' ? $scheduleTimezone : null,
        'runtime_schedule_last_slot' => null,
    ]) === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Runtime schedule updated.'));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.schedule.update');

Route::post('/plugins/automation-catalog/{packId}/run', function (
    Request $request,
    string $packId,
    AutomationPackRuntimeService $runtime,
) {
    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $run = $runtime->runPack(
        packId: $packId,
        triggerMode: 'manual',
        principalId: $principalId,
        membershipId: is_string($membershipId) && $membershipId !== '' ? $membershipId : null,
    );
    abort_if($run === null, 404);

    $status = (string) ($run['status'] ?? 'failed');
    $summary = sprintf(
        'Runtime %s · total %s · ok %s · fail %s · skip %s',
        $status,
        (string) ($run['total_mappings'] ?? '0'),
        (string) ($run['success_count'] ?? '0'),
        (string) ($run['failed_count'] ?? '0'),
        (string) ($run['skipped_count'] ?? '0'),
    );

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', $summary);
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.run');

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
    ]))->with('status', __('Automation package repository saved.'));
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
        ]))->with('status', __('Official repository is not configured. Set repository URL/sign/public key in plugin config.'));
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
            __('Official repository installed and refreshed: %d releases and %d latest pack rows.'),
            (int) ($result['release_rows'] ?? 0),
            (int) ($result['latest_rows'] ?? 0),
        );
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult((string) $repositoryRecord['id'], 'failed', $exception->getMessage());
        $statusMessage = sprintf(
            __('Official repository installed but refresh failed: %s'),
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
            __('Repository refreshed: %d releases and %d latest pack rows.'),
            (int) ($result['release_rows'] ?? 0),
            (int) ($result['latest_rows'] ?? 0),
        );
    } catch (Throwable $exception) {
        $repository->markRepositorySyncResult($repositoryId, 'failed', $exception->getMessage());
        $statusMessage = sprintf(__('Repository refresh failed: %s'), $exception->getMessage());
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
        'target_binding_mode' => ['nullable', 'string', Rule::in(['explicit', 'scope'])],
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
        'target_subject_id' => ['nullable', 'string', 'max:80', 'required_if:target_binding_mode,explicit'],
        'target_scope_id' => ['nullable', 'string', 'max:64'],
        'target_tags' => ['nullable', 'string', 'max:600'],
        'posture_propagation_policy' => ['nullable', 'string', Rule::in(['disabled', 'status-only'])],
        'execution_mode' => ['nullable', 'string', Rule::in(['both', 'runtime-only', 'manual-only'])],
        'on_fail_policy' => ['nullable', 'string', Rule::in(['no-op', 'raise-finding', 'raise-finding-and-action'])],
        'evidence_policy' => ['nullable', 'string', Rule::in(['always', 'on-fail', 'on-change'])],
        'runtime_retry_max_attempts' => ['nullable', 'integer', 'min:0', 'max:5'],
        'runtime_retry_backoff_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
        'runtime_max_targets' => ['nullable', 'integer', 'min:1', 'max:2000'],
        'runtime_payload_max_kb' => ['nullable', 'integer', 'min:0', 'max:10240'],
        'workflow_key' => ['nullable', 'string', 'max:180', 'required_if:mapping_kind,workflow-transition'],
        'transition_key' => ['nullable', 'string', 'max:80', 'required_if:mapping_kind,workflow-transition'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $bindingMode = is_string($validated['target_binding_mode'] ?? null) && $validated['target_binding_mode'] !== ''
        ? (string) $validated['target_binding_mode']
        : 'explicit';

    if ($bindingMode === 'scope' && ! in_array((string) $validated['target_subject_type'], ['asset', 'risk'], true)) {
        throw ValidationException::withMessages([
            'target_subject_type' => __('Scope binding mode currently supports only asset and risk targets.'),
        ]);
    }

    if ($bindingMode === 'explicit' && trim((string) ($validated['target_subject_id'] ?? '')) === '') {
        throw ValidationException::withMessages([
            'target_subject_id' => __('Target subject id is required in explicit binding mode.'),
        ]);
    }

    $posturePolicy = is_string($validated['posture_propagation_policy'] ?? null) && $validated['posture_propagation_policy'] !== ''
        ? (string) $validated['posture_propagation_policy']
        : 'disabled';
    $executionMode = is_string($validated['execution_mode'] ?? null) && $validated['execution_mode'] !== ''
        ? (string) $validated['execution_mode']
        : 'both';
    $onFailPolicy = is_string($validated['on_fail_policy'] ?? null) && $validated['on_fail_policy'] !== ''
        ? (string) $validated['on_fail_policy']
        : 'no-op';
    $evidencePolicy = is_string($validated['evidence_policy'] ?? null) && $validated['evidence_policy'] !== ''
        ? (string) $validated['evidence_policy']
        : 'always';

    if ($posturePolicy !== 'disabled' && ! in_array((string) $validated['target_subject_type'], ['asset', 'risk'], true)) {
        throw ValidationException::withMessages([
            'posture_propagation_policy' => __('Posture propagation currently supports only asset and risk targets.'),
        ]);
    }

    $rawSelectorTags = collect(explode(',', (string) ($validated['target_tags'] ?? '')))
        ->map(static fn (string $value): string => trim($value))
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();
    $selectorTags = [];
    $invalidSelectorTags = [];

    foreach ($rawSelectorTags as $tag) {
        if (! str_contains($tag, ':')) {
            $invalidSelectorTags[] = $tag;

            continue;
        }

        [$rawKey, $rawValue] = array_pad(explode(':', $tag, 2), 2, '');
        $key = trim((string) $rawKey);
        $value = trim((string) $rawValue);

        if ($key === '' || $value === '') {
            $invalidSelectorTags[] = $tag;

            continue;
        }

        $selectorTags[] = $key.':'.$value;
    }

    if ($invalidSelectorTags !== []) {
        throw ValidationException::withMessages([
            'target_tags' => sprintf(
                __('Invalid selector tag format for: %s. Use key:value (comma-separated).'),
                implode(', ', $invalidSelectorTags),
            ),
        ]);
    }

    $principalId = (string) $request->input('principal_id', 'principal-org-a');
    $membershipId = $request->input('membership_id');
    $organizationId = (string) $request->input('organization_id', 'org-a');
    $scopeId = $request->input('scope_id');

    $mapping = $repository->createOutputMapping($packId, [
        ...$validated,
        'target_binding_mode' => $bindingMode,
        'posture_propagation_policy' => $posturePolicy,
        'execution_mode' => $executionMode,
        'on_fail_policy' => $onFailPolicy,
        'evidence_policy' => $evidencePolicy,
        'runtime_retry_max_attempts' => (int) ($validated['runtime_retry_max_attempts'] ?? 0),
        'runtime_retry_backoff_ms' => (int) ($validated['runtime_retry_backoff_ms'] ?? 0),
        'runtime_max_targets' => (int) ($validated['runtime_max_targets'] ?? 200),
        'runtime_payload_max_kb' => (int) ($validated['runtime_payload_max_kb'] ?? 512),
        'target_selector' => [
            'tags' => $selectorTags,
        ],
    ], $principalId);
    abort_if($mapping === null, 404);

    return redirect()->route('core.shell.index', array_filter([
        'menu' => 'plugin.automation-catalog.root',
        'pack_id' => $packId,
        'principal_id' => $principalId,
        'organization_id' => $organizationId,
        'scope_id' => is_string($scopeId) && $scopeId !== '' ? $scopeId : null,
        'locale' => $request->input('locale', 'en'),
        'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
    ]))->with('status', __('Automation output mapping saved.'));
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

    if (($mapping['target_binding_mode'] ?? 'explicit') === 'scope') {
        return redirect()->route('core.shell.index', array_filter([
            'menu' => 'plugin.automation-catalog.root',
            'pack_id' => $packId,
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        ]))->with('status', __('Scope resolver mappings execute from pack runtime. Use Run now (or scheduled runtime) instead.'));
    }

    if (($mapping['execution_mode'] ?? 'both') === 'runtime-only') {
        return redirect()->route('core.shell.index', array_filter([
            'menu' => 'plugin.automation-catalog.root',
            'pack_id' => $packId,
            'principal_id' => $principalId,
            'organization_id' => $organizationId,
            'scope_id' => $scopeId,
            'locale' => $request->input('locale', 'en'),
            'membership_ids' => is_string($membershipId) && $membershipId !== '' ? [$membershipId] : null,
        ]))->with('status', __('This mapping is runtime-only. Execute it from pack runtime.'));
    }

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
    ]))->with('status', $result['status'] === 'success' ? $result['message'] : sprintf(__('Mapping failed: %s'), $result['message']));
})->middleware('core.permission:plugin.automation-catalog.packs.manage')->name('plugin.automation-catalog.output-mappings.apply');
