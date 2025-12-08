<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\tests\helpers\CsvTestHelper;

/**
 * Filesystems CSV Validation Tests
 *
 * Tests the validation of CSV data for filesystems import.
 */
class FilesystemsValidationTest extends BaseValidationTest
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
        $columns = ['handle', 'name', 'basePath'];

        $result = $this->service->validateColumns('filesystems', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testValidColumnsWithAllAllowed(): void
    {
        $columns = ['handle', 'name', 'basePath', 'publicUrls', 'baseUrl'];

        $result = $this->service->validateColumns('filesystems', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testInvalidColumnsDetected(): void
    {
        $columns = ['handle', 'name', 'basePath', 'invalidColumn', 'fsHandle'];

        $result = $this->service->validateColumns('filesystems', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('invalidColumn', $result['invalidColumns']);
        $this->assertContains('fsHandle', $result['invalidColumns']);
    }

    public function testMissingRequiredColumnsDetected(): void
    {
        $columns = ['handle', 'name']; // missing 'basePath'

        $result = $this->service->validateColumns('filesystems', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('basePath', $result['missingRequired']);
    }

    public function testMissingAllRequiredColumns(): void
    {
        $columns = ['publicUrls', 'baseUrl']; // missing all required

        $result = $this->service->validateColumns('filesystems', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('handle', $result['missingRequired']);
        $this->assertContains('name', $result['missingRequired']);
        $this->assertContains('basePath', $result['missingRequired']);
    }

    // =========================================================================
    // Row Validation Tests - Valid Cases
    // =========================================================================

    public function testValidMinimalFilesystemRow(): void
    {
        $csv = <<<CSV
handle,name,basePath
assets,Assets,@webroot/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidFilesystemRowWithAllFields(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
assets,Assets,@webroot/assets,true,@web/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidMultipleFilesystemRows(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
assets,Assets,@webroot/assets,true,@web/assets
uploads,Uploads,@webroot/uploads,true,@web/uploads
private,Private,@storage/private,false,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidFilesystemWithPrivateStorage(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls
private,Private Storage,@storage/private,false
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - publicUrls Boolean Field
    // =========================================================================

    /**
     * @dataProvider validBooleanValuesProvider
     */
    public function testValidBooleanValuesForPublicUrls(string $value): void
    {
        // Need to provide baseUrl when publicUrls is truthy
        $baseUrl = in_array(strtolower($value), ['true', '1', 'yes', 'on']) ? '@web/assets' : '';

        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,{$value},{$baseUrl}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        // Check for boolean validation error specifically
        $hasBoolError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'publicUrls') && str_contains($error, 'boolean')) {
                    $hasBoolError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasBoolError, "Boolean value '{$value}' should be valid for publicUrls");
    }

    public static function validBooleanValuesProvider(): array
    {
        return [
            'true lowercase' => ['true'],
            'false lowercase' => ['false'],
            'TRUE uppercase' => ['TRUE'],
            'FALSE uppercase' => ['FALSE'],
            'yes' => ['yes'],
            'no' => ['no'],
            '1' => ['1'],
            '0' => ['0'],
            'on' => ['on'],
            'off' => ['off'],
        ];
    }

    /**
     * @dataProvider invalidBooleanValuesProvider
     */
    public function testInvalidBooleanValuesForPublicUrls(string $value): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls
test,Test,@webroot/test,{$value}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasBoolError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'publicUrls') && str_contains($error, 'boolean')) {
                $hasBoolError = true;
                break;
            }
        }
        $this->assertTrue($hasBoolError, "Boolean value '{$value}' should be invalid for publicUrls");
    }

    public static function invalidBooleanValuesProvider(): array
    {
        return [
            'random string' => ['maybe'],
            'number other than 0/1' => ['2'],
            'typo' => ['tru'],
            'german ja' => ['ja'],
            'partial' => ['ye'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - publicUrls/baseUrl Dependency
    // =========================================================================

    public function testPublicUrlsTrueRequiresBaseUrl(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,true,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('baseUrl', $result['rowErrors'][2][0]);
    }

    public function testPublicUrlsTrueWithBaseUrlIsValid(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,true,@web/test
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testPublicUrlsFalseDoesNotRequireBaseUrl(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls
test,Test,@storage/private,false
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testPublicUrlsEmptyDoesNotRequireBaseUrl(): void
    {
        // When publicUrls is empty/not set, baseUrl is not required
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@storage/private,,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testBaseUrlWithoutPublicUrlsIsValid(): void
    {
        // baseUrl can be provided even without publicUrls being explicitly set
        $csv = <<<CSV
handle,name,basePath,baseUrl
test,Test,@webroot/test,@web/test
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    /**
     * @dataProvider truthyPublicUrlsRequiresBaseUrlProvider
     */
    public function testAllTruthyPublicUrlsValuesRequireBaseUrl(string $publicUrlsValue): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,{$publicUrlsValue},
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid'], "publicUrls='{$publicUrlsValue}' should require baseUrl");
        $hasBaseUrlError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'baseUrl')) {
                $hasBaseUrlError = true;
                break;
            }
        }
        $this->assertTrue($hasBaseUrlError, "publicUrls='{$publicUrlsValue}' should require baseUrl");
    }

    public static function truthyPublicUrlsRequiresBaseUrlProvider(): array
    {
        return [
            'true' => ['true'],
            'TRUE' => ['TRUE'],
            '1' => ['1'],
            'yes' => ['yes'],
            'YES' => ['YES'],
            'on' => ['on'],
        ];
    }

    /**
     * @dataProvider falsyPublicUrlsDoesNotRequireBaseUrlProvider
     */
    public function testAllFalsyPublicUrlsValuesDoNotRequireBaseUrl(string $publicUrlsValue): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,{$publicUrlsValue},
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "publicUrls='{$publicUrlsValue}' should not require baseUrl");
    }

    public static function falsyPublicUrlsDoesNotRequireBaseUrlProvider(): array
    {
        return [
            'false' => ['false'],
            'FALSE' => ['FALSE'],
            '0' => ['0'],
            'no' => ['no'],
            'NO' => ['NO'],
            'off' => ['off'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - basePath Formats
    // =========================================================================

    public function testValidBasePathWithAlias(): void
    {
        $csv = <<<CSV
handle,name,basePath
test1,Test 1,@webroot/assets
test2,Test 2,@storage/private
test3,Test 3,@root/files
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testValidBasePathWithAbsolutePath(): void
    {
        $csv = <<<CSV
handle,name,basePath
test,Test,/var/www/html/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testValidBasePathWithEnvironmentVariable(): void
    {
        $csv = <<<CSV
handle,name,basePath
test,Test,\$ASSETS_PATH
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - baseUrl Formats
    // =========================================================================

    public function testValidBaseUrlWithAlias(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/assets,true,@web/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testValidBaseUrlWithFullUrl(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/assets,true,https://cdn.example.com/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testValidBaseUrlWithEnvironmentVariable(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/assets,true,\$CDN_URL/assets
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Empty/Null Values
    // =========================================================================

    public function testEmptyOptionalFieldsAreValid(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Multiple Errors
    // =========================================================================

    public function testMultipleErrorsInSingleRow(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
test,Test,@webroot/test,notbool,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        // Should have boolean error
        $hasBoolError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'boolean')) {
                $hasBoolError = true;
                break;
            }
        }
        $this->assertTrue($hasBoolError);
    }

    public function testErrorsInMultipleRows(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls,baseUrl
valid1,Valid 1,@webroot/valid1,true,@web/valid1
invalid1,Invalid 1,@webroot/invalid1,notbool,
valid2,Valid 2,@webroot/valid2,false,
invalid2,Invalid 2,@webroot/invalid2,true,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(3, $result['rowErrors']); // Row 3 - invalid boolean
        $this->assertArrayHasKey(5, $result['rowErrors']); // Row 5 - missing baseUrl
        $this->assertArrayNotHasKey(2, $result['rowErrors']); // Row 2 - valid
        $this->assertArrayNotHasKey(4, $result['rowErrors']); // Row 4 - valid
    }

    public function testRowNumbersAreCorrectInErrors(): void
    {
        $csv = <<<CSV
handle,name,basePath,publicUrls
row2,Row 2,@webroot/r2,invalid
row3,Row 3,@webroot/r3,true
row4,Row 4,@webroot/r4,alsoinvalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('filesystems', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        // Row numbers should be 2 and 4 (1-indexed, header is row 1)
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertArrayHasKey(4, $result['rowErrors']);
        // Row 3 has valid boolean but might fail on baseUrl check
    }
}
