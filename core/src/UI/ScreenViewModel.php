<?php

namespace PymeSec\Core\UI;

class ScreenViewModel
{
    /**
     * @param  array<int, ToolbarAction>  $toolbarActions
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly string $content,
        public readonly array $toolbarActions = [],
    ) {
    }
}
