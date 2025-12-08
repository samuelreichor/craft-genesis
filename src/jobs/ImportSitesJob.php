<?php

namespace samuelreichor\genesis\jobs;

use craft\i18n\Translation;
use craft\queue\BaseJob;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\models\SiteData;

/**
 * Import Sites Job
 *
 * Queue job for importing multiple sites from CSV data.
 */
class ImportSitesJob extends BaseJob
{
    /**
     * @var array Array of site data arrays (serialized SiteData)
     */
    public array $sitesData = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $importer = Genesis::getInstance()->csvImporterService;
        $total = count($this->sitesData);

        foreach ($this->sitesData as $index => $siteDataArray) {
            $siteData = SiteData::fromArray($siteDataArray);
            $importer->importSite($siteData);

            $this->setProgress($queue, ($index + 1) / $total, "Importing site: {$siteData->handle}");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $count = count($this->sitesData);
        return Translation::prep('genesis', 'Importing {count} sites from CSV', [
            'count' => $count,
        ]);
    }
}
