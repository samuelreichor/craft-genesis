<?php

namespace samuelreichor\genesis\models;

/**
 * Site Data Transfer Object
 *
 * Represents transformed site data ready for import.
 */
class SiteData
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $language,
        public ?string $baseUrl = null,
        public bool $primary = false,
        public bool $hasUrls = true,
        public bool $enabled = true,
        public ?int $groupId = null,
    ) {
    }

    /**
     * Convert to array for serialization.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'language' => $this->language,
            'baseUrl' => $this->baseUrl,
            'primary' => $this->primary,
            'hasUrls' => $this->hasUrls,
            'enabled' => $this->enabled,
            'groupId' => $this->groupId,
        ];
    }

    /**
     * Create from array.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            handle: $data['handle'],
            name: $data['name'],
            language: $data['language'],
            baseUrl: $data['baseUrl'] ?? null,
            primary: $data['primary'] ?? false,
            hasUrls: $data['hasUrls'] ?? true,
            enabled: $data['enabled'] ?? true,
            groupId: $data['groupId'] ?? null,
        );
    }
}
