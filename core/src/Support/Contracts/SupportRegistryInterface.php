<?php

namespace PymeSec\Core\Support\Contracts;

interface SupportRegistryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function catalogue(string $locale = 'en'): array;
}
