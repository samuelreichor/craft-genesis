<?php

namespace samuelreichor\genesis\services;

use Craft;
use samuelreichor\genesis\helpers\Validators;
use samuelreichor\genesis\models\AssetVolumeData;
use samuelreichor\genesis\models\EntryTypeData;
use samuelreichor\genesis\models\FilesystemData;
use samuelreichor\genesis\models\PreviewTargetData;
use samuelreichor\genesis\models\SectionData;
use samuelreichor\genesis\models\SectionSiteSettingsData;
use samuelreichor\genesis\models\SiteData;
use yii\base\Component;

/**
 * CSV Transformer Service
 *
 * Transforms CSV data into structured objects ready for import.
 */
class CsvTransformerService extends Component
{
    /**
     * Transform CSV rows into SiteData objects.
     *
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows
     * @return SiteData[]
     */
    public function transformSites(array $columns, array $rows): array
    {
        $sites = [];

        foreach ($rows as $row) {
            $rowData = array_combine($columns, $row);
            $sites[] = $this->transformSiteRow($rowData);
        }

        return $sites;
    }

    /**
     * Transform a single CSV row into a SiteData object.
     *
     * @param array $rowData Associative array of column => value
     * @return SiteData
     */
    private function transformSiteRow(array $rowData): SiteData
    {
        return new SiteData(
            handle: $rowData['handle'],
            name: $rowData['name'],
            language: $rowData['language'],
            baseUrl: !empty($rowData['baseUrl']) ? $rowData['baseUrl'] : null,
            primary: isset($rowData['primary']) && Validators::isTruthy($rowData['primary']),
            hasUrls: !isset($rowData['hasUrls']) || Validators::isTruthy($rowData['hasUrls']),
            enabled: !isset($rowData['enabled']) || Validators::isTruthy($rowData['enabled']),
            groupId: $this->resolveGroupId($rowData['group'] ?? null),
        );
    }

    /**
     * Resolve a group name to its ID.
     *
     * @param string|null $groupName The group name
     * @return int|null The group ID or null if not found
     */
    private function resolveGroupId(?string $groupName): ?int
    {
        if (empty($groupName)) {
            // Return the first group's ID as default
            $groups = Craft::$app->getSites()->getAllGroups();
            return !empty($groups) ? $groups[0]->id : null;
        }

        $allGroups = Craft::$app->getSites()->getAllGroups();

        foreach ($allGroups as $group) {
            if ($group->getName() === $groupName) {
                return $group->id;
            }
        }

        return null;
    }

    /**
     * Transform CSV rows into EntryTypeData objects.
     *
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows
     * @return EntryTypeData[]
     */
    public function transformEntryTypes(array $columns, array $rows): array
    {
        $entryTypes = [];

        foreach ($rows as $row) {
            $rowData = array_combine($columns, $row);
            $entryTypes[] = $this->transformEntryTypeRow($rowData);
        }

        return $entryTypes;
    }

    /**
     * Transform a single CSV row into an EntryTypeData object.
     *
     * @param array $rowData Associative array of column => value
     * @return EntryTypeData
     */
    private function transformEntryTypeRow(array $rowData): EntryTypeData
    {
        return new EntryTypeData(
            handle: $rowData['handle'],
            name: $rowData['name'],
            description: !empty($rowData['description']) ? $rowData['description'] : null,
            titleTranslationMethod: $this->normalizeTranslationMethod($rowData['titleTranslationMethod'] ?? 'site'),
            titleTranslationKeyFormat: !empty($rowData['titleTranslationKeyFormat']) ? $rowData['titleTranslationKeyFormat'] : null,
            showSlug: !isset($rowData['showSlug']) || Validators::isTruthy($rowData['showSlug']),
            slugTranslationMethod: $this->normalizeTranslationMethod($rowData['slugTranslationMethod'] ?? 'site'),
            slugTranslationKeyFormat: !empty($rowData['slugTranslationKeyFormat']) ? $rowData['slugTranslationKeyFormat'] : null,
            showStatusField: !isset($rowData['showStatusField']) || Validators::isTruthy($rowData['showStatusField']),
        );
    }

