<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Checkout;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Psr\Log\LoggerInterface;

class BillingAddressResolver implements BatchResolverInterface
{
    /**
     * @var array Cart objects cache
     */
    private array $cartCache = [];

    /**
     * @var array Country data cache
     */
    private array $countryCache = [];

    /**
     * @var array Region data cache
     */
    private array $regionCache = [];

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CountryFactory $countryFactory,
        private readonly RegionFactory $regionFactory,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Batch resolve billing addresses
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
 * @var \Magento\Quote\Api\Data\CartInterface[] $carts
*/
        $carts = $value['carts'] ?? [];
        $result = [];

        if (empty($carts)) {
            return $result;
        }

        // Load all carts in batch
        $this->loadCarts(
            array_map(
                function ($cart) {
                    return $cart->getId();
                },
                $carts
            )
        );

        foreach ($carts as $cart) {
            $cartId = $cart->getId();
            $loadedCart = $this->cartCache[$cartId] ?? null;

            if (!$loadedCart || !$loadedCart->getBillingAddress()) {
                $result[$cartId] = null;
                continue;
            }

            $result[$cartId] = $this->transformBillingAddress($loadedCart->getBillingAddress());
        }

        return $result;
    }

    /**
     * Load carts in batch
     *
     * @param  array $cartIds
     * @return void
     */
    private function loadCarts(array $cartIds): void
    {
        $uncachedIds = array_diff($cartIds, array_keys($this->cartCache));

        if (empty($uncachedIds)) {
            return;
        }

        try {
            $carts = $this->cartRepository->getList(
                $this->buildSearchCriteria(['entity_id' => ['in' => $uncachedIds]])
            )->getItems();

            foreach ($carts as $cart) {
                $this->cartCache[$cart->getId()] = $cart;
            }
        } catch (\Exception $e) {
            // Silently handle cart loading errors to avoid breaking the GraphQL response
            // Individual cart errors will be reflected in the result array
            $this->logger?->error(
                'Error loading carts: ' . $e->getMessage(),
                [
                'cart_ids' => $uncachedIds,
                'exception' => $e
                ]
            );
        }
    }

    /**
     * Transform billing address to GraphQL format
     *
     * @param  \Magento\Quote\Api\Data\AddressInterface $address
     * @return array
     */
    private function transformBillingAddress($address): array
    {
        $countryId = $address->getCountryId();
        $regionId = $address->getRegionId();

        return [
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'region' => $this->getRegionData($regionId, $countryId),
            'postcode' => $address->getPostcode(),
            'country' => $this->getCountryData($countryId),
            'telephone' => $address->getTelephone(),
            'vat_id' => $address->getVatId(),
            'save_in_address_book' => (bool)$address->getSaveInAddressBook(),
            'customer_notes' => $address->getCustomerNotes()
        ];
    }

    /**
     * Get country data
     *
     * @param  string $countryId
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
     * Get region data
     *
     * @param  int|null $regionId
     * @param  string   $countryId
     * @return array
     */
    private function getRegionData(?int $regionId, string $countryId): array
    {
        if ($regionId === null) {
            return [
                'region_id' => null,
                'region_code' => null,
                'region' => null
            ];
        }

        $cacheKey = "{$countryId}_{$regionId}";
        if (!isset($this->regionCache[$cacheKey])) {
            $region = $this->regionFactory->create()->load($regionId);
            $this->regionCache[$cacheKey] = [
                'region_id' => $region->getId(),
                'region_code' => $region->getCode(),
                'region' => $region->getName()
            ];
        }

        return $this->regionCache[$cacheKey];
    }

    /**
     * Build search criteria
     *
     * @param  array $filters
     * @return \Magento\Framework\Api\SearchCriteria
     */
    private function buildSearchCriteria(array $filters): \Magento\Framework\Api\SearchCriteria
    {
        $searchCriteriaBuilder = $this->objectManager->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);

        foreach ($filters as $field => $condition) {
            $searchCriteriaBuilder->addFilter($field, $condition['value'], $condition['condition_type']);
        }

        return $searchCriteriaBuilder->create();
    }
}
