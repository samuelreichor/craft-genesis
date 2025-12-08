<?php

namespace samuelreichor\genesis\jobs;

use craft\i18n\Translation;
use craft\queue\BaseJob;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\models\AssetVolumeData;

/**
 * Import Assets Job
 *
 * Queue job for importing multiple asset volumes from CSV data.
 */
class ImportAssetsJob extends BaseJob
{
    /**
     * @var array Array of asset volume data arrays (serialized AssetVolumeData)
     */
    public array $assetsData = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $importer = Genesis::getInstance()->csvImporterService;
        $total = count($this->assetsData);

        foreach ($this->assetsData as $index => $assetDataArray) {
            $assetData = AssetVolumeData::fromArray($assetDataArray);
            $importer->importAsset($assetData);

            $this->setProgress($queue, ($index + 1) / $total, "Importing asset volume: {$assetData->handle}");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $count = count($this->assetsData);
        return Translation::prep('genesis', 'Importing {count} asset volumes from CSV', [
            'count' => $count,
        ]);
    }
}
