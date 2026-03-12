<?php

namespace PymeSec\Core\Permissions;

class PermissionDefinition
{
    /**
     * @param  array<int, string>  $contexts
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $origin,
        public readonly ?string $featureArea = null,
        public readonly ?string $operation = null,
        public readonly array $contexts = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'origin' => $this->origin,
            'feature_area' => $this->featureArea,
            'operation' => $this->operation,
            'contexts' => $this->contexts,
        ];
    }
}
