<?php

namespace samuelreichor\genesis\models;

/**
 * Preview Target Data Transfer Object
 *
 * Represents a preview target configuration for a section.
 */
class PreviewTargetData
{
    public function __construct(
        public string $label,
        public string $urlFormat,
        public bool $refresh = true,
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
            'label' => $this->label,
            'urlFormat' => $this->urlFormat,
            'refresh' => $this->refresh,
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
            label: $data['label'],
            urlFormat: $data['urlFormat'],
            refresh: $data['refresh'] ?? true,
        );
    }
}
