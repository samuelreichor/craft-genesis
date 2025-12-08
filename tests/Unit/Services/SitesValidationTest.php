<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\tests\helpers\CsvTestHelper;

/**
 * Sites CSV Validation Tests
 *
 * Tests the validation of CSV data for sites import.
 */
class SitesValidationTest extends BaseValidationTest
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
        $columns = ['handle', 'name', 'language'];

        $result = $this->service->validateColumns('sites', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testValidColumnsWithAllAllowed(): void
    {
        $columns = ['handle', 'name', 'language', 'baseUrl', 'primary', 'hasUrls', 'enabled', 'group'];

        $result = $this->service->validateColumns('sites', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testInvalidColumnsDetected(): void
    {
        $columns = ['handle', 'name', 'language', 'invalidColumn', 'anotherInvalid'];

        $result = $this->service->validateColumns('sites', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('invalidColumn', $result['invalidColumns']);
        $this->assertContains('anotherInvalid', $result['invalidColumns']);
    }

    public function testMissingRequiredColumnsDetected(): void
    {
        $columns = ['handle', 'name']; // missing 'language'

        $result = $this->service->validateColumns('sites', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('language', $result['missingRequired']);
    }

    public function testEmptyColumnsFailsValidation(): void
    {
        $columns = [];

        $result = $this->service->validateColumns('sites', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('handle', $result['missingRequired']);
        $this->assertContains('name', $result['missingRequired']);
        $this->assertContains('language', $result['missingRequired']);
    }

    // =========================================================================
    // Row Validation Tests - Valid Cases
    // =========================================================================

    public function testValidMinimalSiteRow(): void
    {
        $csv = <<<CSV
handle,name,language
default,Default Site,en
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidSiteRowWithAllFields(): void
    {
        $csv = <<<CSV
handle,name,language,baseUrl,primary,hasUrls,enabled
default,Default Site,en-US,https://example.com,true,true,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidMultipleSiteRows(): void
    {
        $csv = <<<CSV
handle,name,language,baseUrl,primary,hasUrls
default,Default Site,en,https://example.com,true,true
german,German Site,de,https://example.de,false,true
french,French Site,fr,https://example.fr,false,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    // =========================================================================
    // Row Validation Tests - Language Code
    // =========================================================================

    /**
     * @dataProvider validLanguageCodesProvider
     */
    public function testValidLanguageCodes(string $languageCode): void
    {
        $csv = <<<CSV
handle,name,language
test,Test Site,{$languageCode}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "Language code '{$languageCode}' should be valid");
    }

    public static function validLanguageCodesProvider(): array
    {
        return [
            'simple two letter' => ['en'],
            'simple two letter german' => ['de'],
            'simple two letter french' => ['fr'],
            'with region en-US' => ['en-US'],
            'with region de-DE' => ['de-DE'],
            'with region de-AT' => ['de-AT'],
            'with region fr-FR' => ['fr-FR'],
            'chinese simplified' => ['zh-Hans'],
        ];
    }

    /**
     * @dataProvider invalidLanguageCodesProvider
     */
    public function testInvalidLanguageCodes(string $languageCode): void
    {
        $csv = <<<CSV
handle,name,language
test,Test Site,{$languageCode}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid'], "Language code '{$languageCode}' should be invalid");
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('not a valid language code', $result['rowErrors'][2][0]);
    }

    public static function invalidLanguageCodesProvider(): array
    {
        return [
            'single letter' => ['e'],
            'too long' => ['english'],
            'with numbers' => ['en123'],
            'special chars' => ['en@US'],
            'empty region' => ['en-'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Boolean Fields
    // =========================================================================

    /**
     * @dataProvider validBooleanValuesProvider
     */
    public function testValidBooleanValues(string $value): void
    {
        $csv = <<<CSV
handle,name,language,primary,hasUrls,enabled,baseUrl
test,Test Site,en,{$value},{$value},{$value},https://example.com
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "Boolean value '{$value}' should be valid");
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
            'YES uppercase' => ['YES'],
            'NO uppercase' => ['NO'],
            '1' => ['1'],
            '0' => ['0'],
            'on' => ['on'],
            'off' => ['off'],
        ];
    }

    /**
     * @dataProvider invalidBooleanValuesProvider
     */
    public function testInvalidBooleanValues(string $value): void
    {
        $csv = <<<CSV
handle,name,language,primary
test,Test Site,en,{$value}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid'], "Boolean value '{$value}' should be invalid");
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('must be a boolean value', $result['rowErrors'][2][0]);
    }

    public static function invalidBooleanValuesProvider(): array
    {
        return [
            'random string' => ['maybe'],
            'number other than 0/1' => ['2'],
            'typo' => ['tru'],
            'german ja' => ['ja'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - hasUrls/baseUrl Dependency
    // =========================================================================

    public function testHasUrlsTrueRequiresBaseUrl(): void
    {
        $csv = <<<CSV
handle,name,language,hasUrls,baseUrl
test,Test Site,en,true,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('baseUrl', $result['rowErrors'][2][0]);
    }

    public function testHasUrlsTrueWithBaseUrlIsValid(): void
    {
        $csv = <<<CSV
handle,name,language,hasUrls,baseUrl
test,Test Site,en,true,https://example.com
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testHasUrlsFalseDoesNotRequireBaseUrl(): void
    {
        $csv = <<<CSV
handle,name,language,hasUrls
test,Test Site,en,false
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testBaseUrlWithoutHasUrlsIsValid(): void
    {
        // baseUrl can be set without hasUrls being explicitly true
        $csv = <<<CSV
handle,name,language,baseUrl
test,Test Site,en,https://example.com
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Multiple Errors
    // =========================================================================

    public function testMultipleErrorsInSingleRow(): void
    {
        $csv = <<<CSV
handle,name,language,primary,hasUrls,baseUrl
test,Test Site,invalid-lang-code-too-long,notabool,true,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        // Should have multiple errors
        $this->assertGreaterThan(1, count($result['rowErrors'][2]));
    }

    public function testErrorsInMultipleRows(): void
    {
        $csv = <<<CSV
handle,name,language,primary
valid,Valid Site,en,true
invalid1,Invalid Site 1,x,true
valid2,Valid Site 2,de,false
invalid2,Invalid Site 2,en,notabool
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        // Row 3 (index 1 + 2) and Row 5 (index 3 + 2) should have errors
        $this->assertArrayHasKey(3, $result['rowErrors']);
        $this->assertArrayHasKey(5, $result['rowErrors']);
        // Row 2 and 4 should be valid
        $this->assertArrayNotHasKey(2, $result['rowErrors']);
        $this->assertArrayNotHasKey(4, $result['rowErrors']);
    }

    // =========================================================================
    // Row Validation Tests - Empty/Null Values
    // =========================================================================

    public function testEmptyOptionalFieldsAreValid(): void
    {
        $csv = <<<CSV
handle,name,language,baseUrl,primary,hasUrls,enabled
test,Test Site,en,,,,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testRowNumbersAreCorrectInErrors(): void
    {
        $csv = <<<CSV
handle,name,language,primary
row2,Site 2,en,invalid
row3,Site 3,de,true
row4,Site 4,fr,alsobad
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sites', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        // Row numbers should be 2 and 4 (1-indexed, header is row 1)
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertArrayHasKey(4, $result['rowErrors']);
        $this->assertArrayNotHasKey(3, $result['rowErrors']);
    }
}
