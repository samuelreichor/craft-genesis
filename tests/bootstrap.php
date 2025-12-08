<?php

/**
 * PHPUnit Bootstrap
 *
 * Sets up the test environment with mocked Craft classes.
 */

define('CRAFT_BASE_PATH', __DIR__ . '/..');
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

// Autoload
require_once CRAFT_VENDOR_PATH . '/autoload.php';

// Load test helpers
require_once __DIR__ . '/helpers/CraftMock.php';
require_once __DIR__ . '/helpers/CsvTestHelper.php';
