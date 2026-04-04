<?php

namespace PymeSec\Plugins\AutomationCatalog;

use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
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

        $verificationKey = $this->resolveVerificationKey($publicKeyPem);
        $verifyResult = openssl_verify($repositoryJson, $decodedSignature, $verificationKey, OPENSSL_ALGO_SHA256);

        if ($verifyResult !== 1) {
            throw new RuntimeException('Repository signature validation failed.');
        }
    }

    private function resolveVerificationKey(string $rawPublicKey): OpenSSLAsymmetricKey|string
    {
        $candidate = trim(str_replace(["\r\n", "\r"], "\n", $rawPublicKey));
        if (str_contains($candidate, '\n')) {
            $candidate = str_replace('\n', "\n", $candidate);
        }

        if ($candidate === '') {
            throw new RuntimeException('Repository public key is empty.');
        }

        $asPem = $this->loadOpenSslPublicKey($candidate);
        if ($asPem !== false) {
            return $asPem;
        }

        $singleLine = preg_replace('/\s+/', ' ', $candidate);
        $singleLine = is_string($singleLine) ? trim($singleLine) : '';

        if (str_starts_with($singleLine, 'ssh-') && ! str_starts_with($singleLine, 'ssh-rsa ')) {
            $keyType = strtok($singleLine, ' ') ?: 'unknown';
            throw new RuntimeException(sprintf(
                'OpenSSH key type [%s] is not supported for repository signatures. Use ssh-rsa or PEM.',
                $keyType
            ));
        }

        if (str_starts_with($singleLine, 'ssh-rsa ')) {
            $pem = $this->convertOpenSshRsaToPem($singleLine);
            $converted = $this->loadOpenSslPublicKey($pem);
            if ($converted !== false) {
                return $converted;
            }
        }

        throw new RuntimeException(
            'Repository public key is invalid. Use PEM format (BEGIN PUBLIC KEY) or OpenSSH ssh-rsa.'
        );
    }

    private function loadOpenSslPublicKey(string $candidate): OpenSSLAsymmetricKey|false
    {
        $key = openssl_pkey_get_public($candidate);

        return $key instanceof OpenSSLAsymmetricKey ? $key : false;
    }

    private function convertOpenSshRsaToPem(string $openSshKey): string
    {
        $parts = preg_split('/\s+/', trim($openSshKey));
        if (! is_array($parts) || count($parts) < 2 || $parts[0] !== 'ssh-rsa') {
            throw new RuntimeException('OpenSSH public key must start with ssh-rsa.');
        }

        $base64Chunks = [];
        foreach (array_slice($parts, 1) as $part) {
            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $part) !== 1) {
                break;
            }
            $base64Chunks[] = $part;
        }

        $base64Payload = implode('', $base64Chunks);
        if ($base64Payload === '') {
            throw new RuntimeException('OpenSSH public key payload is missing.');
        }

        $blob = base64_decode($base64Payload, true);
        if (! is_string($blob) || $blob === '') {
            throw new RuntimeException('OpenSSH public key payload is not valid base64.');
        }

        $offset = 0;
        $algorithm = $this->readSshString($blob, $offset);
        if ($algorithm !== 'ssh-rsa') {
            throw new RuntimeException('Only ssh-rsa OpenSSH keys are supported for repository signatures.');
        }

        $exponent = $this->readSshString($blob, $offset);
        $modulus = $this->readSshString($blob, $offset);

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($this->normalizeMpintForDer($modulus)).
            $this->asn1Integer($this->normalizeMpintForDer($exponent))
        );

        $subjectPublicKeyInfo = $this->asn1Sequence(
            $this->asn1Sequence(
                $this->asn1ObjectIdentifier('1.2.840.113549.1.1.1').
                $this->asn1Null()
            ).
            $this->asn1BitString($rsaPublicKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function readSshString(string $blob, int &$offset): string
    {
        if (strlen($blob) < $offset + 4) {
            throw new RuntimeException('OpenSSH key payload is truncated.');
        }

        $length = unpack('N', substr($blob, $offset, 4));
        $offset += 4;
        $valueLength = is_array($length) ? (int) ($length[1] ?? 0) : 0;

        if ($valueLength < 0 || strlen($blob) < $offset + $valueLength) {
            throw new RuntimeException('OpenSSH key payload is malformed.');
        }

        $value = substr($blob, $offset, $valueLength);
        $offset += $valueLength;

        return $value;
    }

    private function normalizeMpintForDer(string $value): string
    {
        $normalized = ltrim($value, "\x00");
        if ($normalized === '') {
            return "\x00";
        }

        if ((ord($normalized[0]) & 0x80) !== 0) {
            return "\x00".$normalized;
        }

        return $normalized;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        $payload = "\x00".$value;

        return "\x03".$this->asn1Length(strlen($payload)).$payload;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1ObjectIdentifier(string $oid): string
    {
        $parts = array_map(static fn (string $part): int => (int) $part, explode('.', $oid));
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid ASN.1 OID.');
        }

        $encoded = chr((40 * $parts[0]) + $parts[1]);
        foreach (array_slice($parts, 2) as $part) {
            $encoded .= $this->encodeBase128($part);
        }

        return "\x06".$this->asn1Length(strlen($encoded)).$encoded;
    }

    private function encodeBase128(int $value): string
    {
        if ($value === 0) {
            return "\x00";
        }

        $chunks = '';
        while ($value > 0) {
            $chunks = chr($value & 0x7F).$chunks;
            $value >>= 7;
        }

        $length = strlen($chunks);
        $result = '';
        for ($index = 0; $index < $length; $index++) {
            $byte = ord($chunks[$index]);
            if ($index < $length - 1) {
                $byte |= 0x80;
            }
            $result .= chr($byte);
        }

        return $result;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $encoded = '';
        $value = $length;
        while ($value > 0) {
            $encoded = chr($value & 0xFF).$encoded;
            $value >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
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
