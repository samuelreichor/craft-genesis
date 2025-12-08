<?php

namespace samuelreichor\genesis\models;

/**
 * Section Site Settings Data Transfer Object
 *
 * Represents transformed site-specific settings for a section.
 */
class SectionSiteSettingsData
{
    public function __construct(
        public string $siteHandle,
        public ?string $uriFormat = null,
        public ?string $template = null,
        public bool $enabledByDefault = true,
        public bool $isHomepage = false,
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
            'siteHandle' => $this->siteHandle,
            'uriFormat' => $this->uriFormat,
            'template' => $this->template,
            'enabledByDefault' => $this->enabledByDefault,
            'isHomepage' => $this->isHomepage,
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
            siteHandle: $data['siteHandle'],
            uriFormat: $data['uriFormat'] ?? null,
            template: $data['template'] ?? null,
            enabledByDefault: $data['enabledByDefault'] ?? true,
            isHomepage: $data['isHomepage'] ?? false,
        );
    }
}
