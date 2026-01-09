<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use samuelreichor\genesis\services\CsvValidationService;
use samuelreichor\genesis\tests\helpers\CsvTestHelper;

/**
 * Sections CSV Validation Tests
 *
 * Tests the validation of CSV data for sections import.
 */
class SectionsValidationTest extends BaseValidationTest
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
        $columns = ['handle', 'name', 'type', 'entryTypes'];

        $result = $this->service->validateColumns('sections', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testValidColumnsWithAllAllowed(): void
    {
        $columns = [
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
        ];

        $result = $this->service->validateColumns('sections', $columns);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['invalidColumns']);
        $this->assertEmpty($result['missingRequired']);
    }

    public function testInvalidColumnsDetected(): void
    {
        $columns = ['handle', 'name', 'type', 'entryTypes', 'invalidColumn'];

        $result = $this->service->validateColumns('sections', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('invalidColumn', $result['invalidColumns']);
    }

    public function testMissingRequiredColumnsDetected(): void
    {
        $columns = ['handle', 'name']; // missing 'type' and 'entryTypes'

        $result = $this->service->validateColumns('sections', $columns);

        $this->assertFalse($result['valid']);
        $this->assertContains('type', $result['missingRequired']);
        $this->assertContains('entryTypes', $result['missingRequired']);
    }

    // =========================================================================
    // Row Validation Tests - Section Types
    // =========================================================================

    /**
     * @dataProvider validSectionTypesProvider
     */
    public function testValidSectionTypes(string $type): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes
test,Test,{$type},article
CSV;

        $parsed = CsvTestHelper::parse($csv);
        // Note: This will fail on entryTypeExists check without Craft::$app
        // We're testing the type validation specifically
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Check that type validation passed (no "not a valid section type" error)
        $hasTypeError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'not a valid section type')) {
                    $hasTypeError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTypeError, "Section type '{$type}' should be valid");
    }

    public static function validSectionTypesProvider(): array
    {
        return [
            'single' => ['single'],
            'channel' => ['channel'],
            'structure' => ['structure'],
            'Single uppercase' => ['Single'],
            'CHANNEL uppercase' => ['CHANNEL'],
        ];
    }

    /**
     * @dataProvider invalidSectionTypesProvider
     */
    public function testInvalidSectionTypes(string $type): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes
test,Test,{$type},article
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasTypeError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'not a valid section type')) {
                $hasTypeError = true;
                break;
            }
        }
        $this->assertTrue($hasTypeError, "Section type '{$type}' should be invalid");
    }

    public static function invalidSectionTypesProvider(): array
    {
        return [
            'random' => ['random'],
            'typo' => ['chanell'],
            'plural' => ['singles'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Propagation Methods
    // =========================================================================

    /**
     * @dataProvider validPropagationMethodsProvider
     */
    public function testValidPropagationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,propagationMethod
test,Test,channel,article,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Check that propagation method validation passed
        $hasPropagationError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'not a valid propagation method')) {
                    $hasPropagationError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasPropagationError, "Propagation method '{$method}' should be valid");
    }

    public static function validPropagationMethodsProvider(): array
    {
        return [
            'none' => ['none'],
            'siteGroup' => ['siteGroup'],
            'language' => ['language'],
            'all' => ['all'],
            'custom' => ['custom'],
            'label: save to created site' => ['Only save entries to the site they were created in'],
            'label: save to site group' => ['Save entries to other sites in the same site group'],
            'label: save to same language' => ['Save entries to other sites with the same language'],
            'label: save to all sites' => ['Save entries to all sites enabled for this section'],
            'label: let entry choose' => ['Let each entry choose which sites it should be saved to'],
        ];
    }

    /**
     * @dataProvider invalidPropagationMethodsProvider
     */
    public function testInvalidPropagationMethods(string $method): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,propagationMethod
