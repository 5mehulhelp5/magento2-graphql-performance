<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchResponse;
use Magento\Framework\GraphQl\Config\Element\Field;

/**
 * Batch resolver for product attributes
 *
 * This class provides efficient batch resolution of product attributes,
 * handling attribute metadata caching and value-to-label conversion.
 * It implements the batch resolver interface to optimize performance
 * when resolving attributes for multiple products.
 */
class AttributeResolver implements BatchResolverInterface
{
    /**
     * @var array<string, ?\Magento\Catalog\Api\Data\ProductAttributeInterface> Cache of attribute metadata
     */
    private array $attributeCache = [];

    /**
     * @param AttributeRepositoryInterface $attributeRepository Attribute repository service
     */
    public function __construct(
        private readonly AttributeRepositoryInterface $attributeRepository
    ) {
    }

    /**
     * Batch resolve product attributes
     *
     * @param ContextInterface $context
     * @param Field $field
     * @param array $requests
     * @return BatchResponse
     */
    public function resolve(
        ContextInterface $context,
        Field $field,
        array $requests
    ): BatchResponse {
        $response = new BatchResponse();
        $attributeCode = $field->getName();

        // Get attribute metadata
        $attribute = $this->getAttributeMetadata($attributeCode);
        if (!$attribute) {
            foreach ($requests as $request) {
                $response->addResponse($request, []);
            }
            return $response;
        }

        foreach ($requests as $request) {
            $value = $request['value'] ?? [];
            $products = $value['products'] ?? [];

            $result = [];
            foreach ($products as $product) {
                $value = $product->getData($attributeCode);
                $label = $attribute->getSource()->getOptionText($value);

                $result[$product->getId()] = [
                    'value' => $value,
                    'label' => $label ?: $value
                ];
            }

            $response->addResponse($request, $result);
        }

        return $response;
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
