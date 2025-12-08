<?php

namespace samuelreichor\genesis\models;

/**
 * Filesystem Data Transfer Object
 *
 * Represents transformed filesystem data ready for import.
 */
class FilesystemData
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $basePath,
        public bool $hasUrls = false,
        public ?string $url = null,
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
            'basePath' => $this->basePath,
            'hasUrls' => $this->hasUrls,
            'url' => $this->url,
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
            basePath: $data['basePath'],
            hasUrls: $data['hasUrls'] ?? false,
            url: $data['url'] ?? null,
        );
    }
}
