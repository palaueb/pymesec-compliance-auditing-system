<?php

namespace PymeSec\Core\UI;

use Closure;

class ScreenDefinition
{
    /**
     * @param  Closure(ScreenRenderContext): array<string, mixed> | null  $dataResolver
     * @param  Closure(ScreenRenderContext): array<int, ToolbarAction> | null  $toolbarResolver
     */
    public function __construct(
        public readonly string $menuId,
        public readonly string $owner,
        public readonly string $titleKey,
        public readonly ?string $subtitleKey,
        public readonly string $viewPath,
        public readonly ?Closure $dataResolver = null,
        public readonly ?Closure $toolbarResolver = null,
    ) {
    }
}
