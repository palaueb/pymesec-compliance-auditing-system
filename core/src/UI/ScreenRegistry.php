<?php

namespace PymeSec\Core\UI;

use Illuminate\Contracts\View\Factory as ViewFactory;
use PymeSec\Core\Menus\MenuLabelResolver;
use PymeSec\Core\UI\Contracts\ScreenRegistryInterface;

class ScreenRegistry implements ScreenRegistryInterface
{
    /**
     * @var array<string, ScreenDefinition>
     */
    private array $definitions = [];

    public function __construct(
        private readonly ViewFactory $views,
        private readonly MenuLabelResolver $labels,
    ) {}

    public function register(ScreenDefinition $definition): void
    {
        $this->definitions[$definition->menuId] = $definition;
    }

    public function has(string $menuId): bool
    {
        return isset($this->definitions[$menuId]);
    }

    public function definition(string $menuId): ?ScreenDefinition
    {
        return $this->definitions[$menuId] ?? null;
    }

    public function render(string $menuId, ScreenRenderContext $context): ?ScreenViewModel
    {
        $definition = $this->definition($menuId);

        if ($definition === null || ! is_file($definition->viewPath)) {
            return null;
        }

        $data = $definition->dataResolver !== null
            ? ($definition->dataResolver)($context)
            : [];

        $toolbar = $definition->toolbarResolver !== null
            ? ($definition->toolbarResolver)($context)
            : [];

        return new ScreenViewModel(
            title: $this->labels->label($definition->owner, $definition->titleKey, $context->locale),
            subtitle: $definition->subtitleKey !== null
                ? $this->labels->label($definition->owner, $definition->subtitleKey, $context->locale)
                : null,
            content: $this->views->file($definition->viewPath, $data)->render(),
            toolbarActions: $toolbar,
        );
    }
}
