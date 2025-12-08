<?php

namespace samuelreichor\genesis\jobs;

use craft\i18n\Translation;
use craft\queue\BaseJob;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\models\SectionData;

/**
 * Import Sections Job
 *
 * Queue job for importing multiple sections from CSV data.
 */
class ImportSectionsJob extends BaseJob
{
    /**
     * @var array Array of section data arrays (serialized SectionData)
     */
    public array $sectionsData = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $importer = Genesis::getInstance()->csvImporterService;
        $total = count($this->sectionsData);

        foreach ($this->sectionsData as $index => $sectionDataArray) {
            $sectionData = SectionData::fromArray($sectionDataArray);
            $importer->importSection($sectionData);

            $this->setProgress($queue, ($index + 1) / $total, "Importing section: {$sectionData->handle}");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $count = count($this->sectionsData);
        return Translation::prep('genesis', 'Importing {count} sections from CSV', [
            'count' => $count,
        ]);
    }
}
