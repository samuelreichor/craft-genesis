<?php

namespace samuelreichor\genesis\services;

use Craft;
use samuelreichor\genesis\helpers\Validators;
use yii\base\Component;

/**
 * CSV Validation Service
 *
 * Validates CSV column headers and row data against allowed properties for each element type.
 */
class CsvValidationService extends Component
{
    /**
     * Allowed columns per element type
     */
    private const ALLOWED_COLUMNS = [
        'sites' => [
            'handle',
            'name',
            'language',
            'baseUrl',
            'primary',
            'hasUrls',
            'enabled',
            'group',
        ],
        'entryTypes' => [
            'handle',
            'name',
            'description',
            'titleTranslationMethod',
            'titleTranslationKeyFormat',
            'showSlug',
            'slugTranslationMethod',
            'slugTranslationKeyFormat',
            'showStatusField',
        ],
        'sections' => [
            'handle',
            'name',
            'type',
            'entryTypes',
            'site',
            'siteUri',
            'siteTemplate',
            'siteHome',
            'siteDefaultStatus',
            'enableVersioning',
            'propagationMethod',
            'maxAuthors',
            'maxLevels',
            'defaultPlacement',
            'enablePreviewTargets',
            'previewTargetLabel',
            'previewTargetUrlFormat',
            'previewTargetAutoRefresh',
        ],
        'filesystems' => [
            'handle',
            'name',
            'basePath',
            'publicUrls',
            'baseUrl',
        ],
        'assets' => [
            'handle',
            'name',
            'fsHandle',
            'subpath',
            'transformFsHandle',
            'transformSubpath',
            'titleTranslationMethod',
            'titleTranslationKeyFormat',
            'altTranslationMethod',
            'altTranslationKeyFormat',
        ],
    ];

    /**
     * Required columns per element type
     */
    private const REQUIRED_COLUMNS = [
        'sites' => ['handle', 'name', 'language'],
        'entryTypes' => ['handle', 'name'],
        'sections' => ['handle', 'name', 'type', 'entryTypes'],
        'fields' => ['handle', 'name'],
        'filesystems' => ['handle', 'name', 'basePath'],
        'assets' => ['handle', 'name', 'fsHandle'],
    ];

    /**
     * Validates CSV columns against allowed and required columns for the given element type.
     *
     * @param string $elementType The element type (sites, sections, fields, filesystems, assets)
     * @param array $columns The CSV column headers to validate
     * @return array{valid: bool, invalidColumns: array, missingRequired: array}
     */
    public function validateColumns(string $elementType, array $columns): array
    {
        if (!isset(self::ALLOWED_COLUMNS[$elementType])) {
            return [
                'valid' => false,
                'invalidColumns' => [],
                'missingRequired' => [],
                'error' => "Unknown element type: {$elementType}",
            ];
        }

        $allowedColumns = self::ALLOWED_COLUMNS[$elementType];
        $requiredColumns = self::REQUIRED_COLUMNS[$elementType];

        // Find invalid columns (not in allowed list)
        $invalidColumns = array_diff($columns, $allowedColumns);

        // Find missing required columns
        $missingRequired = array_diff($requiredColumns, $columns);

        return [
            'valid' => empty($invalidColumns) && empty($missingRequired),
            'invalidColumns' => array_values($invalidColumns),
            'missingRequired' => array_values($missingRequired),
        ];
    }

    /**
     * Returns the list of allowed columns for a given element type.
     *
     * @param string $elementType The element type
     * @return array
     */
    public function getAllowedColumns(string $elementType): array
    {
        return self::ALLOWED_COLUMNS[$elementType] ?? [];
    }

    /**
     * Returns the list of required columns for a given element type.
     *
     * @param string $elementType The element type
     * @return array
     */
    public function getRequiredColumns(string $elementType): array
    {
        return self::REQUIRED_COLUMNS[$elementType] ?? [];
    }

    /**
     * Returns all supported element types.
     *
     * @return array
     */
    public function getSupportedElementTypes(): array
    {
        return array_keys(self::ALLOWED_COLUMNS);
    }

