<?php

namespace PymeSec\Core\Menus;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;
use PymeSec\Core\Menus\Contracts\MenuRegistryInterface;
use PymeSec\Core\Permissions\AuthorizationContext;
use PymeSec\Core\Permissions\Contracts\AuthorizationServiceInterface;
use PymeSec\Core\Permissions\Contracts\PermissionRegistryInterface;

class MenuRegistry implements MenuRegistryInterface
{
    /**
     * @var array<int, MenuRegistration>
     */
    private array $registrations = [];

    /**
     * @var array<string, MenuDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $issues = [];

    private bool $finalized = false;

    public function __construct(
        private readonly PermissionRegistryInterface $permissions,
        private readonly AuthorizationServiceInterface $authorization,
        private readonly UrlGenerator $url,
    ) {
    }

    public function registerCore(MenuDefinition $definition): void
    {
        $this->register(new MenuRegistration($definition));
    }

    public function registerPlugin(MenuDefinition $definition, array $dependencyPluginIds = []): void
    {
        $this->register(new MenuRegistration(
            definition: $definition,
            dependencyPluginIds: array_values(array_unique(array_filter(
                $dependencyPluginIds,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            ))),
        ));
    }

    public function finalize(): void
    {
        $this->definitions = [];
        $this->issues = [];

        $pending = [];

        foreach ($this->registrations as $registration) {
            $definition = $registration->definition;

            if (isset($this->definitions[$definition->id])) {
                $this->issues[] = $this->issue($definition, 'duplicate_menu_id');

                continue;
            }

            if ($definition->permission !== null && $this->permissions->find($definition->permission) === null) {
                $this->issues[] = $this->issue($definition, 'menu_permission_not_registered');

                continue;
            }

            if ($definition->routeName !== null && ! Route::has($definition->routeName)) {
                $this->issues[] = $this->issue($definition, 'menu_route_not_found');

                continue;
            }

            $pending[$definition->id] = $registration;
            $this->definitions[$definition->id] = $definition;
        }

        foreach ($pending as $registration) {
            $definition = $registration->definition;

            if ($definition->parentId === null) {
                continue;
            }

            $parent = $this->definitions[$definition->parentId] ?? null;

            if ($parent === null) {
                unset($this->definitions[$definition->id]);
                $this->issues[] = $this->issue($definition, 'parent_menu_not_found');

                continue;
            }

            if ($parent->parentId !== null) {
                unset($this->definitions[$definition->id]);
                $this->issues[] = $this->issue($definition, 'menu_depth_exceeded');

                continue;
            }

            if (
                $parent->owner !== 'core'
                && $parent->owner !== $definition->owner
                && ! in_array($parent->owner, $registration->dependencyPluginIds, true)
            ) {
                unset($this->definitions[$definition->id]);
                $this->issues[] = $this->issue($definition, 'parent_owner_dependency_missing');
            }
        }

        $this->finalized = true;
    }

    public function all(): array
    {
        $this->ensureFinalized();

        return $this->tree($this->definitions);
    }

    public function visible(MenuVisibilityContext $context): array
    {
        $this->ensureFinalized();

        $visible = [];

        foreach ($this->definitions as $definition) {
            if ($definition->permission === null) {
                $visible[$definition->id] = $definition;

                continue;
            }

            if ($context->principal === null) {
                continue;
            }

            $result = $this->authorization->authorize(new AuthorizationContext(
                principal: $context->principal,
                permission: $definition->permission,
                memberships: $context->memberships,
                organizationId: $context->organizationId,
                scopeId: $context->scopeId,
            ));

            if ($result->status === 'allow') {
                $visible[$definition->id] = $definition;
            }
        }

        return $this->tree($visible);
    }

    public function issues(): array
    {
        $this->ensureFinalized();

        return $this->issues;
    }

    private function register(MenuRegistration $registration): void
    {
        $this->registrations[] = $registration;
        $this->finalized = false;
    }

    private function ensureFinalized(): void
    {
        if (! $this->finalized) {
            $this->finalize();
        }
    }

    /**
     * @param  array<string, MenuDefinition>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function tree(array $definitions): array
    {
        uasort($definitions, static function (MenuDefinition $left, MenuDefinition $right): int {
            $byOrder = $left->order <=> $right->order;

            return $byOrder !== 0 ? $byOrder : strcmp($left->id, $right->id);
        });

        $children = [];

        foreach ($definitions as $definition) {
            if ($definition->parentId !== null) {
                $children[$definition->parentId][] = $definition;
            }
        }

        $items = [];

        foreach ($definitions as $definition) {
            if ($definition->parentId !== null) {
                continue;
            }

            $items[] = $this->renderItem($definition, $children[$definition->id] ?? []);
        }

        return $items;
    }

    /**
     * @param  array<int, MenuDefinition>  $children
     * @return array<string, mixed>
     */
    private function renderItem(MenuDefinition $definition, array $children): array
    {
        usort($children, static function (MenuDefinition $left, MenuDefinition $right): int {
            $byOrder = $left->order <=> $right->order;

            return $byOrder !== 0 ? $byOrder : strcmp($left->id, $right->id);
        });

        return [
            ...$definition->toArray(),
            'url' => $this->resolveUrl($definition->routeName),
            'children' => array_map(
                fn (MenuDefinition $child): array => [
                    ...$child->toArray(),
                    'url' => $this->resolveUrl($child->routeName),
                    'children' => [],
                ],
                $children,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(MenuDefinition $definition, string $reason): array
    {
        return [
            'id' => $definition->id,
            'owner' => $definition->owner,
            'reason' => $reason,
            'parent_id' => $definition->parentId,
            'route' => $definition->routeName,
            'permission' => $definition->permission,
        ];
    }

    private function resolveUrl(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        try {
            return $this->url->route($routeName);
        } catch (\Throwable) {
            return null;
        }
    }
}
