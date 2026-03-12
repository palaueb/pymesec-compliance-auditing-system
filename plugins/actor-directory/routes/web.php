<?php

use Illuminate\Support\Facades\Route;
use PymeSec\Core\FunctionalActors\Contracts\FunctionalActorServiceInterface;

Route::get('/plugins/actors', function (FunctionalActorServiceInterface $actors) {
    $organizationId = request()->query('organization_id', 'org-a');
    $scopeId = request()->query('scope_id');
    $principalId = request()->query('principal_id');

    return response()->json([
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'actors' => array_map(
            static fn ($actor): array => $actor->toArray(),
            $actors->actors(is_string($organizationId) ? $organizationId : null, is_string($scopeId) ? $scopeId : null),
        ),
        'principal_links' => is_string($principalId) && $principalId !== ''
            ? array_map(static fn ($link): array => $link->toArray(), $actors->linksForPrincipal($principalId, is_string($organizationId) ? $organizationId : null))
            : [],
    ]);
})->name('plugin.actor-directory.index');

Route::get('/plugins/actors/assignments', function (FunctionalActorServiceInterface $actors) {
    $organizationId = request()->query('organization_id', 'org-a');
    $scopeId = request()->query('scope_id');

    return response()->json([
        'organization_id' => $organizationId,
        'scope_id' => $scopeId,
        'assignments' => array_map(
            static fn ($assignment): array => $assignment->toArray(),
            $actors->assignments(is_string($organizationId) ? $organizationId : null, is_string($scopeId) ? $scopeId : null),
        ),
    ]);
})->name('plugin.actor-directory.assignments');
