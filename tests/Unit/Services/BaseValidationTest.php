<?php

namespace samuelreichor\genesis\tests\Unit\Services;

use Craft;
use PHPUnit\Framework\TestCase;

/**
 * Base Validation Test
 *
 * Provides common setup for validation tests including Craft mock configuration.
 */
abstract class BaseValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset mock app for each test
        Craft::init();

        // Setup default mock data
        $this->setupMockData();
    }

    /**
     * Setup default mock data for tests.
     * Override in subclasses to customize.
     */
    protected function setupMockData(): void
    {
        // Default sites
        Craft::$app->getSites()->setSites([
            Craft::createMockSite('default', 'Default Site'),
            Craft::createMockSite('german', 'German Site'),
            Craft::createMockSite('french', 'French Site'),
        ]);

        // Default site groups
        Craft::$app->getSites()->setGroups([
            Craft::createMockSiteGroup(1, 'Default'),
            Craft::createMockSiteGroup(2, 'Europe'),
        ]);

        // Default entry types
        Craft::$app->getEntries()->setEntryTypes([
            Craft::createMockEntryType('article', 'Article'),
            Craft::createMockEntryType('page', 'Page'),
            Craft::createMockEntryType('product', 'Product'),
        ]);

        // Default filesystems
        Craft::$app->getFs()->setFilesystems([
            Craft::createMockFilesystem('assets', 'Assets'),
            Craft::createMockFilesystem('uploads', 'Uploads'),
        ]);
    }

    /**
     * Add a mock site
     */
    protected function addMockSite(string $handle, string $name = ''): void
    {
        $sites = [];
        // Get existing sites (we need to rebuild since we can't get them back)
        // This is a limitation of the simple mock
        $sites[] = Craft::createMockSite($handle, $name);
        Craft::$app->getSites()->setSites($sites);
    }

    /**
     * Add a mock entry type
     */
    protected function addMockEntryType(string $handle, string $name = ''): void
    {
        $entryTypes = [];
        $entryTypes[] = Craft::createMockEntryType($handle, $name);
        Craft::$app->getEntries()->setEntryTypes($entryTypes);
    }

    /**
     * Add a mock filesystem
     */
    protected function addMockFilesystem(string $handle, string $name = ''): void
    {
        $filesystems = [];
        $filesystems[] = Craft::createMockFilesystem($handle, $name);
        Craft::$app->getFs()->setFilesystems($filesystems);
    }

    /**
     * Clear all mock sites
     */
    protected function clearMockSites(): void
    {
        Craft::$app->getSites()->setSites([]);
    }

    /**
     * Clear all mock entry types
     */
    protected function clearMockEntryTypes(): void
    {
        Craft::$app->getEntries()->setEntryTypes([]);
    }

    /**
     * Clear all mock filesystems
     */
    protected function clearMockFilesystems(): void
    {
        Craft::$app->getFs()->setFilesystems([]);
    }
}
