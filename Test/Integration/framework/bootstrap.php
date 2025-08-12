<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;

require_once __DIR__ . '/../../../vendor/autoload.php';

// The __() function is already defined by Magento Framework

$testsBaseDir = dirname(__DIR__);
$integrationTestsDir = realpath("{$testsBaseDir}/../../");

// Check for Magento root in environment variable
if (!empty($_SERVER['TESTS_MAGENTO_ROOT'])) {
    $magentoRoot = $_SERVER['TESTS_MAGENTO_ROOT'];
} elseif (file_exists(dirname(dirname(dirname(__DIR__))) . '/app/etc/di.xml')) {
    // Check if we're in a Magento installation
    $magentoRoot = dirname(dirname(dirname(__DIR__)));
} else {
    throw new \Exception(
        'TESTS_MAGENTO_ROOT must be defined in phpunit.xml or environment variable. ' .
        'It should point to a valid Magento installation.'
    );
}

if (!defined('TESTS_TEMP_DIR')) {
    define('TESTS_TEMP_DIR', dirname(__DIR__) . '/tmp');
}

require_once $magentoRoot . '/app/bootstrap.php';

if (!defined('TESTS_MAGENTO_ROOT')) {
    define('TESTS_MAGENTO_ROOT', $magentoRoot);
}

if (!defined('INTEGRATION_TESTS_DIR')) {
    define('INTEGRATION_TESTS_DIR', $integrationTestsDir);
}

$settings = new \Magento\TestFramework\Bootstrap\Settings($testsBaseDir, get_defined_constants());
$testFramework = new \Magento\TestFramework\Bootstrap(
    Bootstrap::create(TESTS_MAGENTO_ROOT, $_SERVER),
    TESTS_MAGENTO_ROOT,
    $settings
);

$testFramework->initialize();