test,Test,channel,article,{$method}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey(2, $result['rowErrors']);

        $hasPropagationError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'not a valid propagation method')) {
                $hasPropagationError = true;
                break;
            }
        }
        $this->assertTrue($hasPropagationError, "Propagation method '{$method}' should be invalid");
    }

    public static function invalidPropagationMethodsProvider(): array
    {
        return [
            'random' => ['random'],
            'typo' => ['alle'],
            'partial' => ['site'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Type-Conditional Fields (Structure)
    // =========================================================================

    public function testStructureAllowsMaxLevels(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,maxLevels
test,Test,structure,article,5
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have maxLevels error
        $hasMaxLevelsError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'maxLevels')) {
                    $hasMaxLevelsError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasMaxLevelsError);
    }

    public function testStructureAllowsDefaultPlacement(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,defaultPlacement
test,Test,structure,article,beginning
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have defaultPlacement error for structure
        $hasPlacementError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'defaultPlacement') && str_contains($error, 'only allowed for structure')) {
                    $hasPlacementError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasPlacementError);
    }

    public function testStructureDoesNotAllowSiteHome(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteHome
test,Test,structure,article,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasSiteHomeError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'siteHome') && str_contains($error, 'only allowed for single')) {
                $hasSiteHomeError = true;
                break;
            }
        }
        $this->assertTrue($hasSiteHomeError);
    }

    public function testStructureMaxLevelsMustBePositiveInteger(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,maxLevels
test,Test,structure,article,-1
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasMaxLevelsError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'maxLevels') && str_contains($error, 'positive integer')) {
                $hasMaxLevelsError = true;
                break;
            }
        }
        $this->assertTrue($hasMaxLevelsError);
    }

    /**
     * @dataProvider validDefaultPlacementsProvider
     */
    public function testValidDefaultPlacements(string $placement): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,defaultPlacement
test,Test,structure,article,{$placement}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $hasPlacementError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'not a valid default placement')) {
                    $hasPlacementError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasPlacementError, "Default placement '{$placement}' should be valid");
    }

    public static function validDefaultPlacementsProvider(): array
    {
        return [
            'beginning' => ['beginning'],
            'end' => ['end'],
            'Before other entries label' => ['Before other entries'],
            'After other entries label' => ['After other entries'],
        ];
    }

    /**
     * @dataProvider invalidDefaultPlacementsProvider
     */
    public function testInvalidDefaultPlacements(string $placement): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,defaultPlacement
