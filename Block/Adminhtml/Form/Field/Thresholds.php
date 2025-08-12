<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Thresholds field renderer
 */
class Thresholds extends AbstractFieldArray
{
    /**
     * @var array
     */
    protected $_columns = [];

    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn('threshold', [
            'label' => __('Threshold Value'),
            'class' => 'required-entry validate-number validate-greater-than-zero',
            'style' => 'width:200px'
        ]);

        $this->addColumn('description', [
            'label' => __('Description'),
            'style' => 'width:300px'
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Threshold');
    }

    /**
     * Prepare array row
     *
     * @param DataObject $row
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Render cell template
     *
     * @return string
     */
    protected function _getCellInputElementId($row, $index)
    {
        return $this->_getCellInputElementName($row, $index);
    }

    /**
     * Get the grid and scripts contents
     *
     * @param string $html
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        // Add validation rules
        $html .= '<script type="text/javascript">
            require(["jquery", "mage/validation"], function($) {
                $("#' . $this->getHtmlId() . '").on("change", ".threshold input", function() {
                    var value = $(this).val();
                    if (value && (!$.isNumeric(value) || value <= 0)) {
                        $(this).addClass("validation-failed");
                        $(this).after("<div class=\"validation-advice\">Please enter a valid positive number.</div>");
                    } else {
                        $(this).removeClass("validation-failed");
                        $(this).next(".validation-advice").remove();
                    }
                });
            });
        </script>';

        return $html;
    }
}
