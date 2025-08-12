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
        if (empty($value) || (is_string($value) && trim($value) === '')) {
            $this->setValue('[]');
            return parent::beforeSave();
        }

        // Convert to array if string
        if (is_string($value)) {
            $values = array_filter(explode(',', $value), function($v) {
                return trim($v) !== '';
            });
        } elseif (is_array($value)) {
            $values = array_filter($value, function($v) {
                return !empty($v) || is_numeric($v);
            });
        } else {
            throw new ValidatorException(__('Threshold values must be provided as a comma-separated list'));
        }

        // Process each value
        $cleanValues = [];
        foreach ($values as $threshold) {
            $threshold = trim($threshold);
            if ($threshold === '') {
                continue;
            }

            // Remove any non-numeric characters
            $threshold = preg_replace('/[^0-9.-]/', '', $threshold);

            if (!is_numeric($threshold)) {
                throw new ValidatorException(
                    __('Invalid threshold value "%1". Thresholds must be numbers.', $threshold)
                );
            }

            $floatValue = (float)$threshold;
            if ($floatValue < 0) {
                throw new ValidatorException(
                    __('Invalid threshold value "%1". Thresholds must be non-negative numbers.', $threshold)
                );
            }

            $cleanValues[] = $floatValue;
        }

        // If no valid values found, store as empty array
        if (empty($cleanValues)) {
            $this->setValue('[]');
            return parent::beforeSave();
        }

        // Store as JSON for consistent format
        $this->setValue(json_encode($cleanValues));

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
