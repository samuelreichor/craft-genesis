<?php

namespace samuelreichor\genesis\jobs;

use craft\i18n\Translation;
use craft\queue\BaseJob;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\models\EntryTypeData;

/**
 * Import Entry Types Job
 *
 * Queue job for importing multiple entry types from CSV data.
 */
class ImportEntryTypesJob extends BaseJob
{
    /**
     * @var array Array of entry type data arrays (serialized EntryTypeData)
     */
    public array $entryTypesData = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $importer = Genesis::getInstance()->csvImporterService;
        $total = count($this->entryTypesData);

        foreach ($this->entryTypesData as $index => $entryTypeDataArray) {
            $entryTypeData = EntryTypeData::fromArray($entryTypeDataArray);
            $importer->importEntryType($entryTypeData);

            $this->setProgress($queue, ($index + 1) / $total, "Importing entry type: {$entryTypeData->handle}");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $count = count($this->entryTypesData);
        return Translation::prep('genesis', 'Importing {count} entry types from CSV', [
            'count' => $count,
        ]);
    }
}
