<?php

namespace samuelreichor\genesis\models;

/**
 * Asset Volume Data Transfer Object
 *
 * Represents transformed asset volume data ready for import.
 */
class AssetVolumeData
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $fsHandle,
        public ?string $subpath = null,
        public ?string $transformFsHandle = null,
        public ?string $transformSubpath = null,
        public string $titleTranslationMethod = 'site',
        public ?string $titleTranslationKeyFormat = null,
        public string $altTranslationMethod = 'site',
        public ?string $altTranslationKeyFormat = null,
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
            'fsHandle' => $this->fsHandle,
            'subpath' => $this->subpath,
            'transformFsHandle' => $this->transformFsHandle,
            'transformSubpath' => $this->transformSubpath,
            'titleTranslationMethod' => $this->titleTranslationMethod,
            'titleTranslationKeyFormat' => $this->titleTranslationKeyFormat,
            'altTranslationMethod' => $this->altTranslationMethod,
            'altTranslationKeyFormat' => $this->altTranslationKeyFormat,
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
            fsHandle: $data['fsHandle'],
            subpath: $data['subpath'] ?? null,
            transformFsHandle: $data['transformFsHandle'] ?? null,
            transformSubpath: $data['transformSubpath'] ?? null,
            titleTranslationMethod: $data['titleTranslationMethod'] ?? 'site',
            titleTranslationKeyFormat: $data['titleTranslationKeyFormat'] ?? null,
            altTranslationMethod: $data['altTranslationMethod'] ?? 'site',
            altTranslationKeyFormat: $data['altTranslationKeyFormat'] ?? null,
        );
    }
}
