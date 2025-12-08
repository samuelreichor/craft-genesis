<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\tests\helpers\CsvTestHelper;

/**
 * EntryTypes CSV Validation Tests
 *
 * Tests the validation of CSV data for entry types import.
 */
class EntryTypesValidationTest extends BaseValidationTest
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
        $columns = ['handle', 'name'];

        $result = $this->service->validateColumns('entryTypes', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testValidColumnsWithAllAllowed(): void
    {
        $columns = [
            'handle',
            'name',
            'description',
            'titleTranslationMethod',
            'titleTranslationKeyFormat',
            'showSlug',
            'slugTranslationMethod',
            'slugTranslationKeyFormat',
            'showStatusField',
        ];

        $result = $this->service->validateColumns('entryTypes', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testInvalidColumnsDetected(): void
    {
        $columns = ['handle', 'name', 'invalidColumn', 'type'];

        $result = $this->service->validateColumns('entryTypes', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('invalidColumn', $result['invalidColumns']);
        $this->assertContains('type', $result['invalidColumns']);
    }

    public function testMissingRequiredColumnsDetected(): void
    {
        $columns = ['handle']; // missing 'name'

        $result = $this->service->validateColumns('entryTypes', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('name', $result['missingRequired']);
    }

    // =========================================================================
    // Row Validation Tests - Valid Cases
    // =========================================================================

    public function testValidMinimalEntryTypeRow(): void
    {
        $csv = <<<CSV
handle,name
article,Article
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidEntryTypeRowWithAllFields(): void
    {
        $csv = <<<CSV
handle,name,description,titleTranslationMethod,titleTranslationKeyFormat,showSlug,slugTranslationMethod,slugTranslationKeyFormat,showStatusField
article,Article,A blog article,site,,true,site,,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    public function testValidMultipleEntryTypeRows(): void
    {
        $csv = <<<CSV
handle,name,description,showSlug,showStatusField
article,Article,Blog articles,true,true
page,Page,Static pages,true,true
product,Product,Shop products,false,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['rowErrors']);
    }

    // =========================================================================
    // Row Validation Tests - Boolean Fields
    // =========================================================================

    /**
     * @dataProvider validBooleanValuesProvider
     */
    public function testValidBooleanValuesForShowSlug(string $value): void
    {
        $csv = <<<CSV
handle,name,showSlug
test,Test,{$value}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "Boolean value '{$value}' should be valid for showSlug");
    }

    /**
     * @dataProvider validBooleanValuesProvider
     */
    public function testValidBooleanValuesForShowStatusField(string $value): void
    {
        $csv = <<<CSV
handle,name,showStatusField
test,Test,{$value}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "Boolean value '{$value}' should be valid for showStatusField");
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
        ];
    }

    /**
     * @dataProvider invalidBooleanValuesProvider
     */
    public function testInvalidBooleanValuesForShowSlug(string $value): void
    {
        $csv = <<<CSV
handle,name,showSlug
test,Test,{$value}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid'], "Boolean value '{$value}' should be invalid for showSlug");
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    public static function invalidBooleanValuesProvider(): array
    {
        return [
            'random string' => ['maybe'],
            'number other than 0/1' => ['2'],
            'typo' => ['tru'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Translation Methods
    // =========================================================================

    /**
     * @dataProvider validTranslationMethodsProvider
     */
    public function testValidTitleTranslationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,titleTranslationMethod
test,Test,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid'], "Translation method '{$method}' should be valid");
    }

    public static function validTranslationMethodsProvider(): array
    {
        // Note: 'custom' and 'Custom…' are excluded because they require a KeyFormat
        return [
            'none' => ['none'],
            'site' => ['site'],
            'siteGroup' => ['siteGroup'],
            'language' => ['language'],
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
handle,name,titleTranslationMethod
test,Test,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid'], "Translation method '{$method}' should be invalid");
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
    // Row Validation Tests - Custom Translation Method requires KeyFormat
    // =========================================================================

    public function testCustomTitleTranslationMethodRequiresKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,titleTranslationMethod,titleTranslationKeyFormat
test,Test,custom,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('titleTranslationKeyFormat', $result['rowErrors'][2][0]);
    }

    public function testCustomTitleTranslationMethodWithKeyFormatIsValid(): void
    {
        $csv = <<<CSV
handle,name,titleTranslationMethod,titleTranslationKeyFormat
test,Test,custom,{section.handle}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testCustomLabelTranslationMethodRequiresKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,titleTranslationMethod,titleTranslationKeyFormat
test,Test,Custom…,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    public function testNonCustomTranslationMethodDoesNotRequireKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,titleTranslationMethod,titleTranslationKeyFormat
test,Test,site,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Slug Translation (only when showSlug is true)
    // =========================================================================

    public function testSlugTranslationMethodValidatedWhenShowSlugTrue(): void
    {
        $csv = <<<CSV
handle,name,showSlug,slugTranslationMethod
test,Test,true,invalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
    }

    public function testSlugTranslationMethodNotValidatedWhenShowSlugFalse(): void
    {
        // When showSlug is false, slugTranslationMethod is not validated
        $csv = <<<CSV
handle,name,showSlug,slugTranslationMethod
test,Test,false,invalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        // Should be valid because slug fields are not validated when showSlug is false
        $this->assertTrue($result['valid']);
    }

    public function testCustomSlugTranslationMethodRequiresKeyFormatWhenShowSlugTrue(): void
    {
        $csv = <<<CSV
handle,name,showSlug,slugTranslationMethod,slugTranslationKeyFormat
test,Test,true,custom,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertStringContainsString('slugTranslationKeyFormat', $result['rowErrors'][2][0]);
    }

    public function testValidSlugTranslationWithCustomKeyFormat(): void
    {
        $csv = <<<CSV
handle,name,showSlug,slugTranslationMethod,slugTranslationKeyFormat
test,Test,true,custom,{entry.id}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Empty/Null Values
    // =========================================================================

    public function testEmptyOptionalFieldsAreValid(): void
    {
        $csv = <<<CSV
handle,name,description,titleTranslationMethod,showSlug,showStatusField
test,Test,,,,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    public function testEmptyTranslationMethodIsValid(): void
    {
        // Empty translation method should default to 'site' and not trigger validation error
        $csv = <<<CSV
handle,name,titleTranslationMethod
test,Test,
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - Multiple Errors
    // =========================================================================

    public function testMultipleErrorsInSingleRow(): void
    {
        $csv = <<<CSV
handle,name,showSlug,showStatusField,titleTranslationMethod
test,Test,notbool,alsonotbool,invalid
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);
        $this->assertGreaterThanOrEqual(2, count($result['rowErrors'][2]));
    }

    public function testErrorsInMultipleRows(): void
    {
        $csv = <<<CSV
handle,name,showSlug
valid1,Valid 1,true
invalid1,Invalid 1,notbool
valid2,Valid 2,false
invalid2,Invalid 2,maybe
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('entryTypes', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(3, $result['rowErrors']); // Row 3
        $this->assertArrayHasKey(5, $result['rowErrors']); // Row 5
        $this->assertArrayNotHasKey(2, $result['rowErrors']); // Row 2 valid
        $this->assertArrayNotHasKey(4, $result['rowErrors']); // Row 4 valid
    }
}
