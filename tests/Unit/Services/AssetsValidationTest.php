<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\tests\helpers\CsvTestHelper;

/**
 * Assets CSV Validation Tests
 *
 * Tests the validation of CSV data for asset volumes import.
 */
class AssetsValidationTest extends BaseValidationTest
{
    private CsvValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvValidationService();
    }

    // =========================================================================
    // Column Validation Tests
    // =========================================================================

    public function testValidColumnsWithRequiredOnly(): void
    {
        $columns = ['handle', 'name', 'fsHandle'];

        $result = $this->service->validateColumns('assets', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testValidColumnsWithAllAllowed(): void
    {
        $columns = [
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
        ];

        $result = $this->service->validateColumns('assets', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testInvalidColumnsDetected(): void
    {
        $columns = ['handle', 'name', 'fsHandle', 'invalidColumn', 'basePath'];

        $result = $this->service->validateColumns('assets', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('invalidColumn', $result['invalidColumns']);
        $this->assertContains('basePath', $result['invalidColumns']); // basePath is for filesystems, not assets
    }

    public function testMissingRequiredColumnsDetected(): void
    {
        $columns = ['handle', 'name']; // missing 'fsHandle'

        $result = $this->service->validateColumns('assets', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('fsHandle', $result['missingRequired']);
    }

    public function testMissingAllRequiredColumns(): void
    {
        $columns = ['subpath', 'transformFsHandle']; // missing all required

        $result = $this->service->validateColumns('assets', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('handle', $result['missingRequired']);
        $this->assertContains('name', $result['missingRequired']);
        $this->assertContains('fsHandle', $result['missingRequired']);
    }

    // =========================================================================
    // Row Validation Tests - Translation Methods (titleTranslationMethod)
    // =========================================================================

    /**
     * @dataProvider validTranslationMethodsProvider
     */
    public function testValidTitleTranslationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod
test,Test,assets,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Check that translation method validation passed (no translation method error)
        $hasTranslationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'titleTranslationMethod')) {
                    $hasTranslationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTranslationError, "Translation method '{$method}' should be valid for titleTranslationMethod");
    }

    public static function validTranslationMethodsProvider(): array
    {
        return [
            'none' => ['none'],
            'site' => ['site'],
            'siteGroup' => ['siteGroup'],
            'language' => ['language'],
            'custom' => ['custom'],
            'Not translatable label' => ['Not translatable'],
            'Translate for each site label' => ['Translate for each site'],
            'Translate for each site group label' => ['Translate for each site group'],
            'Translate for each language label' => ['Translate for each language'],
        ];
    }

    /**
     * @dataProvider invalidTranslationMethodsProvider
     */
    public function testInvalidTitleTranslationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod
test,Test,assets,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    public static function invalidTranslationMethodsProvider(): array
    {
        return [
            'random string' => ['random'],
            'typo' => ['sit'],
            'partial match' => ['site group'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Translation Methods (altTranslationMethod)
    // =========================================================================

    /**
     * @dataProvider validTranslationMethodsProvider
     */
    public function testValidAltTranslationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,fsHandle,altTranslationMethod
test,Test,assets,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Check that translation method validation passed (no translation method error)
        $hasTranslationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'altTranslationMethod')) {
                    $hasTranslationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTranslationError, "Translation method '{$method}' should be valid for altTranslationMethod");
    }

    /**
     * @dataProvider invalidTranslationMethodsProvider
     */
    public function testInvalidAltTranslationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,fsHandle,altTranslationMethod
test,Test,assets,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    // =========================================================================
    // Row Validation Tests - Custom Translation Method requires KeyFormat
    // =========================================================================

    public function testCustomTitleTranslationMethodRequiresKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat
test,Test,assets,custom,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasKeyFormatError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'titleTranslationKeyFormat')) {
                $hasKeyFormatError = true;
                break;
            }
        }
        $this->assertTrue($hasKeyFormatError);
    }

    public function testCustomTitleTranslationMethodWithKeyFormatIsValid(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat
test,Test,assets,custom,{volume.handle}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have titleTranslationKeyFormat error
        $hasKeyFormatError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'titleTranslationKeyFormat')) {
                    $hasKeyFormatError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasKeyFormatError);
    }

    public function testCustomAltTranslationMethodRequiresKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,altTranslationMethod,altTranslationKeyFormat
test,Test,assets,custom,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasKeyFormatError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'altTranslationKeyFormat')) {
                $hasKeyFormatError = true;
                break;
            }
        }
        $this->assertTrue($hasKeyFormatError);
    }

    public function testCustomAltTranslationMethodWithKeyFormatIsValid(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,altTranslationMethod,altTranslationKeyFormat
test,Test,assets,custom,{asset.id}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have altTranslationKeyFormat error
        $hasKeyFormatError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'altTranslationKeyFormat')) {
                    $hasKeyFormatError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasKeyFormatError);
    }

    public function testCustomLabelTranslationMethodRequiresKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat
