<?php

namespace samuelreichor\genesis\controllers;

use Craft;
use craft\helpers\Queue;
use craft\web\Controller;
use craft\web\UploadedFile;
use samuelreichor\genesis\Genesis;
use samuelreichor\genesis\jobs\ImportAssetsJob;
use samuelreichor\genesis\jobs\ImportEntryTypesJob;
use samuelreichor\genesis\jobs\ImportFilesystemsJob;
use samuelreichor\genesis\jobs\ImportSectionsJob;
use samuelreichor\genesis\jobs\ImportSitesJob;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * CSV Controller
 *
 * Handles CSV file uploads and validation.
 */
class CsvController extends Controller
{
    /**
     * Validates the columns of an uploaded CSV file.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionValidate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $elementType = Craft::$app->getRequest()->getRequiredBodyParam('elementType');
        $csvFile = UploadedFile::getInstanceByName('csvFile');

        if (!$csvFile) {
            return $this->asJson([
                'valid' => false,
                'error' => Craft::t('genesis', 'No CSV file was uploaded.'),
                'invalidColumns' => [],
                'missingRequired' => [],
            ]);
        }

        if ($csvFile->getExtension() !== 'csv') {
            return $this->asJson([
                'valid' => false,
                'error' => Craft::t('genesis', 'Please upload a valid CSV file.'),
                'invalidColumns' => [],
                'missingRequired' => [],
            ]);
        }

        $columns = $this->extractCsvColumns($csvFile->tempName);

        if ($columns === null) {
            return $this->asJson([
                'valid' => false,
                'error' => Craft::t('genesis', 'Could not read the CSV file.'),
                'invalidColumns' => [],
                'missingRequired' => [],
            ]);
        }

        if (empty($columns)) {
            return $this->asJson([
                'valid' => false,
                'error' => Craft::t('genesis', 'The CSV file appears to be empty or has no headers.'),
                'invalidColumns' => [],
                'missingRequired' => [],
            ]);
        }

        $csvService = Genesis::getInstance()->csvValidationService;

        // First validate columns
        $columnResult = $csvService->validateColumns($elementType, $columns);

        if (!$columnResult['valid']) {
            return $this->asJson($columnResult);
        }

        // Then validate row data
        $rows = $this->extractCsvRows($csvFile->tempName);
        $rowResult = $csvService->validateRows($elementType, $columns, $rows);

        return $this->asJson([
            'valid' => $rowResult['valid'],
            'invalidColumns' => [],
            'missingRequired' => [],
            'rowErrors' => $rowResult['rowErrors'],
        ]);
    }

    /**
     * Imports CSV data by pushing a job to the queue.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $elementType = Craft::$app->getRequest()->getRequiredBodyParam('elementType');
        $csvFile = UploadedFile::getInstanceByName('csvFile');

        if (!$csvFile) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'No CSV file was uploaded.'),
            ]);
        }

        if ($csvFile->getExtension() !== 'csv') {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'Please upload a valid CSV file.'),
            ]);
        }

        $columns = $this->extractCsvColumns($csvFile->tempName);

        if ($columns === null || empty($columns)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'Could not read the CSV file.'),
            ]);
        }

        $plugin = Genesis::getInstance();
        $csvValidationService = $plugin->csvValidationService;

        // Validate columns
        $columnResult = $csvValidationService->validateColumns($elementType, $columns);
        if (!$columnResult['valid']) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'CSV validation failed.'),
                'validationErrors' => $columnResult,
            ]);
        }

        // Validate rows
        $rows = $this->extractCsvRows($csvFile->tempName);
        $rowResult = $csvValidationService->validateRows($elementType, $columns, $rows);
        if (!$rowResult['valid']) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'CSV row validation failed.'),
                'validationErrors' => $rowResult,
            ]);
        }

        // Transform and queue the import based on element type
        return match ($elementType) {
            'sites' => $this->importSites($columns, $rows),
            'entryTypes' => $this->importEntryTypes($columns, $rows),
            'sections' => $this->importSections($columns, $rows),
            'filesystems' => $this->importFilesystems($columns, $rows),
            'assets' => $this->importAssets($columns, $rows),
            default => $this->asJson([
                'success' => false,
                'error' => Craft::t('genesis', 'Import for "{type}" is not yet implemented.', ['type' => $elementType]),
            ]),
        };
    }

    /**
     * Transform and queue sites import.
     *
     * @param array $columns
     * @param array $rows
     * @return Response
     */
    private function importSites(array $columns, array $rows): Response
    {
        $transformer = Genesis::getInstance()->csvTransformerService;
        $sitesData = $transformer->transformSites($columns, $rows);

        // Convert SiteData objects to arrays for serialization
        $sitesDataArrays = array_map(fn($site) => $site->toArray(), $sitesData);

        // Push job to queue
        $jobId = Queue::push(new ImportSitesJob([
            'sitesData' => $sitesDataArrays,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('genesis', '{count} sites queued for import.', ['count' => count($sitesData)]),
            'jobId' => $jobId,
        ]);
    }

    /**
     * Transform and queue entry types import.
     *
     * @param array $columns
     * @param array $rows
     * @return Response
     */
    private function importEntryTypes(array $columns, array $rows): Response
    {
        $transformer = Genesis::getInstance()->csvTransformerService;
        $entryTypesData = $transformer->transformEntryTypes($columns, $rows);

        // Convert EntryTypeData objects to arrays for serialization
        $entryTypesDataArrays = array_map(fn($entryType) => $entryType->toArray(), $entryTypesData);

        // Push job to queue
        $jobId = Queue::push(new ImportEntryTypesJob([
            'entryTypesData' => $entryTypesDataArrays,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('genesis', '{count} entry types queued for import.', ['count' => count($entryTypesData)]),
            'jobId' => $jobId,
        ]);
    }

    /**
     * Transform and queue sections import.
     *
     * @param array $columns
     * @param array $rows
     * @return Response
     */
    private function importSections(array $columns, array $rows): Response
    {
        $transformer = Genesis::getInstance()->csvTransformerService;
        $sectionsData = $transformer->transformSections($columns, $rows);

        // Convert SectionData objects to arrays for serialization
        $sectionsDataArrays = array_map(fn($section) => $section->toArray(), $sectionsData);

        // Push job to queue
        $jobId = Queue::push(new ImportSectionsJob([
            'sectionsData' => $sectionsDataArrays,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('genesis', '{count} sections queued for import.', ['count' => count($sectionsData)]),
            'jobId' => $jobId,
        ]);
    }

    /**
     * Transform and queue filesystems import.
     *
     * @param array $columns
     * @param array $rows
     * @return Response
     */
    private function importFilesystems(array $columns, array $rows): Response
    {
        $transformer = Genesis::getInstance()->csvTransformerService;
        $filesystemsData = $transformer->transformFilesystems($columns, $rows);

        // Convert FilesystemData objects to arrays for serialization
        $filesystemsDataArrays = array_map(fn($filesystem) => $filesystem->toArray(), $filesystemsData);

        // Push job to queue
        $jobId = Queue::push(new ImportFilesystemsJob([
            'filesystemsData' => $filesystemsDataArrays,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('genesis', '{count} filesystems queued for import.', ['count' => count($filesystemsData)]),
            'jobId' => $jobId,
        ]);
    }

    /**
     * Transform and queue assets import.
     *
     * @param array $columns
     * @param array $rows
     * @return Response
     */
    private function importAssets(array $columns, array $rows): Response
    {
        $transformer = Genesis::getInstance()->csvTransformerService;
        $assetsData = $transformer->transformAssets($columns, $rows);

        // Convert AssetVolumeData objects to arrays for serialization
        $assetsDataArrays = array_map(fn($asset) => $asset->toArray(), $assetsData);

        // Push job to queue
        $jobId = Queue::push(new ImportAssetsJob([
            'assetsData' => $assetsDataArrays,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => Craft::t('genesis', '{count} asset volumes queued for import.', ['count' => count($assetsData)]),
            'jobId' => $jobId,
        ]);
    }

    /**
     * Extracts column headers from a CSV file.
     *
     * @param string $filePath Path to the CSV file
     * @return array|null Array of column names or null on error
     */
    private function extractCsvColumns(string $filePath): ?array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return null;
        }

        $firstRow = fgetcsv($handle);
        fclose($handle);

        if ($firstRow === false) {
            return null;
        }

        // Trim whitespace and filter out empty columns
        return array_values(array_filter(array_map('trim', $firstRow), function($col) {
            return $col !== '';
        }));
    }

    /**
     * Extracts all data rows from a CSV file (excluding header).
     *
     * @param string $filePath Path to the CSV file
     * @return array Array of rows (each row is an array of values)
     */
    private function extractCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        // Skip header row
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (count(array_filter($row, fn($cell) => $cell !== '')) > 0) {
                $rows[] = array_map('trim', $row);
            }
        }

        fclose($handle);

        return $rows;
    }
}
