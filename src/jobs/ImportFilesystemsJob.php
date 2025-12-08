<?php

namespace samuelreichor\genesis\jobs;

use craft\i18n\Translation;
use craft\queue\BaseJob;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\models\FilesystemData;

/**
 * Import Filesystems Job
 *
 * Queue job for importing multiple filesystems from CSV data.
 */
class ImportFilesystemsJob extends BaseJob
{
    /**
     * @var array Array of filesystem data arrays (serialized FilesystemData)
     */
    public array $filesystemsData = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $importer = Genesis::getInstance()->csvImporterService;
        $total = count($this->filesystemsData);

        foreach ($this->filesystemsData as $index => $filesystemDataArray) {
            $filesystemData = FilesystemData::fromArray($filesystemDataArray);
            $importer->importFilesystem($filesystemData);

            $this->setProgress($queue, ($index + 1) / $total, "Importing filesystem: {$filesystemData->handle}");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $count = count($this->filesystemsData);
        return Translation::prep('genesis', 'Importing {count} filesystems from CSV', [
            'count' => $count,
        ]);
    }
}
