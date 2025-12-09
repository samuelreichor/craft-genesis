<?php

namespace samuelreichor\genesis\services;

use Craft;
use craft\enums\PropagationMethod;
use craft\fs\Local;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use craft\models\Volume;
use samuelreichor\genesis\models\AssetVolumeData;
use samuelreichor\genesis\models\EntryTypeData;
use samuelreichor\genesis\models\FilesystemData;
use samuelreichor\genesis\models\SectionData;
use samuelreichor\genesis\models\SiteData;
use yii\base\Component;

/**
 * CSV Importer Service
 *
 * Imports transformed data into Craft CMS.
 */
class CsvImporterService extends Component
{
    /**
     * Import or update a site from SiteData.
     *
     * @param SiteData|array $siteData The site data to import
     * @return bool Whether the import was successful
     */
    public function importSite(SiteData|array $siteData): bool
    {
        if (is_array($siteData)) {
            $siteData = SiteData::fromArray($siteData);
        }

        // Check if site with this handle already exists
        $site = Craft::$app->getSites()->getSiteByHandle($siteData->handle);
        $isUpdate = $site !== null;

        if (!$site) {
            $site = new Site();
        }

        $site->groupId = $siteData->groupId;
        $site->name = $siteData->name;
        $site->handle = $siteData->handle;
        $site->language = $siteData->language;
        $site->primary = $siteData->primary;
        $site->hasUrls = $siteData->hasUrls;
        $site->baseUrl = $siteData->baseUrl;
        $site->enabled = $siteData->enabled;

        try {
            $success = Craft::$app->getSites()->saveSite($site);

            if ($success) {
                $action = $isUpdate ? 'updated' : 'imported';
                Craft::info("Successfully {$action} site '{$siteData->handle}'.", 'genesis');
                return true;
            }

            $errors = $site->getErrors();
            Craft::error("Failed to import site '{$siteData->handle}': " . json_encode($errors), 'genesis');
            return false;
        } catch (\Throwable $e) {
            Craft::error("Exception importing site '{$siteData->handle}': " . $e->getMessage(), 'genesis');
            return false;
        }
    }

    /**
     * Import or update an entry type from EntryTypeData.
     *
     * @param EntryTypeData|array $entryTypeData The entry type data to import
     * @return bool Whether the import was successful
     */
    public function importEntryType(EntryTypeData|array $entryTypeData): bool
    {
        if (is_array($entryTypeData)) {
            $entryTypeData = EntryTypeData::fromArray($entryTypeData);
        }

        // Check if entry type with this handle already exists
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeData->handle);
        $isUpdate = $entryType !== null;

        if (!$entryType) {
            $entryType = new EntryType();
        }

        $entryType->name = $entryTypeData->name;
        $entryType->handle = $entryTypeData->handle;
        $entryType->description = $entryTypeData->description;
        $entryType->hasTitleField = true;
        $entryType->titleTranslationMethod = $entryTypeData->titleTranslationMethod;
        $entryType->titleTranslationKeyFormat = $entryTypeData->titleTranslationKeyFormat;
        $entryType->showSlugField = $entryTypeData->showSlug;
        $entryType->slugTranslationMethod = $entryTypeData->slugTranslationMethod;
        $entryType->slugTranslationKeyFormat = $entryTypeData->slugTranslationKeyFormat;
        $entryType->showStatusField = $entryTypeData->showStatusField;