    /**
     * Normalize translation method labels to internal values.
     *
     * @param string $method The translation method (label or internal value)
     * @return string The internal translation method value
     */
    private function normalizeTranslationMethod(string $method): string
    {
        $mapping = [
            'Not translatable' => 'none',
            'Translate for each site' => 'site',
            'Translate for each site group' => 'siteGroup',
            'Translate for each language' => 'language',
            'Custom…' => 'custom',
        ];

        return $mapping[$method] ?? $method;
    }

    /**
     * Transform CSV rows into SectionData objects.
     * Multiple rows with the same handle are grouped into one section with multiple site settings.
     *
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows
     * @return SectionData[]
     */
    public function transformSections(array $columns, array $rows): array
    {
        // Group rows by handle
        $grouped = [];
        foreach ($rows as $row) {
            $rowData = array_combine($columns, $row);
            $handle = $rowData['handle'];

            if (!isset($grouped[$handle])) {
                $grouped[$handle] = [];
            }
            $grouped[$handle][] = $rowData;
        }

        // Transform each group into a SectionData
        $sections = [];
        foreach ($grouped as $handle => $rowsForHandle) {
            $sections[] = $this->transformSectionGroup($rowsForHandle);
        }

        return $sections;
    }

    /**
     * Transform a group of CSV rows (same handle) into a SectionData object.
     *
     * @param array $rows Array of row data with the same handle
     * @return SectionData
     */
    private function transformSectionGroup(array $rows): SectionData
    {
        // Use first row for section-level properties
        $firstRow = $rows[0];

        // Parse entry types (comma-separated handles)
        $entryTypeHandles = [];
        if (!empty($firstRow['entryTypes'])) {
            $entryTypeHandles = array_map('trim', explode(',', $firstRow['entryTypes']));
        }

        // Collect site settings from all rows
        $siteSettings = [];
        foreach ($rows as $rowData) {
            if (!empty($rowData['site'])) {
                $siteSettings[] = new SectionSiteSettingsData(
                    siteHandle: $rowData['site'],
                    uriFormat: !empty($rowData['siteUri']) ? $rowData['siteUri'] : null,
                    template: !empty($rowData['siteTemplate']) ? $rowData['siteTemplate'] : null,
                    enabledByDefault: !isset($rowData['siteDefaultStatus']) || Validators::isTruthy($rowData['siteDefaultStatus']),
                    isHomepage: isset($rowData['siteHome']) && Validators::isTruthy($rowData['siteHome']),
                );
            }
        }

        // Determine if preview targets are enabled (default: true)
        $enablePreviewTargets = !isset($firstRow['enablePreviewTargets']) || Validators::isTruthy($firstRow['enablePreviewTargets']);

        // Collect preview targets from all rows (only if enabled)
        $previewTargets = [];
        if ($enablePreviewTargets) {
            foreach ($rows as $rowData) {
                if (!empty($rowData['previewTargetLabel']) && !empty($rowData['previewTargetUrlFormat'])) {
                    $previewTargets[] = new PreviewTargetData(
                        label: $rowData['previewTargetLabel'],
                        urlFormat: $rowData['previewTargetUrlFormat'],
                        refresh: !isset($rowData['previewTargetAutoRefresh']) || Validators::isTruthy($rowData['previewTargetAutoRefresh']),
                    );
                }
            }
        }

        return new SectionData(
            handle: $firstRow['handle'],
            name: $firstRow['name'],
            type: strtolower(trim($firstRow['type'])),
            entryTypeHandles: $entryTypeHandles,
            siteSettings: $siteSettings,
            propagationMethod: $this->normalizePropagationMethod($firstRow['propagationMethod'] ?? 'all'),
            maxAuthors: !empty($firstRow['maxAuthors']) ? (int)$firstRow['maxAuthors'] : 1,
            maxLevels: !empty($firstRow['maxLevels']) ? (int)$firstRow['maxLevels'] : null,
            defaultPlacement: $this->normalizeDefaultPlacement($firstRow['defaultPlacement'] ?? null),
            enableVersioning: !isset($firstRow['enableVersioning']) || Validators::isTruthy($firstRow['enableVersioning']),
            enablePreviewTargets: $enablePreviewTargets,
            previewTargets: $previewTargets,
        );
    }

