<?php

namespace PymeSec\Core\Menus;

class MenuDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $owner,
        public readonly string $labelKey,
        public readonly ?string $routeName = null,
        public readonly ?string $parentId = null,
        public readonly ?string $icon = null,
        public readonly int $order = 100,
        public readonly ?string $permission = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner' => $this->owner,
            'label_key' => $this->labelKey,
            'route' => $this->routeName,
            'parent_id' => $this->parentId,
            'icon' => $this->icon,
            'order' => $this->order,
            'permission' => $this->permission,
        ];
    }
}
