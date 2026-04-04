<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AutomationPackageRepositorySyncService
{
    public function __construct(
        private readonly AutomationCatalogRepository $repository,
    ) {}

    /**
     * @param  array<string, string>  $repositoryRecord
     * @return array{release_rows: int, latest_rows: int}
     */
    public function sync(array $repositoryRecord): array
    {
        $repositoryUrl = trim((string) ($repositoryRecord['repository_url'] ?? ''));
        $repositorySignUrl = trim((string) ($repositoryRecord['repository_sign_url'] ?? ''));
        $publicKeyPem = trim((string) ($repositoryRecord['public_key_pem'] ?? ''));
        $repositoryId = (string) ($repositoryRecord['id'] ?? '');
        $organizationId = (string) ($repositoryRecord['organization_id'] ?? '');
        $scopeId = ($repositoryRecord['scope_id'] ?? '') !== '' ? (string) $repositoryRecord['scope_id'] : null;

        if ($repositoryId === '' || $organizationId === '' || $repositoryUrl === '' || $repositorySignUrl === '' || $publicKeyPem === '') {
            throw new RuntimeException('Repository metadata is incomplete.');
        }

        $repositoryResponse = Http::timeout(20)->acceptJson()->get($repositoryUrl);

        if (! $repositoryResponse->successful()) {
            throw new RuntimeException(sprintf('Repository fetch failed: HTTP %d.', $repositoryResponse->status()));
        }

        $repositoryJson = (string) $repositoryResponse->body();
        $signResponse = Http::timeout(20)->get($repositorySignUrl);

        if (! $signResponse->successful()) {
            throw new RuntimeException(sprintf('Repository signature fetch failed: HTTP %d.', $signResponse->status()));
        }

        $repositorySignature = trim((string) $signResponse->body());

        $this->verifyRepositorySignature($repositoryJson, $repositorySignature, $publicKeyPem);

        $payload = json_decode($repositoryJson, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Repository payload is not valid JSON.');
        }

        $packs = $this->normalizePacks($payload, $repositoryUrl);

        if ($packs === []) {
            throw new RuntimeException('Repository payload does not include valid packs.');
        }

        return $this->repository->replaceRepositoryReleases(
            repositoryId: $repositoryId,
            organizationId: $organizationId,
            scopeId: $scopeId,
            packs: $packs,
        );
    }

    private function verifyRepositorySignature(string $repositoryJson, string $repositorySignature, string $publicKeyPem): void
    {
        if (! function_exists('openssl_verify')) {
            throw new RuntimeException('OpenSSL extension is required for repository signature validation.');
        }

        $decodedSignature = base64_decode($repositorySignature, true);

        if (! is_string($decodedSignature) || $decodedSignature === '') {
            throw new RuntimeException('Repository signature is not valid base64.');
        }

        $verifyResult = openssl_verify($repositoryJson, $decodedSignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($verifyResult !== 1) {
            throw new RuntimeException('Repository signature validation failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizePacks(array $payload, string $repositoryUrl): array
    {
        $packs = is_array($payload['packs'] ?? null) ? $payload['packs'] : [];
        $normalized = [];

        foreach ($packs as $pack) {
            if (! is_array($pack)) {
                continue;
            }

            $packKey = trim((string) ($pack['id'] ?? ($pack['pack_key'] ?? '')));
            $packName = trim((string) ($pack['name'] ?? ''));
            $packDescription = trim((string) ($pack['description'] ?? ''));
            $latestVersion = trim((string) ($pack['latest_version'] ?? ''));
            $versions = is_array($pack['versions'] ?? null) ? $pack['versions'] : [];
            $normalizedVersions = [];

            foreach ($versions as $versionRow) {
                if (! is_array($versionRow)) {
                    continue;
                }

                $version = trim((string) ($versionRow['version'] ?? ''));
                $artifactUrl = trim((string) ($versionRow['artifact_url'] ?? ($versionRow['zip_url'] ?? ($versionRow['package_url'] ?? ''))));

                if ($version === '' || $artifactUrl === '') {
                    continue;
                }

                $resolvedArtifactUrl = $this->resolveUrl($repositoryUrl, $artifactUrl);
                $artifactSignUrl = trim((string) ($versionRow['artifact_signature_url'] ?? ($versionRow['signature_url'] ?? '')));
                $packManifestUrl = trim((string) ($versionRow['pack_manifest_url'] ?? ($versionRow['manifest_url'] ?? '')));

                $normalizedVersions[] = [
                    'version' => $version,
                    'artifact_url' => $resolvedArtifactUrl,
                    'artifact_signature_url' => $artifactSignUrl !== '' ? $this->resolveUrl($repositoryUrl, $artifactSignUrl) : $resolvedArtifactUrl.'.sign',
                    'artifact_sha256' => trim((string) ($versionRow['artifact_sha256'] ?? ($versionRow['sha256'] ?? ''))),
                    'pack_manifest_url' => $packManifestUrl !== '' ? $this->resolveUrl($repositoryUrl, $packManifestUrl) : null,
                    'capabilities' => is_array($versionRow['capabilities'] ?? null) ? $versionRow['capabilities'] : [],
                    'permissions_requested' => is_array($versionRow['permissions_requested'] ?? null) ? $versionRow['permissions_requested'] : [],
                ];
            }

            if ($packKey === '' || $packName === '' || $normalizedVersions === []) {
                continue;
            }

            if ($latestVersion === '') {
                $latestVersion = (string) ($normalizedVersions[0]['version'] ?? '');
            }

            $normalized[] = [
                'pack_key' => $packKey,
                'pack_name' => $packName,
                'pack_description' => $packDescription,
                'latest_version' => $latestVersion,
                'versions' => $normalizedVersions,
            ];
        }

        return $normalized;
    }

    private function resolveUrl(string $repositoryUrl, string $candidate): string
    {
        if (preg_match('/^https?:\\/\\//i', $candidate) === 1) {
            return $candidate;
        }

        $base = preg_replace('/\\/[^\\/]*$/', '', $repositoryUrl);
        $base = is_string($base) ? rtrim($base, '/') : rtrim($repositoryUrl, '/');

        return $base.'/'.ltrim($candidate, '/');
    }
}