    /**
     * Normalize propagation method labels to internal values.
     *
     * @param string $method The propagation method (label or internal value)
     * @return string The internal propagation method value
     */
    private function normalizePropagationMethod(string $method): string
    {
        $mapping = [
            'Only save entries to the site they were created in' => 'none',
            'Save entries to other sites in the same site group' => 'siteGroup',
            'Save entries to other sites with the same language' => 'language',
            'Save entries to all sites enabled for this section' => 'all',
            'Let each entry choose which sites it should be saved to' => 'custom',
        ];

        return $mapping[$method] ?? $method;
    }

    /**
     * Normalize default placement labels to internal values.
     *
     * @param string|null $placement The default placement (label or internal value)
     * @return string|null The internal default placement value
     */
    private function normalizeDefaultPlacement(?string $placement): ?string
    {
        if (empty($placement)) {
            return null;
        }

        $mapping = [
            'Before other entries' => 'beginning',
            'After other entries' => 'end',
        ];

        return $mapping[$placement] ?? $placement;
    }

    /**
     * Transform CSV rows into FilesystemData objects.
     *
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows
     * @return FilesystemData[]
     */
    public function transformFilesystems(array $columns, array $rows): array
    {
        $filesystems = [];

        foreach ($rows as $row) {
            $rowData = array_combine($columns, $row);
            $filesystems[] = $this->transformFilesystemRow($rowData);
        }

        return $filesystems;
    }

    /**
     * Transform a single CSV row into a FilesystemData object.
     *
     * @param array $rowData Associative array of column => value
     * @return FilesystemData
     */
    private function transformFilesystemRow(array $rowData): FilesystemData
    {
        return new FilesystemData(
            handle: $rowData['handle'],
            name: $rowData['name'],
            basePath: $rowData['basePath'],
            hasUrls: isset($rowData['publicUrls']) && Validators::isTruthy($rowData['publicUrls']),
            url: !empty($rowData['baseUrl']) ? $rowData['baseUrl'] : null,
        );
    }

    /**
     * Transform CSV rows into AssetVolumeData objects.
     *
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows
     * @return AssetVolumeData[]
     */
    public function transformAssets(array $columns, array $rows): array
    {
        $assets = [];

        foreach ($rows as $row) {
            $rowData = array_combine($columns, $row);
            $assets[] = $this->transformAssetRow($rowData);
        }

        return $assets;
    }

    /**
     * Transform a single CSV row into an AssetVolumeData object.
     *
     * @param array $rowData Associative array of column => value
     * @return AssetVolumeData
     */
    private function transformAssetRow(array $rowData): AssetVolumeData
    {
        return new AssetVolumeData(
            handle: $rowData['handle'],
            name: $rowData['name'],
            fsHandle: $rowData['fsHandle'],
            subpath: !empty($rowData['subpath']) ? $rowData['subpath'] : null,
            transformFsHandle: !empty($rowData['transformFsHandle']) ? $rowData['transformFsHandle'] : null,
            transformSubpath: !empty($rowData['transformSubpath']) ? $rowData['transformSubpath'] : null,
            titleTranslationMethod: $this->normalizeTranslationMethod($rowData['titleTranslationMethod'] ?? 'site'),
            titleTranslationKeyFormat: !empty($rowData['titleTranslationKeyFormat']) ? $rowData['titleTranslationKeyFormat'] : null,
            altTranslationMethod: $this->normalizeTranslationMethod($rowData['altTranslationMethod'] ?? 'site'),
            altTranslationKeyFormat: !empty($rowData['altTranslationKeyFormat']) ? $rowData['altTranslationKeyFormat'] : null,
        );
    }
}
