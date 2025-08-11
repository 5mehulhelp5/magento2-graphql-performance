<?php
$vendorDir = __DIR__ . '/../../../vendor';
$autoloadFile = $vendorDir . '/autoload.php';

if (!file_exists($autoloadFile)) {
    throw new RuntimeException(
        'Vendor directory not found. Please run "composer install" first.'
    );
}

require_once $autoloadFile;

/**
 * Define the __ function if it doesn't exist
 *
 * @SuppressWarnings(PHPMD.ShortMethodName)
 */
if (!function_exists('__')) {
    function __()
    {
        $argc = func_get_args();

        $text = array_shift($argc);
        if (!empty($argc) && is_array($argc[0])) {
            $argc = $argc[0];
        }

        return new \Magento\Framework\Phrase($text, $argc);
    }
}