test,Test,structure,article,{$placement}
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasPlacementError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'not a valid default placement')) {
                $hasPlacementError = true;
                break;
            }
        }
        $this->assertTrue($hasPlacementError, "Default placement '{$placement}' should be invalid");
    }

    public static function invalidDefaultPlacementsProvider(): array
    {
        return [
            'random' => ['random'],
            'middle' => ['middle'],
            'typo' => ['begining'],
        ];
    }

    // =========================================================================
    // Row Validation Tests - Type-Conditional Fields (Single)
    // =========================================================================

    public function testSingleAllowsSiteHome(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteHome
test,Test,single,article,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have siteHome "only allowed for single" error
        $hasSiteHomeError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'siteHome') && str_contains($error, 'only allowed for single')) {
                    $hasSiteHomeError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasSiteHomeError);
    }

    public function testSingleDoesNotAllowMaxLevels(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,maxLevels
test,Test,single,article,5
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasMaxLevelsError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'maxLevels') && str_contains($error, 'only allowed for structure')) {
                $hasMaxLevelsError = true;
                break;
            }
        }
        $this->assertTrue($hasMaxLevelsError);
    }

    public function testSingleDoesNotAllowDefaultPlacement(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,defaultPlacement
test,Test,single,article,beginning
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasPlacementError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'defaultPlacement') && str_contains($error, 'only allowed for structure')) {
                $hasPlacementError = true;
                break;
            }
        }
        $this->assertTrue($hasPlacementError);
    }

    // =========================================================================
    // Row Validation Tests - Type-Conditional Fields (Channel)
    // =========================================================================

    public function testChannelDoesNotAllowMaxLevels(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,maxLevels
test,Test,channel,article,5
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasMaxLevelsError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'maxLevels') && str_contains($error, 'only allowed for structure')) {
                $hasMaxLevelsError = true;
                break;
            }
        }
        $this->assertTrue($hasMaxLevelsError);
    }

    public function testChannelDoesNotAllowDefaultPlacement(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,defaultPlacement
test,Test,channel,article,end
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
    }

    public function testChannelDoesNotAllowSiteHome(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteHome
test,Test,channel,article,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
    }

    // =========================================================================
    // Row Validation Tests - siteUri/siteTemplate Dependency
    // =========================================================================

    public function testSiteUriRequiresSiteTemplate(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteUri,siteTemplate
test,Test,channel,article,blog/{slug},
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasUriTemplateError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'siteUri') && str_contains($error, 'siteTemplate')) {
                $hasUriTemplateError = true;
                break;
            }
        }
        $this->assertTrue($hasUriTemplateError);
    }

    public function testSiteTemplateRequiresSiteUriForNonSingle(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteUri,siteTemplate
test,Test,channel,article,,_entries/blog
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasTemplateUriError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'siteTemplate') && str_contains($error, 'siteUri')) {
                $hasTemplateUriError = true;
                break;
            }
        }
        $this->assertTrue($hasTemplateUriError);
    }

    public function testSingleCanHaveSiteTemplateWithoutSiteUri(): void
    {
        // Single sections can have template without URI (homepage case)
        $csv = <<<CSV
handle,name,type,entryTypes,siteUri,siteTemplate
test,Test,single,article,,_singles/home
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have the "siteTemplate requires siteUri" error
        $hasTemplateUriError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'siteTemplate') && str_contains($error, 'siteUri')) {
                    $hasTemplateUriError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTemplateUriError);
    }

    public function testSiteHomeAllowsEmptySiteUri(): void
    {
        // When siteHome is true, siteUri can be empty (homepage has no URI)
        $csv = <<<CSV
handle,name,type,entryTypes,siteUri,siteTemplate,siteHome
test,Test,single,article,,_singles/home,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have the "siteTemplate requires siteUri" error when siteHome is true
        $hasTemplateUriError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if (str_contains($error, 'siteTemplate') && str_contains($error, 'siteUri')) {
                    $hasTemplateUriError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasTemplateUriError);
    }

    public function testSiteHomeAllowsEmptySiteUriOnMultiSiteRows(): void
    {
        // Multi-site rows: type may be empty but siteHome=true should still allow empty siteUri
        $csv = <<<CSV
handle,name,type,entryTypes,site,siteUri,siteTemplate,siteHome
home,Home,single,article,en,,_pages/home,true
home,Home,,,de,,_pages/home,true
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Neither row should have the "siteTemplate requires siteUri" error
        $hasTemplateUriError = false;
        foreach ($result['rowErrors'] ?? [] as $rowErrors) {
            foreach ($rowErrors as $error) {
                if (str_contains($error, 'siteTemplate') && str_contains($error, 'siteUri')) {
                    $hasTemplateUriError = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($hasTemplateUriError);
    }

    public function testValidSiteUriAndSiteTemplate(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteUri,siteTemplate
test,Test,channel,article,blog/{slug},_entries/blog
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Should not have siteUri/siteTemplate dependency errors
        $hasUriTemplateError = false;
        if (!empty($result['rowErrors'][2])) {
            foreach ($result['rowErrors'][2] as $error) {
                if ((str_contains($error, 'siteUri') || str_contains($error, 'siteTemplate')) &&
                    (str_contains($error, 'must also be set') || str_contains($error, 'must be set'))) {
                    $hasUriTemplateError = true;
                    break;
                }
            }
        }
        $this->assertFalse($hasUriTemplateError);
    }

    // =========================================================================
    // Row Validation Tests - Boolean Fields
    // =========================================================================

    public function testSiteDefaultStatusMustBeBoolean(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteDefaultStatus
test,Test,channel,article,notbool
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasBoolError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'siteDefaultStatus') && str_contains($error, 'boolean')) {
                $hasBoolError = true;
                break;
            }
        }
        $this->assertTrue($hasBoolError);
    }

    public function testSiteHomeMustBeBooleanForSingle(): void
    {
        $csv = <<<CSV
handle,name,type,entryTypes,siteHome
test,Test,single,article,notbool
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        $this->assertFalse($result['valid']);
        $hasBoolError = false;
        foreach ($result['rowErrors'][2] as $error) {
            if (str_contains($error, 'siteHome') && str_contains($error, 'boolean')) {
                $hasBoolError = true;
                break;
            }
        }
        $this->assertTrue($hasBoolError);
    }

    // =========================================================================
    // Row Validation Tests - Multiple Rows
    // =========================================================================

    public function testMultipleSectionRowsWithSameHandle(): void
    {
        // This tests multiple rows for the same section with different site settings
        $csv = <<<CSV
handle,name,type,entryTypes,site,siteUri,siteTemplate
blog,Blog,channel,article,default,blog/{slug},_entries/blog
blog,Blog,channel,article,german,blog/{slug},_entries/blog
CSV;

        $parsed = CsvTestHelper::parse($csv);
        $result = $this->service->validateRows('sections', $parsed['columns'], $parsed['rows']);

        // Both rows should pass validation (site existence check will fail without Craft::$app)
        // but we're testing that multiple rows with same handle don't cause issues
        $hasHandleError = false;
        if (!empty($result['rowErrors'])) {
            foreach ($result['rowErrors'] as $errors) {
                foreach ($errors as $error) {
                    if (str_contains($error, 'handle')) {
                        $hasHandleError = true;
                        break 2;
                    }
                }
            }
        }
        $this->assertFalse($hasHandleError);
    }
}
