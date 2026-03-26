<?php

namespace PymeSec\Plugins\IdentityLocal;

use Illuminate\Http\UploadedFile;
use PymeSec\Core\Audit\AuditRecordData;
use PymeSec\Core\Audit\Contracts\AuditTrailInterface;
use PymeSec\Core\Events\Contracts\EventBusInterface;
use PymeSec\Core\Events\PublicEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityUserImportService
{
    private const MAX_FILE_BYTES = 1048576;

    private const MAX_ROWS = 250;

    private const MAX_COLUMNS = 25;

    private const MAX_CELL_LENGTH = 500;

    private const MAX_HEADER_LENGTH = 120;

    /**
     * @param  array<string, array<int, string>>  $fieldAliases
     */
    private const FIELD_ALIASES = [
        'display_name' => ['display name', 'full name', 'name', 'employee name', 'person name'],
        'email' => ['email', 'email address', 'work email', 'mail', 'e mail'],
        'username' => ['username', 'login', 'user', 'account', 'userid', 'user name'],
        'job_title' => ['job title', 'title', 'role', 'team', 'department', 'department team', 'department or team'],
    ];

    public function __construct(
        private readonly IdentityLocalRepository $users,
        private readonly AuditTrailInterface $audit,
        private readonly EventBusInterface $events,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function beginImport(UploadedFile $file, string $organizationId, ?string $managedByPrincipalId = null): array
    {
        $this->assertAcceptedFile($file);

        [$delimiter, $rows] = $this->parseFile($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file does not contain any importable rows.',
            ]);
        }

        $headers = array_keys($rows[0]);

        $result = [
            'file_name' => $file->getClientOriginalName() ?: 'import.csv',
            'delimiter' => $delimiter,
            'delimiter_label' => match ($delimiter) {
                "\t" => 'TSV',
                ';' => 'Semicolon-separated',
                default => 'CSV',
            },
            'headers' => $headers,
            'row_count' => count($rows),
            'sample_rows' => array_slice($rows, 0, 5),
            'rows' => $rows,
            'default_mapping' => $this->defaultMapping($headers),
            'required_fields' => [
                'display_name' => 'Full name',
                'email' => 'Work email',
            ],
            'optional_fields' => [
                'username' => 'Username',
                'job_title' => 'Team / department',
            ],
        ];

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user-import.uploaded',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'identity_local_import',
            summary: [
                'file_name' => $result['file_name'],
                'delimiter' => $result['delimiter_label'],
                'row_count' => $result['row_count'],
                'header_count' => count($result['headers']),
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.user-import.uploaded',
            originComponent: 'identity-local',
            organizationId: $organizationId,
            payload: [
                'file_name' => $result['file_name'],
                'row_count' => $result['row_count'],
            ],
        ));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $uploadState
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    public function reviewImport(array $uploadState, array $mapping, string $organizationId, ?string $managedByPrincipalId = null): array
    {
        $headers = $this->normalizeHeaders($uploadState);
        $rows = $this->normalizeRows($uploadState);
        $resolvedMapping = $this->resolveMapping($headers, $mapping);
        $existingEmails = DB::table('identity_local_users')
            ->selectRaw('LOWER(email) as email')
            ->pluck('email')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(static fn (string $value): string => Str::lower($value))
            ->all();
        $existingUsernames = DB::table('identity_local_users')
            ->selectRaw('LOWER(username) as username')
            ->pluck('username')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(static fn (string $value): string => Str::lower($value))
            ->all();

        $emailCounts = [];
        $providedUsernameCounts = [];

        foreach ($rows as $row) {
            $email = Str::lower(trim((string) ($row[$resolvedMapping['email']] ?? '')));

            if ($email !== '') {
                $emailCounts[$email] = ($emailCounts[$email] ?? 0) + 1;
            }

            $usernameHeader = $resolvedMapping['username'] ?? null;

            if (is_string($usernameHeader) && $usernameHeader !== '') {
                $providedUsername = Str::lower(trim((string) ($row[$usernameHeader] ?? '')));

                if ($providedUsername !== '') {
                    $providedUsernameCounts[$providedUsername] = ($providedUsernameCounts[$providedUsername] ?? 0) + 1;
                }
            }
        }

        $reviewRows = [];
        $validRows = [];
        $generatedUsernames = [];

        foreach ($rows as $index => $row) {
            $displayName = trim((string) ($row[$resolvedMapping['display_name']] ?? ''));
            $email = trim((string) ($row[$resolvedMapping['email']] ?? ''));
            $jobTitleHeader = $resolvedMapping['job_title'] ?? null;
            $jobTitle = is_string($jobTitleHeader) && $jobTitleHeader !== ''
                ? trim((string) ($row[$jobTitleHeader] ?? ''))
                : '';
            $usernameHeader = $resolvedMapping['username'] ?? null;
            $providedUsername = is_string($usernameHeader) && $usernameHeader !== ''
                ? Str::lower(trim((string) ($row[$usernameHeader] ?? '')))
                : '';
            $normalized = [
                'display_name' => $displayName,
                'email' => Str::lower($email),
                'username' => $providedUsername,
                'job_title' => $jobTitle,
            ];

            $validator = Validator::make($normalized, [
                'display_name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:190'],
                'job_title' => ['nullable', 'string', 'max:120'],
            ]);

            $errors = $validator->errors()->all();

            if ($normalized['email'] !== '' && ($emailCounts[$normalized['email']] ?? 0) > 1) {
                $errors[] = 'Duplicate email inside the uploaded file.';
            }

            if ($normalized['email'] !== '' && in_array($normalized['email'], $existingEmails, true)) {
                $errors[] = 'Email already exists in the local directory.';
            }

            if ($providedUsername !== '') {
                $usernameValidator = Validator::make(['username' => $providedUsername], [
                    'username' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
                ]);

                $errors = [...$errors, ...$usernameValidator->errors()->all()];

                if (($providedUsernameCounts[$providedUsername] ?? 0) > 1) {
                    $errors[] = 'Duplicate username inside the uploaded file.';
                }

                if (in_array($providedUsername, $existingUsernames, true)) {
                    $errors[] = 'Username already exists in the local directory.';
                }

                $normalized['username'] = $providedUsername;
            } else {
                $generated = $this->generateUsername(
                    displayName: $displayName,
                    email: $normalized['email'],
                    reserved: [...$existingUsernames, ...array_keys($generatedUsernames)],
                );

                if ($generated === null) {
                    $errors[] = 'The row does not contain enough data to derive a safe username.';
                } else {
                    $normalized['username'] = $generated;
                    $generatedUsernames[$generated] = true;
                }
            }

            $reviewRows[] = [
                'row_number' => $index + 2,
                'raw' => $row,
                'normalized' => $normalized,
                'errors' => array_values(array_unique($errors)),
            ];

            if ($errors === []) {
                $validRows[] = $normalized;
            }
        }

        $result = [
            'mapping' => $resolvedMapping,
            'rows' => $reviewRows,
            'valid_rows' => $validRows,
            'summary' => [
                'total_rows' => count($reviewRows),
                'valid_count' => count($validRows),
                'invalid_count' => count(array_filter($reviewRows, static fn (array $row): bool => $row['errors'] !== [])),
            ],
        ];

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user-import.reviewed',
            outcome: ($result['summary']['invalid_count'] ?? 0) > 0 ? 'failure' : 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'identity_local_import',
            summary: [
                'total_rows' => $result['summary']['total_rows'],
                'valid_count' => $result['summary']['valid_count'],
                'invalid_count' => $result['summary']['invalid_count'],
                'mapping' => $resolvedMapping,
            ],
            executionOrigin: 'identity-local',
        ));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $reviewState
     * @return array<string, mixed>
     */
    public function importUsers(array $reviewState, string $organizationId, ?string $managedByPrincipalId = null): array
    {
        $rows = is_array($reviewState['rows'] ?? null) ? $reviewState['rows'] : [];
        $invalidCount = (int) ($reviewState['summary']['invalid_count'] ?? 0);

        if ($rows === [] || $invalidCount > 0) {
            throw ValidationException::withMessages([
                'import_file' => 'Fix every invalid row before confirming the import.',
            ]);
        }

        $validRows = is_array($reviewState['valid_rows'] ?? null) ? $reviewState['valid_rows'] : [];
        $created = [];

        DB::transaction(function () use ($validRows, $organizationId, $managedByPrincipalId, &$created): void {
            foreach ($validRows as $row) {
                $email = Str::lower((string) ($row['email'] ?? ''));
                $username = Str::lower((string) ($row['username'] ?? ''));

                if ($email === '' || $username === '') {
                    throw ValidationException::withMessages([
                        'import_file' => 'The import payload is incomplete.',
                    ]);
                }

                if (DB::table('identity_local_users')->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages([
                        'import_file' => sprintf('Email [%s] already exists. Reload the import and review the rows again.', $email),
                    ]);
                }

                if (DB::table('identity_local_users')->whereRaw('LOWER(username) = ?', [$username])->exists()) {
                    throw ValidationException::withMessages([
                        'import_file' => sprintf('Username [%s] already exists. Reload the import and review the rows again.', $username),
                    ]);
                }

                $created[] = $this->users->createUser([
                    'organization_id' => $organizationId,
                    'display_name' => (string) $row['display_name'],
                    'username' => $username,
                    'email' => $email,
                    'job_title' => ($row['job_title'] ?? '') !== '' ? (string) $row['job_title'] : null,
                    'password_enabled' => false,
                    'magic_link_enabled' => true,
                    'is_active' => true,
                ], $managedByPrincipalId);
            }
        });

        $result = [
            'created_count' => count($created),
            'created' => $created,
        ];

        $this->audit->record(new AuditRecordData(
            eventType: 'plugin.identity-local.user-import.completed',
            outcome: 'success',
            originComponent: 'identity-local',
            principalId: $managedByPrincipalId,
            organizationId: $organizationId,
            targetType: 'identity_local_import',
            summary: [
                'created_count' => $result['created_count'],
                'principal_ids' => array_values(array_filter(array_map(
                    static fn (array $user): ?string => is_string($user['principal_id'] ?? null) ? (string) $user['principal_id'] : null,
                    $created,
                ))),
            ],
            executionOrigin: 'identity-local',
        ));

        $this->events->publish(new PublicEvent(
            name: 'plugin.identity-local.user-import.completed',
            originComponent: 'identity-local',
            organizationId: $organizationId,
            payload: [
                'created_count' => $result['created_count'],
            ],
        ));

        return $result;
    }

    private function assertAcceptedFile(UploadedFile $file): void
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $size = (int) ($file->getSize() ?? 0);
        $mime = Str::lower($file->getClientMimeType() ?: $file->getMimeType() ?: '');
        $acceptedMime = [
            'text/plain',
            'text/csv',
            'text/tab-separated-values',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];

        if (! in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'import_file' => 'Only CSV or TSV files are accepted for user imports.',
            ]);
        }

        if ($mime !== '' && ! in_array($mime, $acceptedMime, true) && ! str_starts_with($mime, 'text/')) {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file does not look like a CSV or TSV text document.',
            ]);
        }

        if ($size < 1 || $size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file must be smaller than 1 MB.',
            ]);
        }
    }

    /**
     * @return array{0:string,1:array<int, array<string, string>>}
     */
    private function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file could not be read.',
            ]);
        }

        $contents = $file->get();

        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file is empty.',
            ]);
        }

        if (str_contains($contents, "\0")) {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file contains binary data and cannot be imported safely.',
            ]);
        }

        $delimiter = $this->detectDelimiter($contents, Str::lower($file->getClientOriginalExtension() ?: ''));
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file could not be opened.',
            ]);
        }

        $headerRow = fgetcsv($handle, 0, $delimiter);

        if (! is_array($headerRow) || $headerRow === []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => 'The uploaded file must start with a header row.',
            ]);
        }

        $headers = array_map(
            static fn (mixed $value): string => trim((string) $value),
            $headerRow,
        );
        $headers[0] = $this->stripUtf8Bom($headers[0] ?? '');

        foreach ($headers as $header) {
            if ($header !== '' && mb_strlen($header) > self::MAX_HEADER_LENGTH) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'import_file' => sprintf('Column headers must be shorter than %d characters.', self::MAX_HEADER_LENGTH),
                ]);
            }
        }

        $normalizedHeaders = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);

        if (count($headers) > self::MAX_COLUMNS) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => sprintf('The import file may contain at most %d columns.', self::MAX_COLUMNS),
            ]);
        }

        if (in_array('', $normalizedHeaders, true)) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => 'Every import column needs a non-empty header.',
            ]);
        }

        if (count($normalizedHeaders) !== count(array_unique($normalizedHeaders))) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => 'Column headers must be unique after normalization.',
            ]);
        }

        $rows = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }

            $values = [];

            foreach ($headers as $index => $header) {
                $value = trim((string) ($data[$index] ?? ''));

                if (mb_strlen($value) > self::MAX_CELL_LENGTH) {
                    fclose($handle);

                    throw ValidationException::withMessages([
                        'import_file' => sprintf('A cell exceeds the maximum safe length of %d characters.', self::MAX_CELL_LENGTH),
                    ]);
                }

                $values[$header] = $value;
            }

            if (collect($values)->every(static fn (string $value): bool => $value === '')) {
                continue;
            }

            $rows[] = $values;

            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'import_file' => sprintf('The import file may contain at most %d non-empty rows.', self::MAX_ROWS),
                ]);
            }
        }

        fclose($handle);

        return [$delimiter, $rows];
    }

    private function detectDelimiter(string $contents, string $extension): string
    {
        if ($extension === 'tsv') {
            return "\t";
        }

        $firstLine = strtok(str_replace("\r\n", "\n", $contents), "\n");
        $candidates = [
            ',' => substr_count((string) $firstLine, ','),
            "\t" => substr_count((string) $firstLine, "\t"),
            ';' => substr_count((string) $firstLine, ';'),
        ];
        arsort($candidates);
        $delimiter = array_key_first($candidates);

        return is_string($delimiter) && ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }

    private function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function defaultMapping(array $headers): array
    {
        $mapping = [
            'display_name' => '',
            'email' => '',
            'username' => '',
            'job_title' => '',
        ];
        $used = [];

        foreach ($mapping as $field => $_) {
            $aliases = self::FIELD_ALIASES[$field] ?? [];

            foreach ($headers as $header) {
                $normalized = $this->normalizeHeader($header);

                if (in_array($header, $used, true)) {
                    continue;
                }

                if (in_array($normalized, $aliases, true)) {
                    $mapping[$field] = $header;
                    $used[] = $header;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $mapping
     * @return array<string, string>
     */
    private function resolveMapping(array $headers, array $mapping): array
    {
        $resolved = [
            'display_name' => is_string($mapping['display_name'] ?? null) ? trim((string) $mapping['display_name']) : '',
            'email' => is_string($mapping['email'] ?? null) ? trim((string) $mapping['email']) : '',
            'username' => is_string($mapping['username'] ?? null) ? trim((string) $mapping['username']) : '',
            'job_title' => is_string($mapping['job_title'] ?? null) ? trim((string) $mapping['job_title']) : '',
        ];

        foreach (['display_name', 'email'] as $requiredField) {
            if ($resolved[$requiredField] === '' || ! in_array($resolved[$requiredField], $headers, true)) {
                throw ValidationException::withMessages([
                    'mapping.'.$requiredField => sprintf('Map [%s] to one of the uploaded columns.', $requiredField),
                ]);
            }
        }

        $selected = array_values(array_filter($resolved, static fn (string $value): bool => $value !== ''));

        if (count($selected) !== count(array_unique($selected))) {
            throw ValidationException::withMessages([
                'mapping' => 'Each imported field must map to a distinct source column.',
            ]);
        }

        foreach (['username', 'job_title'] as $optionalField) {
            if ($resolved[$optionalField] !== '' && ! in_array($resolved[$optionalField], $headers, true)) {
                throw ValidationException::withMessages([
                    'mapping.'.$optionalField => sprintf('The selected column for [%s] does not exist in the uploaded file.', $optionalField),
                ]);
            }
        }

        return $resolved;
    }

    private function generateUsername(string $displayName, string $email, array $reserved): ?string
    {
        $base = Str::lower(trim(Str::before($email, '@')));

        if ($base === '') {
            $base = Str::lower(Str::replace('-', '.', Str::slug($displayName)));
        }

        $base = preg_replace('/[^a-z0-9._-]+/', '.', $base) ?? '';
        $base = trim((string) $base, '.-_');
        $base = preg_replace('/[._-]{2,}/', '.', $base) ?? $base;

        if ($base === '') {
            return null;
        }

        $candidate = Str::limit($base, 120, '');
        $suffix = 2;

        while (in_array($candidate, $reserved, true) || DB::table('identity_local_users')->whereRaw('LOWER(username) = ?', [$candidate])->exists()) {
            $room = max(1, 120 - (strlen((string) $suffix) + 1));
            $candidate = Str::limit($base, $room, '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::lower(trim($this->stripUtf8Bom($header)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    private function normalizeHeaders(array $state): array
    {
        $headers = $state['headers'] ?? null;

        if (! is_array($headers) || $headers === []) {
            throw ValidationException::withMessages([
                'import_file' => 'The import session is missing the uploaded columns. Upload the file again.',
            ]);
        }

        return array_values(array_filter(
            array_map(static fn (mixed $header): ?string => is_string($header) ? $header : null, $headers),
            static fn (?string $header): bool => is_string($header) && $header !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, string>>
     */
    private function normalizeRows(array $state): array
    {
        $rows = $state['rows'] ?? null;

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages([
                'import_file' => 'The import session does not contain any uploaded rows. Upload the file again.',
            ]);
        }

        return array_values(array_filter(
            array_map(static function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $normalized = [];

                foreach ($row as $key => $value) {
                    if (! is_string($key)) {
                        continue;
                    }

                    $normalized[$key] = is_string($value) ? $value : '';
                }

                return $normalized;
            }, $rows),
            static fn (?array $row): bool => is_array($row) && $row !== [],
        ));
    }
}
