<?php

/**
 * Craft Mock
 *
 * Provides a minimal mock of Craft's classes and services for testing.
 * This avoids the need to bootstrap the full Craft application.
 */

// Only define if Craft class doesn't exist (i.e., not in a real Craft environment)
if (!class_exists('Craft')) {
    /**
     * Mock Sites Service
     */
    class MockSitesService
    {
        /** @var array Simulated sites */
        private array $sites = [];

        /** @var array Simulated site groups */
        private array $groups = [];

        public function setSites(array $sites): void
        {
            $this->sites = $sites;
        }

        public function setGroups(array $groups): void
        {
            $this->groups = $groups;
        }

        public function getSiteByHandle(string $handle): ?object
        {
            foreach ($this->sites as $site) {
                if ($site->handle === $handle) {
                    return $site;
                }
            }
            return null;
        }

        public function getAllGroups(): array
        {
            return $this->groups;
        }
    }

    /**
     * Mock Entries Service
     */
    class MockEntriesService
    {
        /** @var array Simulated entry types */
        private array $entryTypes = [];

        public function setEntryTypes(array $entryTypes): void
        {
            $this->entryTypes = $entryTypes;
        }

        public function getAllEntryTypes(): array
        {
            return $this->entryTypes;
        }

        public function getEntryTypeByHandle(string $handle): ?object
        {
            foreach ($this->entryTypes as $entryType) {
                if ($entryType->handle === $handle) {
                    return $entryType;
                }
            }
            return null;
        }
    }

    /**
     * Mock Filesystem Service
     */
    class MockFsService
    {
        /** @var array Simulated filesystems */
        private array $filesystems = [];

        public function setFilesystems(array $filesystems): void
        {
            $this->filesystems = $filesystems;
        }

        public function getFilesystemByHandle(string $handle): ?object
        {
            foreach ($this->filesystems as $fs) {
                if ($fs->handle === $handle) {
                    return $fs;
                }
            }
            return null;
        }
    }

    /**
     * Mock Application
     */
    class MockApp
    {
        private MockSitesService $sitesService;
        private MockEntriesService $entriesService;
        private MockFsService $fsService;

        public function __construct()
        {
            $this->sitesService = new MockSitesService();
            $this->entriesService = new MockEntriesService();
            $this->fsService = new MockFsService();
        }

        public function getSites(): MockSitesService
        {
            return $this->sitesService;
        }

        public function getEntries(): MockEntriesService
        {
            return $this->entriesService;
        }

        public function getFs(): MockFsService
        {
            return $this->fsService;
        }
    }

    /**
     * Mock Craft Class
     */
    class Craft
    {
        public static ?MockApp $app = null;

        /**
         * Mock translation function - just returns the message with placeholders replaced.
         */
        public static function t(string $category, string $message, array $params = [], ?string $language = null): string
        {
            foreach ($params as $key => $value) {
                $message = str_replace('{' . $key . '}', (string)$value, $message);
            }
            return $message;
        }

        /**
         * Initialize the mock app with default empty data
         */
        public static function init(): void
        {
            self::$app = new MockApp();
        }

        /**
         * Helper to create a mock site object
         */
        public static function createMockSite(string $handle, string $name = ''): object
        {
            return (object)[
                'handle' => $handle,
                'name' => $name ?: ucfirst($handle),
            ];
        }

        /**
         * Helper to create a mock site group object
         */
        public static function createMockSiteGroup(int $id, string $name): object
        {
            return new class($id, $name) {
                public int $id;
                private string $name;

                public function __construct(int $id, string $name)
                {
                    $this->id = $id;
                    $this->name = $name;
                }

                public function getName(): string
                {
                    return $this->name;
                }
            };
        }

        /**
         * Helper to create a mock entry type object
         */
        public static function createMockEntryType(string $handle, string $name = ''): object
        {
            return (object)[
                'handle' => $handle,
                'name' => $name ?: ucfirst($handle),
            ];
        }

        /**
         * Helper to create a mock filesystem object
         */
        public static function createMockFilesystem(string $handle, string $name = ''): object
        {
            return (object)[
                'handle' => $handle,
                'name' => $name ?: ucfirst($handle),
            ];
        }
    }

    // Initialize the mock app
    Craft::init();
}