test,Test,assets,Custom…,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    public function testNonCustomTranslationMethodDoesNotRequireKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat
test,Test,assets,site,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have titleTranslationKeyFormat error
        $hasKeyFormatError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'titleTranslationKeyFormat') && str_contains($error, 'must be set')) {
                    $hasKeyFormatError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasKeyFormatError);
    }

    // =========================================================================
    // Row Validation Tests - Both Translation Methods Together
    // =========================================================================

    public function testBothTranslationMethodsValidated(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,altTranslationMethod
test,Test,assets,invalid,alsoinvalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        // Should have at least 2 errors (one for each translation method)
        $this->assertGreaterThanOrEqual(2, count($result['rowErrors'][2]));
    }

    public function testBothCustomTranslationMethodsRequireKeyFormats(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat,altTranslationMethod,altTranslationKeyFormat
test,Test,assets,custom,,custom,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasTitleKeyFormatError = false;
        $hasAltKeyFormatError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'titleTranslationKeyFormat')) {
                $hasTitleKeyFormatError = true;
            }
            if (str_contains($error, 'altTranslationKeyFormat')) {
                $hasAltKeyFormatError = true;
            }
        }
        $this->assertTrue($hasTitleKeyFormatError);
        $this->assertTrue($hasAltKeyFormatError);
    }

    public function testValidBothCustomTranslationMethodsWithKeyFormats(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat,altTranslationMethod,altTranslationKeyFormat
test,Test,assets,custom,{volume.handle},custom,{asset.id}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have key format errors
        $hasKeyFormatError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'KeyFormat') && str_contains($error, 'must be set')) {
                    $hasKeyFormatError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasKeyFormatError);
    }

    // =========================================================================
    // Row Validation Tests - Empty/Null Values
    // =========================================================================

    public function testEmptyOptionalFieldsAreValid(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,subpath,transformFsHandle,transformSubpath,titleTranslationMethod,altTranslationMethod
test,Test,assets,,,,,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should only have fsHandle existence error from Craft::$app check
        // No translation method errors for empty values
        $hasTranslationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'TranslationMethod') || str_contains($error, 'TranslationKeyFormat')) {
                    $hasTranslationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTranslationError);
    }

    public function testEmptyTranslationMethodIsValid(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod
test,Test,assets,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have translation method error for empty value
        $hasTranslationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'titleTranslationMethod')) {
                    $hasTranslationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTranslationError);
    }

    // =========================================================================
    // Row Validation Tests - Multiple Rows
    // =========================================================================

    public function testMultipleAssetVolumeRows(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,altTranslationMethod
images,Images,assets,site,site
documents,Documents,assets,none,none
videos,Videos,assets,language,language
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have translation method errors
        $hasTranslationError = false;
        foreach ($result['rowErrors'] ?? [] as $rowErrors) {
            foreach ($rowErrors as $error) {
                if (str_contains($error, 'TranslationMethod')) {
                    $hasTranslationError = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($hasTranslationError);
    }

    public function testErrorsInMultipleRows(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod
valid1,Valid 1,assets,site
invalid1,Invalid 1,assets,notvalid
valid2,Valid 2,assets,none
invalid2,Invalid 2,assets,alsonotvalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        // Rows 3 and 5 should have translation method errors
        $hasRow3TranslationError = false;
        $hasRow5TranslationError = false;

        if (!empty($result['rowErrors'][3])) {
            foreach ($result['rowErrors'][3] as $error) {
                if (str_contains($error, 'titleTranslationMethod') || str_contains($error, 'boolean')) {
                    $hasRow3TranslationError = true;
                    break;
                }
            }
        }

        if (!empty($result['rowErrors'][5])) {
            foreach ($result['rowErrors'][5] as $error) {
                if (str_contains($error, 'titleTranslationMethod') || str_contains($error, 'boolean')) {
                    $hasRow5TranslationError = true;
                    break;
                }
            }
        }

        $this->assertTrue($hasRow3TranslationError, 'Row 3 should have translation method error');
        $this->assertTrue($hasRow5TranslationError, 'Row 5 should have translation method error');
    }

    // =========================================================================
    // Row Validation Tests - Complex Scenarios
    // =========================================================================

    public function testCompleteValidAssetVolumeRow(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,subpath,transformFsHandle,transformSubpath,titleTranslationMethod,titleTranslationKeyFormat,altTranslationMethod,altTranslationKeyFormat
images,Images,assets,images,assets,_transforms,site,,site,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have translation method or key format errors
        $hasValidationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'Translation')) {
                    $hasValidationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasValidationError);
    }

    public function testAssetVolumeWithCustomTranslations(): void
    {
        $csv = <<<CSV
handle,name,fsHandle,titleTranslationMethod,titleTranslationKeyFormat,altTranslationMethod,altTranslationKeyFormat
images,Images,assets,custom,{volume.handle}_{site.handle},custom,{asset.folderId}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('assets', $parsed['columns'], $parsed['rows']);

        // Should not have key format errors
        $hasKeyFormatError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'KeyFormat')) {
                    $hasKeyFormatError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasKeyFormatError);
    }
}