    /**
     * Validates CSV data rows for a given element type.
     *
     * @param string $elementType The element type
     * @param array $columns The CSV column headers
     * @param array $rows The CSV data rows (array of arrays)
     * @return array{valid: bool, rowErrors: array}
     */
    public function validateRows(string $elementType, array $columns, array $rows): array
    {
        $rowErrors = [];

        foreach ($rows as $rowIndex => $row) {
            $rowData = array_combine($columns, $row);
            $rowNumber = $rowIndex + 2; // +2 because row 1 is headers, and the index starts at 0

            $errors = match ($elementType) {
                'sites' => $this->validateSiteRow($rowData, $rowNumber),
                'entryTypes' => $this->validateEntryTypeRow($rowData, $rowNumber),
                'sections' => $this->validateSectionRow($rowData, $rowNumber),
                'filesystems' => $this->validateFilesystemRow($rowData, $rowNumber),
                'assets' => $this->validateAssetRow($rowData, $rowNumber),
                default => [],
            };

            if (!empty($errors)) {
                $rowErrors[$rowNumber] = $errors;
            }
        }

        return [
            'valid' => empty($rowErrors),
            'rowErrors' => $rowErrors,
        ];
    }

    /**
     * Validates a single site row.
     *
     * @param array $rowData Associative array of column => value
     * @param int $rowNumber The row number for error messages
     * @return array Array of error messages
     */
    private function validateSiteRow(array $rowData, int $rowNumber): array
    {
        $errors = [];

        // Validate boolean fields
        $errors = array_merge($errors, $this->validateBooleanValues(['primary', 'hasUrls', 'enabled'], $rowData, $rowNumber));

        // Validate language is a valid ISO code
        $language = $rowData['language'] ?? null;
        if (!empty($language) && !Validators::isValidLanguageCode($language)) {
            $errors[] = Craft::t('genesis', 'Row {row}: "{language}" is not a valid language code (e.g., en, en-US, de-DE).', [
                'row' => $rowNumber,
                'language' => $language,
            ]);
        }

        // If hasUrls is true, baseUrl must be set
        $hasUrls = $rowData['hasUrls'] ?? null;
        $baseUrl = $rowData['baseUrl'] ?? null;

        if (Validators::isTruthy($hasUrls) && empty($baseUrl)) {
            $errors[] = Craft::t('genesis', 'Row {row}: When "hasUrls" is true, "baseUrl" must be set.', [
                'row' => $rowNumber,
            ]);
        }

        // Validate group label exists
        $group = $rowData['group'] ?? null;
        if (!empty($group)) {
            if (!Validators::siteGroupExists($group)) {
                $errors[] = Craft::t('genesis', 'Row {row}: Site group "{group}" not found.', [
                    'row' => $rowNumber,
                    'group' => $group,
                ]);
            }
        }

        return $errors;
    }

    public function validateEntryTypeRow(array $rowData, int $rowNumber): array
    {
        $errors = [];

        // Validate boolean fields
        $errors = array_merge($errors, $this->validateBooleanValues(['showSlug', 'showStatusField'], $rowData, $rowNumber));

        // Validate title translation method
        $errors = array_merge($errors, $this->validateTranslationMethod('titleTranslationMethod', 'titleTranslationKeyFormat', $rowData, $rowNumber));

        // Validate slug translation method if slug is enabled
        $showSlug = $rowData['showSlug'] ?? null;
        if (!empty($showSlug) && Validators::isTruthy($showSlug)) {
            $errors = array_merge($errors, $this->validateTranslationMethod('slugTranslationMethod', 'slugTranslationKeyFormat', $rowData, $rowNumber));
        }

        return $errors;
    }

