<?php

namespace samuelreichor\genesis\models;

/**
 * Section Data Transfer Object
 *
 * Represents transformed section data ready for import.
 */
class SectionData
{
    /**
     * @param string $handle
     * @param string $name
     * @param string $type single, channel, or structure
     * @param array $entryTypeHandles Array of entry type handles
     * @param array $siteSettings Array of SectionSiteSettingsData
     * @param string $propagationMethod
     * @param int|null $maxAuthors
     * @param int|null $maxLevels Only for structure type
     * @param string|null $defaultPlacement Only for structure type
     */
    public function __construct(
        public string $handle,
        public string $name,
        public string $type,
        public array $entryTypeHandles = [],
        public array $siteSettings = [],
        public string $propagationMethod = 'all',
        public ?int $maxAuthors = 1,
        public ?int $maxLevels = null,
        public ?string $defaultPlacement = null,
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
            'type' => $this->type,
            'entryTypeHandles' => $this->entryTypeHandles,
            'siteSettings' => array_map(fn($s) => $s->toArray(), $this->siteSettings),
            'propagationMethod' => $this->propagationMethod,
            'maxAuthors' => $this->maxAuthors,
            'maxLevels' => $this->maxLevels,
            'defaultPlacement' => $this->defaultPlacement,
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
        $siteSettings = array_map(
            fn($s) => SectionSiteSettingsData::fromArray($s),
            $data['siteSettings'] ?? []
        );

        return new self(
            handle: $data['handle'],
            name: $data['name'],
            type: $data['type'],
            entryTypeHandles: $data['entryTypeHandles'] ?? [],
            siteSettings: $siteSettings,
            propagationMethod: $data['propagationMethod'] ?? 'all',
            maxAuthors: $data['maxAuthors'] ?? 1,
            maxLevels: $data['maxLevels'] ?? null,
            defaultPlacement: $data['defaultPlacement'] ?? null,
        );
    }
}
