<?php

namespace samuelreichor\genesis\models;

/**
 * Entry Type Data Transfer Object
 *
 * Represents transformed entry type data ready for import.
 */
class EntryTypeData
{
    public function __construct(
        public string $handle,
        public string $name,
        public ?string $description = null,
        public string $titleTranslationMethod = 'site',
        public ?string $titleTranslationKeyFormat = null,
        public bool $showSlug = true,
        public string $slugTranslationMethod = 'site',
        public ?string $slugTranslationKeyFormat = null,
        public bool $showStatusField = true,
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
            'description' => $this->description,
            'titleTranslationMethod' => $this->titleTranslationMethod,
            'titleTranslationKeyFormat' => $this->titleTranslationKeyFormat,
            'showSlug' => $this->showSlug,
            'slugTranslationMethod' => $this->slugTranslationMethod,
            'slugTranslationKeyFormat' => $this->slugTranslationKeyFormat,
            'showStatusField' => $this->showStatusField,
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
            description: $data['description'] ?? null,
            titleTranslationMethod: $data['titleTranslationMethod'] ?? 'site',
            titleTranslationKeyFormat: $data['titleTranslationKeyFormat'] ?? null,
            showSlug: $data['showSlug'] ?? true,
            slugTranslationMethod: $data['slugTranslationMethod'] ?? 'site',
            slugTranslationKeyFormat: $data['slugTranslationKeyFormat'] ?? null,
            showStatusField: $data['showStatusField'] ?? true,
        );
    }
}
