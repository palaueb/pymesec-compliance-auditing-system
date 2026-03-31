<?php

namespace PymeSec\Core\Artifacts;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ArtifactUploadFileTypeGuard
{
    /**
     * @return array{extension: string, media_type: string}
     */
    public function validate(ArtifactUploadData $artifact): array
    {
        $file = $artifact->file;
        $extension = $this->normalizeExtension($file);

        if ($extension === '' || in_array($extension, $this->blockedExtensions(), true)) {
            $this->reject('This file type is not allowed for uploads.');
        }

        $profile = $this->profileDefinition($artifact->uploadProfile ?? $this->defaultProfileForArtifactType($artifact->artifactType));
        $allowedMimes = $profile['extensions'][$extension] ?? null;

        if (! is_array($allowedMimes)) {
            $this->reject(sprintf(
                'The uploaded file type [%s] is not allowed for this upload.',
                $extension
            ));
        }

        $mediaType = $this->detectMediaType($file);

        if (! in_array($mediaType, $allowedMimes, true)) {
            $this->reject(sprintf(
                'The uploaded file does not match the expected document type for [%s].',
                $extension
            ));
        }

        return [
            'extension' => $extension,
            'media_type' => $mediaType,
        ];
    }

    private function defaultProfileForArtifactType(string $artifactType): string
    {
        return match ($artifactType) {
            'mandate-document' => 'pdf_or_document',
            'document', 'statement' => 'documents_only',
            'record', 'recovery-plan', 'report', 'ticket', 'log-export' => 'documents_and_spreadsheets',
            'snapshot' => 'images_only',
            'workpaper', 'evidence', 'other' => 'review_artifacts',
            default => 'review_artifacts',
        };
    }

    /**
     * @return array{extensions: array<string, array<int, string>>}
     */
    private function profileDefinition(string $profile): array
    {
        return match ($profile) {
            'documents_only' => [
                'extensions' => [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => [
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/zip',
                        'application/octet-stream',
                    ],
                    'odt' => [
                        'application/vnd.oasis.opendocument.text',
                        'application/zip',
                        'application/octet-stream',
                    ],
                    'txt' => ['text/plain', 'application/octet-stream'],
                    'md' => ['text/markdown', 'text/plain', 'application/octet-stream'],
                ],
            ],
            'documents_and_spreadsheets' => [
                'extensions' => [
                    ...$this->profileDefinition('documents_only')['extensions'],
                    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
                    'xlsx' => [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',
                        'application/octet-stream',
                    ],
                    'ods' => [
                        'application/vnd.oasis.opendocument.spreadsheet',
                        'application/zip',
                        'application/octet-stream',
                    ],
                    'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
                ],
            ],
            'images_only' => [
                'extensions' => [
                    'png' => ['image/png'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'webp' => ['image/webp'],
                ],
            ],
            'pdf_or_document' => [
                'extensions' => [
                    ...$this->profileDefinition('documents_only')['extensions'],
                ],
            ],
            'review_artifacts' => [
                'extensions' => [
                    ...$this->profileDefinition('documents_and_spreadsheets')['extensions'],
                    ...$this->profileDefinition('images_only')['extensions'],
                ],
            ],
            default => $this->profileDefinition('review_artifacts'),
        };
    }

    private function normalizeExtension(UploadedFile $file): string
    {
        return strtolower(trim((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '')));
    }

    private function detectMediaType(UploadedFile $file): string
    {
        $detected = strtolower(trim((string) ($file->getMimeType() ?: '')));

        if ($detected !== '') {
            return $detected;
        }

        $client = strtolower(trim((string) ($file->getClientMimeType() ?: '')));

        return $client !== '' ? $client : 'application/octet-stream';
    }

    /**
     * @return array<int, string>
     */
    private function blockedExtensions(): array
    {
        return [
            'php',
            'phtml',
            'phar',
            'js',
            'mjs',
            'html',
            'htm',
            'svg',
            'exe',
            'dll',
            'so',
            'bat',
            'cmd',
            'sh',
            'ps1',
            'jar',
            'apk',
            'zip',
            '7z',
            'rar',
            'tar',
            'gz',
        ];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([
            'artifact' => $message,
        ]);
    }
}
