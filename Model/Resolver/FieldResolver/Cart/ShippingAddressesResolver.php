<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Cart;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Directory\Model\CountryFactory;

class ShippingAddressesResolver implements BatchResolverInterface
{
    private array $methodsCache = [];
    private array $countryCache = [];

    public function __construct(
        private readonly ShippingMethodManagementInterface $shippingMethodManagement,
        private readonly CountryFactory $countryFactory
    ) {}

    /**
     * Batch resolve shipping addresses
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array {
        /** @var \Magento\Quote\Api\Data\CartInterface[] $carts */
        $carts = $value['carts'] ?? [];
        $result = [];

        if (empty($carts)) {
            return $result;
        }

        // Get selected fields
        $fields = $info->getFieldSelection();

        foreach ($carts as $cart) {
            $cartId = $cart->getId();
            $shippingAddress = $cart->getShippingAddress();

            if (!$shippingAddress) {
                $result[$cartId] = [];
                continue;
            }

            $addressData = [
                'firstname' => $shippingAddress->getFirstname(),
                'lastname' => $shippingAddress->getLastname(),
                'company' => $shippingAddress->getCompany(),
                'street' => $shippingAddress->getStreet(),
                'city' => $shippingAddress->getCity(),
                'region' => [
                    'code' => $shippingAddress->getRegionCode(),
                    'label' => $shippingAddress->getRegion()
                ],
                'postcode' => $shippingAddress->getPostcode(),
                'country' => $this->getCountryData($shippingAddress->getCountryId()),
                'telephone' => $shippingAddress->getTelephone(),
                'available_shipping_methods' => $this->getShippingMethods($cartId, $fields),
                'selected_shipping_method' => $this->getSelectedShippingMethod($shippingAddress)
            ];

            $result[$cartId] = [$addressData]; // Array because cart can have multiple addresses in theory
        }

        return $result;
    }

    /**
     * Get country data
     *
     * @param string $countryId
     * @return array
     */
    private function getCountryData(string $countryId): array
    {
        if (!isset($this->countryCache[$countryId])) {
            $country = $this->countryFactory->create()->loadByCode($countryId);
            $this->countryCache[$countryId] = [
                'code' => $country->getId(),
                'label' => $country->getName()
            ];
        }

        return $this->countryCache[$countryId];
    }

    /**
     * Get available shipping methods
     *
     * @param int $cartId
     * @param array $fields
     * @return array
     */
    private function getShippingMethods(int $cartId, array $fields): array
    {
        if (!isset($fields['available_shipping_methods'])) {
            return [];
        }

        if (!isset($this->methodsCache[$cartId])) {
            try {
                $methods = $this->shippingMethodManagement->getList($cartId);
                $this->methodsCache[$cartId] = array_map(function ($method) {
                    return [
                        'carrier_code' => $method->getCarrierCode(),
                        'carrier_title' => $method->getCarrierTitle(),
                        'method_code' => $method->getMethodCode(),
                        'method_title' => $method->getMethodTitle(),
                        'amount' => [
                            'value' => $method->getAmount(),
                            'currency' => $method->getCurrencyCode()
                        ],
                        'price_excl_tax' => [
                            'value' => $method->getPriceExclTax(),
                            'currency' => $method->getCurrencyCode()
                        ],
                        'price_incl_tax' => [
                            'value' => $method->getPriceInclTax(),
                            'currency' => $method->getCurrencyCode()
                        ]
                    ];
                }, $methods);
            } catch (\Exception $e) {
                $this->methodsCache[$cartId] = [];
            }
        }

        return $this->methodsCache[$cartId];
    }

    /**
     * Get selected shipping method
     *
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return array|null
     */
    private function getSelectedShippingMethod($address): ?array
    {
        $method = $address->getShippingMethod();
        if (!$method) {
            return null;
        }

        list($carrierCode, $methodCode) = explode('_', $method, 2);

        return [
            'carrier_code' => $carrierCode,
            'method_code' => $methodCode,
            'carrier_title' => $address->getShippingDescription(),
            'method_title' => '',
            'amount' => [
                'value' => $address->getShippingAmount(),
                'currency' => $address->getQuote()->getQuoteCurrencyCode()
            ]
        ];
    }
}
