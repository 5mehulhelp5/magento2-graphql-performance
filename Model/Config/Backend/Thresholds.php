<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

/**
 * Backend model for performance threshold configuration values
 */
class Thresholds extends Value
{
    /**
     * Validate threshold values before saving
     *
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        // Handle empty values
        if (empty($value)) {
            $this->setValue('[]');
            return parent::beforeSave();
        }

        // If the value is a string (comma-separated list), convert it to array
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
            // Filter out empty values
            $value = array_filter($value, function($v) {
                return $v !== '';
            });
        }

        if (!is_array($value)) {
            throw new ValidatorException(__('Threshold values must be provided as a comma-separated list'));
        }

        // If array is empty after filtering, store as empty array
        if (empty($value)) {
            $this->setValue('[]');
            return parent::beforeSave();
        }

        foreach ($value as $threshold) {
            if (!is_numeric($threshold)) {
                throw new ValidatorException(
                    __('Invalid threshold value "%1". Thresholds must be numbers.', $threshold)
                );
            }

            if ((float)$threshold < 0) {
                throw new ValidatorException(
                    __('Invalid threshold value "%1". Thresholds must be non-negative numbers.', $threshold)
                );
            }
        }

        // Store as JSON for consistent format
        $this->setValue(json_encode($value));

        return parent::beforeSave();
    }

    /**
     * Process the value after loading
     *
     * @return void
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();

        if (empty($value)) {
            $this->setValue('');
            parent::_afterLoad();
            return;
        }

        try {
            $decodedValue = json_decode($value, true);
            if (is_array($decodedValue)) {
                $this->setValue(implode(',', $decodedValue));
            }
        } catch (\Exception $e) {
            // If JSON decode fails, keep the original value
            $this->setValue('');
        }

        parent::_afterLoad();
    }
}