        try {
            $success = Craft::$app->getEntries()->saveEntryType($entryType);

            if ($success) {
                $action = $isUpdate ? 'updated' : 'imported';
                Craft::info("Successfully {$action} entry type '{$entryTypeData->handle}'.", 'genesis');
                return true;
            }

            $errors = $entryType->getErrors();
            Craft::error("Failed to import entry type '{$entryTypeData->handle}': " . json_encode($errors), 'genesis');
            return false;
        } catch (\Throwable $e) {
            Craft::error("Exception importing entry type '{$entryTypeData->handle}': " . $e->getMessage(), 'genesis');
            return false;
        }
    }

    /**
     * Import or update a section from SectionData.
     *
     * @param SectionData|array $sectionData The section data to import
     * @return bool Whether the import was successful
     */
    public function importSection(SectionData|array $sectionData): bool
    {
        if (is_array($sectionData)) {
            $sectionData = SectionData::fromArray($sectionData);
        }

        // Check if section with this handle already exists
        $section = Craft::$app->getEntries()->getSectionByHandle($sectionData->handle);
        $isUpdate = $section !== null;

        if (!$section) {
            $section = new Section();
        }

        // Resolve entry type handles to IDs
        $entryTypeIds = [];
        foreach ($sectionData->entryTypeHandles as $handle) {
            $entryType = Craft::$app->entries->getEntryTypeByHandle($handle);
            if ($entryType) {
                $entryTypeIds[] = $entryType->id;
            } else {
                Craft::warning("Entry type with handle '{$handle}' not found for section '{$sectionData->handle}'.", 'genesis');
            }
        }

        if (empty($entryTypeIds)) {
            Craft::error("No valid entry types found for section '{$sectionData->handle}'.", 'genesis');
            return false;
        }

        // Build site settings
        $siteSettings = $this->buildSiteSettings($sectionData);

        if (empty($siteSettings)) {
            Craft::error("No valid site settings found for section '{$sectionData->handle}'.", 'genesis');
            return false;
        }

        $section->name = $sectionData->name;
        $section->handle = $sectionData->handle;
        $section->type = $sectionData->type;
        $section->enableVersioning = $sectionData->enableVersioning;
        $section->propagationMethod = $this->resolvePropagationMethod($sectionData->propagationMethod);
        $section->maxAuthors = $sectionData->maxAuthors;
        $section->maxLevels = $sectionData->maxLevels;
        $section->defaultPlacement = $sectionData->defaultPlacement ?? Section::DEFAULT_PLACEMENT_END;

        // Set preview targets
        if ($sectionData->enablePreviewTargets && !empty($sectionData->previewTargets)) {
            $section->previewTargets = array_map(fn($pt) => $pt->toArray(), $sectionData->previewTargets);
        } elseif (!$sectionData->enablePreviewTargets) {
            $section->previewTargets = [];
        }

        $section->setSiteSettings($siteSettings);
        $section->setEntryTypes($entryTypeIds);

        try {
            $success = Craft::$app->getEntries()->saveSection($section);

            if ($success) {
                $action = $isUpdate ? 'updated' : 'imported';
                Craft::info("Successfully {$action} section '{$sectionData->handle}'.", 'genesis');
                return true;
            }

            $errors = $section->getErrors();
            Craft::error("Failed to import section '{$sectionData->handle}': " . json_encode($errors), 'genesis');
            return false;
        } catch (\Throwable $e) {
            Craft::error("Exception importing section '{$sectionData->handle}': " . $e->getMessage(), 'genesis');
            return false;
        }
    }

    /**
     * Build site settings array from SectionData.
     *
     * @param SectionData $sectionData
     * @return Section_SiteSettings[]
     */
    private function buildSiteSettings(SectionData $sectionData): array
    {
        $siteSettings = [];

        foreach ($sectionData->siteSettings as $settingsData) {
            $site = Craft::$app->getSites()->getSiteByHandle($settingsData->siteHandle);

            if (!$site) {
                Craft::warning("Site with handle '{$settingsData->siteHandle}' not found for section '{$sectionData->handle}'.", 'genesis');
                continue;
            }

            // For homepage entries, use __home__ as the URI format
            $uriFormat = $settingsData->uriFormat;
            if ($settingsData->isHomepage) {
                $uriFormat = '__home__';
            }

            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'hasUrls' => !empty($uriFormat) || !empty($settingsData->template),
                'uriFormat' => $uriFormat,
                'template' => $settingsData->template,
                'enabledByDefault' => $settingsData->enabledByDefault,
            ]);
        }

        return $siteSettings;
    }

    /**
     * Resolve propagation method string to enum.
     *
     * @param string $method
     * @return PropagationMethod
     */
    private function resolvePropagationMethod(string $method): PropagationMethod
    {
        return match ($method) {
            'none' => PropagationMethod::None,
            'siteGroup' => PropagationMethod::SiteGroup,
            'language' => PropagationMethod::Language,
            'custom' => PropagationMethod::Custom,
            default => PropagationMethod::All,
        };
    }

    /**
     * Import or update a filesystem from FilesystemData.
     *
     * @param FilesystemData|array $filesystemData The filesystem data to import
     * @return bool Whether the import was successful
     */
    public function importFilesystem(FilesystemData|array $filesystemData): bool
    {
        if (is_array($filesystemData)) {
            $filesystemData = FilesystemData::fromArray($filesystemData);
        }

        // Check if filesystem with this handle already exists
        $existingFs = Craft::$app->getFs()->getFilesystemByHandle($filesystemData->handle);
        $isUpdate = $existingFs !== null;

        // Only Local filesystems are supported for import
        if ($existingFs !== null && !$existingFs instanceof Local) {
            Craft::error("Filesystem '{$filesystemData->handle}' exists but is not a Local filesystem. Cannot update.", 'genesis');
            return false;
        }

        $fs = $existingFs instanceof Local ? $existingFs : new Local();

        $fs->name = $filesystemData->name;
        $fs->handle = $filesystemData->handle;
        $fs->path = $filesystemData->basePath;
        $fs->hasUrls = $filesystemData->hasUrls;
        $fs->url = $filesystemData->url;

        try {
            $success = Craft::$app->getFs()->saveFilesystem($fs);

            if ($success) {
                $action = $isUpdate ? 'updated' : 'imported';
                Craft::info("Successfully {$action} filesystem '{$filesystemData->handle}'.", 'genesis');
                return true;
            }

            $errors = $fs->getErrors();
            Craft::error("Failed to import filesystem '{$filesystemData->handle}': " . json_encode($errors), 'genesis');
            return false;
        } catch (\Throwable $e) {
            Craft::error("Exception importing filesystem '{$filesystemData->handle}': " . $e->getMessage(), 'genesis');
            return false;
        }
    }

    /**
     * Import or update an asset volume from AssetVolumeData.
     *
     * @param AssetVolumeData|array $assetData The asset volume data to import
     * @return bool Whether the import was successful
     */
    public function importAsset(AssetVolumeData|array $assetData): bool
    {
        if (is_array($assetData)) {
            $assetData = AssetVolumeData::fromArray($assetData);
        }

        // Check if volume with this handle already exists
        $volume = Craft::$app->getVolumes()->getVolumeByHandle($assetData->handle);
        $isUpdate = $volume !== null;

        if (!$volume) {
            $volume = new Volume();
        }

        $volume->name = $assetData->name;
        $volume->handle = $assetData->handle;
        $volume->fsHandle = $assetData->fsHandle;
        $volume->subpath = $assetData->subpath;
        $volume->setTransformFsHandle($assetData->transformFsHandle);
        $volume->transformSubpath = $assetData->transformSubpath;
        $volume->titleTranslationMethod = $assetData->titleTranslationMethod;
        $volume->titleTranslationKeyFormat = $assetData->titleTranslationKeyFormat;
        $volume->altTranslationMethod = $assetData->altTranslationMethod;
        $volume->altTranslationKeyFormat = $assetData->altTranslationKeyFormat;

        try {
            $success = Craft::$app->getVolumes()->saveVolume($volume);

            if ($success) {
                $action = $isUpdate ? 'updated' : 'imported';
                Craft::info("Successfully {$action} asset volume '{$assetData->handle}'.", 'genesis');
                return true;
            }

            $errors = $volume->getErrors();
            Craft::error("Failed to import asset volume '{$assetData->handle}': " . json_encode($errors), 'genesis');
            return false;
        } catch (\Throwable $e) {
            Craft::error("Exception importing asset volume '{$assetData->handle}': " . $e->getMessage(), 'genesis');
            return false;
        }
    }
}
