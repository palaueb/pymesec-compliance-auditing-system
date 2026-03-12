<?php

namespace PymeSec\Core\UI;

class ToolbarAction
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly string $variant = 'secondary',
        public readonly string $target = '_self',
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'variant' => $this->variant,
            'target' => $this->target,
        ];
    }
}
