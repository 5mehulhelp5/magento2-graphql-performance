<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;

require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
function __()
{
    $argc = func_get_args();

    $text = array_shift($argc);
    if (!empty($argc) && is_array($argc[0])) {
        $argc = $argc[0];
    }

    return new \Magento\Framework\Phrase($text, $argc);
}

$testsBaseDir = dirname(__DIR__);
$integrationTestsDir = realpath("{$testsBaseDir}/../../");

if (isset($_SERVER['TESTS_MAGENTO_ROOT'])) {
    $magentoRoot = $_SERVER['TESTS_MAGENTO_ROOT'];
} else {
    $magentoRoot = realpath("{$integrationTestsDir}/../../../");
}

if (!file_exists($magentoRoot . '/app/etc/di.xml')) {
    throw new \Exception("TESTS_MAGENTO_ROOT directory is not valid Magento root: {$magentoRoot}");
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
