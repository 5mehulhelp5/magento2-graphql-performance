<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class AttributeResolver implements BatchResolverInterface
{
    private array $attributeCache = [];

    public function __construct(
        private readonly AttributeRepositoryInterface $attributeRepository
    ) {
    }

    /**
     * Batch resolve product attributes
     *
     * @param  Field       $field
     * @param  mixed       $context
     * @param  ResolveInfo $info
     * @param  array       $value
     * @param  array       $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        /**
 * @var ProductInterface[] $products
*/
        $products = $value['products'] ?? [];
        $attributeCode = $field->getName();

        // Get attribute metadata
        $attribute = $this->getAttributeMetadata($attributeCode);
        if (!$attribute) {
            return [];
        }

        $result = [];
        foreach ($products as $product) {
            $value = $product->getData($attributeCode);
            $label = $attribute->getSource()->getOptionText($value);

            $result[$product->getId()] = [
                'value' => $value,
                'label' => $label ?: $value
            ];
        }

        return $result;
    }

    /**
     * Get attribute metadata with caching
     *
     * @param  string $attributeCode
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface|null
     */
    private function getAttributeMetadata(string $attributeCode)
    {
        if (!isset($this->attributeCache[$attributeCode])) {
            try {
                $this->attributeCache[$attributeCode] = $this->attributeRepository->get(
                    ProductInterface::ENTITY,
                    $attributeCode
                );
            } catch (\Exception $e) {
                $this->attributeCache[$attributeCode] = null;
            }
        }

        return $this->attributeCache[$attributeCode];
    }
}