    /**
     * Validates a single section row.
     *
     * @param array $rowData Associative array of column => value
     * @param int $rowNumber The row number for error messages
     * @return array Array of error messages
     */
    private function validateSectionRow(array $rowData, int $rowNumber): array
    {
        $errors = [];
        $type = strtolower(trim($rowData['type'] ?? ''));

        // Validate type
        if (!empty($type) && !Validators::isValidSectionType($type)) {
            $errors[] = Craft::t('genesis', 'Row {row}: "{type}" is not a valid section type. Must be single, channel, or structure.', [
                'row' => $rowNumber,
                'type' => $rowData['type'],
            ]);
        }

        // Validate propagationMethod
        $propagationMethod = $rowData['propagationMethod'] ?? null;
        if (!empty($propagationMethod) && !Validators::isValidPropagationMethod($propagationMethod)) {
            $errors[] = Craft::t('genesis', 'Row {row}: "{value}" is not a valid propagation method.', [
                'row' => $rowNumber,
                'value' => $propagationMethod,
            ]);
        }

        // Type-conditional validation
        $maxLevels = $rowData['maxLevels'] ?? null;
        $defaultPlacement = $rowData['defaultPlacement'] ?? null;
        $siteHome = $rowData['siteHome'] ?? null;

        if ($type === 'structure') {
            // maxLevels allowed - validate if set
            if (!empty($maxLevels) && !Validators::isPositiveInteger($maxLevels)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "maxLevels" must be a positive integer.', [
                    'row' => $rowNumber,
                ]);
            }
            // defaultPlacement allowed - validate if set
            if (!empty($defaultPlacement) && !Validators::isValidDefaultPlacement($defaultPlacement)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "{value}" is not a valid default placement.', [
                    'row' => $rowNumber,
                    'value' => $defaultPlacement,
                ]);
            }
            // siteHome NOT allowed for structure
            if (!empty($siteHome)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "siteHome" is only allowed for single sections.', [
                    'row' => $rowNumber,
                ]);
            }
        } elseif ($type === 'single') {
            // siteHome allowed (boolean)
            $errors = array_merge($errors, $this->validateBooleanValues(['siteHome'], $rowData, $rowNumber));
            // maxLevels NOT allowed
            if (!empty($maxLevels)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "maxLevels" is only allowed for structure sections.', [
                    'row' => $rowNumber,
                ]);
            }
            // defaultPlacement NOT allowed
            if (!empty($defaultPlacement)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "defaultPlacement" is only allowed for structure sections.', [
                    'row' => $rowNumber,
                ]);
            }
        } elseif ($type === 'channel') {
            // maxLevels NOT allowed
            if (!empty($maxLevels)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "maxLevels" is only allowed for structure sections.', [
                    'row' => $rowNumber,
                ]);
            }
            // defaultPlacement NOT allowed
            if (!empty($defaultPlacement)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "defaultPlacement" is only allowed for structure sections.', [
                    'row' => $rowNumber,
                ]);
            }
            // siteHome NOT allowed
            if (!empty($siteHome)) {
                $errors[] = Craft::t('genesis', 'Row {row}: "siteHome" is only allowed for single sections.', [
                    'row' => $rowNumber,
                ]);
            }
        }

        // Validate site handle exists
        $site = $rowData['site'] ?? null;
        if (!empty($site) && !Validators::siteExists($site)) {
            $errors[] = Craft::t('genesis', 'Row {row}: Site with handle "{handle}" not found.', [
                'row' => $rowNumber,
                'handle' => $site,
            ]);
        }

        // Validate siteUri and siteTemplate dependency
        $siteUri = $rowData['siteUri'] ?? null;
        $siteTemplate = $rowData['siteTemplate'] ?? null;
        if (!empty($siteUri) && empty($siteTemplate)) {
            $errors[] = Craft::t('genesis', 'Row {row}: When "siteUri" is set, "siteTemplate" must also be set.', [
                'row' => $rowNumber,
            ]);
        }
        if (!empty($siteTemplate) && empty($siteUri) && $type !== 'single' && !Validators::isTruthy($siteHome)) {
            $errors[] = Craft::t('genesis', 'Row {row}: When "siteTemplate" is set, "siteUri" must also be set.', [
                'row' => $rowNumber,
            ]);
        }

        // Validate entry types (comma-separated handles)
        $entryTypes = $rowData['entryTypes'] ?? null;
        if (!empty($entryTypes)) {
            $handles = array_map('trim', explode(',', $entryTypes));
            foreach ($handles as $handle) {
                if (!empty($handle) && !Validators::entryTypeExists($handle)) {
                    $errors[] = Craft::t('genesis', 'Row {row}: Entry type with handle "{handle}" not found.', [
                        'row' => $rowNumber,
                        'handle' => $handle,
                    ]);
                }
            }
        }


        // Validate boolean fields
        $errors = array_merge($errors, $this->validateBooleanValues(['siteDefaultStatus'], $rowData, $rowNumber));

        return $errors;
    }

    /**
     * Validates a single filesystem row.
     *
     * @param array $rowData Associative array of column => value
     * @param int $rowNumber The row number for error messages
     * @return array Array of error messages
     */
    private function validateFilesystemRow(array $rowData, int $rowNumber): array
    {
        $errors = [];

        // Validate boolean fields
        $errors = array_merge($errors, $this->validateBooleanValues(['publicUrls'], $rowData, $rowNumber));

        // If publicUrls is true, baseUrl must be set
        $publicUrls = $rowData['publicUrls'] ?? null;
        $baseUrl = $rowData['baseUrl'] ?? null;

        if (!empty($publicUrls) && Validators::isTruthy($publicUrls) && empty($baseUrl)) {
            $errors[] = Craft::t('genesis', 'Row {row}: When "publicUrls" is true, "baseUrl" must be set.', [
                'row' => $rowNumber,
            ]);
        }

        return $errors;
    }

    /**
     * Validates a single asset row.
     *
     * @param array $rowData Associative array of column => value
     * @param int $rowNumber The row number for error messages
     * @return array Array of error messages
     */
    private function validateAssetRow(array $rowData, int $rowNumber): array
    {
        $errors = [];

        // Validate fsHandle exists (required)
        $fsHandle = $rowData['fsHandle'] ?? null;
        if (!empty($fsHandle) && !Validators::filesystemExists($fsHandle)) {
            $errors[] = Craft::t('genesis', 'Row {row}: Filesystem with handle "{handle}" not found.', [
                'row' => $rowNumber,
                'handle' => $fsHandle,
            ]);
        }

        // Validate transformFsHandle exists (optional)
        $transformFsHandle = $rowData['transformFsHandle'] ?? null;
        if (!empty($transformFsHandle) && !Validators::filesystemExists($transformFsHandle)) {
            $errors[] = Craft::t('genesis', 'Row {row}: Transform filesystem with handle "{handle}" not found.', [
                'row' => $rowNumber,
                'handle' => $transformFsHandle,
            ]);
        }

        // Validate title translation method
        $errors = array_merge($errors, $this->validateTranslationMethod('titleTranslationMethod', 'titleTranslationKeyFormat', $rowData, $rowNumber));

        // Validate alt translation method
        $errors = array_merge($errors, $this->validateTranslationMethod('altTranslationMethod', 'altTranslationKeyFormat', $rowData, $rowNumber));

        return $errors;
    }

    private function validateBooleanValues(array $columns, array $rowData, int $rowNumber): array
    {
        $errors = [];

        foreach ($columns as $column) {
            if (array_key_exists($column, $rowData) && !empty($rowData[$column])) {
                if (!Validators::isValidBooleanString($rowData[$column])) {
                    $errors[] = Craft::t('genesis', 'Row {row}: "{column}" must be a boolean value (true/false, 1/0, yes/no).', [
                        'row' => $rowNumber,
                        'column' => $column,
                    ]);
                }
            }
        }

        return $errors;
    }

    private function validateTranslationMethod(string $columnsMethod, string $columnsFormat, array $rowData, int $rowNumber): array
    {
        $errors = [];

        if (!array_key_exists($columnsMethod, $rowData) || empty($rowData[$columnsMethod])) {
            return $errors;
        }

        $method = $rowData[$columnsMethod];

        if (!Validators::isValidTranslationMethod($method)) {
            $errors[] = Craft::t('genesis', 'Row {row}: "{column}" must be a boolean value (true/false, 1/0, yes/no).', [
                'row' => $rowNumber,
                'column' => $columnsMethod,
            ]);

            return $errors;
        }

        if (Validators::isValidCustomTranslationMethod($method)) {
            if (!array_key_exists($columnsFormat, $rowData) || $rowData[$columnsFormat] === '') {
                $errors[] = Craft::t('genesis', 'Row {row}: "{column}" must be set when using custom translation methods.', [
                    'row' => $rowNumber,
                    'column' => $columnsFormat,
                ]);
            }
        }

        return $errors;
    }
}
