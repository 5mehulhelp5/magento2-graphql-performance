<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\FieldResolver\Order;

use Magento\Framework\GraphQl\Query\Resolver\BatchResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;

/**
 * GraphQL resolver for order shipments
 *
 * This resolver handles batch loading of shipments with support for caching.
 * It transforms shipment data into the GraphQL format and handles related
 * entities like tracking information and shipment items.
 */
class ShipmentsResolver implements BatchResolverInterface
{
    /**
     * @var array<int, array<\Magento\Sales\Api\Data\ShipmentInterface>> Cache of shipments by order ID
     */
    private array $shipmentCache = [];

    /**
     * @var array<string, \Magento\Shipping\Model\Tracking\Result\Status> Cache of tracking statuses by tracking number
     */
    private array $trackingCache = [];

    /**
     * @param ShipmentRepositoryInterface $shipmentRepository Shipment repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder Search criteria builder
     * @param ResolverCache $cache Cache service
     * @param StatusFactory $trackingStatusFactory Tracking status factory
     */
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResolverCache $cache,
        private readonly StatusFactory $trackingStatusFactory
    ) {
    }

    /**
     * Batch resolve order shipments
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
 * @var array $orderIds
*/
        $orderIds = array_map(
            function ($item) {
                return $item['id'] ?? null;
            },
            $value
        );
        $orderIds = array_filter($orderIds);

        if (empty($orderIds)) {
            return [];
        }

        $storeId = $context->getExtensionAttributes()->getStore()->getId();

        // Load shipments in batch
        $this->loadShipments($orderIds, $storeId);

        $result = [];
        foreach ($value as $index => $item) {
            $orderId = $item['id'] ?? null;
            if (!$orderId || !isset($this->shipmentCache[$orderId])) {
                $result[$index] = [];
                continue;
            }

            $result[$index] = array_map(
                fn($shipment) => $this->transformShipmentData($shipment, $info->getFieldSelection()),
                $this->shipmentCache[$orderId]
            );
        }

        return $result;
    }

    /**
     * Load shipments in batch
     *
     * @param  array $orderIds
     * @param  int   $storeId
     * @return void
     */
    private function loadShipments(array $orderIds, int $storeId): void
    {
        $uncachedIds = array_diff($orderIds, array_keys($this->shipmentCache));
        if (empty($uncachedIds)) {
            return;
        }

        foreach ($uncachedIds as $orderId) {
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $cachedData = $this->cache->get($cacheKey);
            if ($cachedData !== null) {
                $this->shipmentCache[$orderId] = $cachedData;
                continue;
            }
        }

        // Load remaining uncached shipments
        $remainingIds = array_diff(
            $uncachedIds,
            array_keys($this->shipmentCache)
        );

        if (empty($remainingIds)) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $remainingIds, 'in')
            ->create();

        $shipments = $this->shipmentRepository->getList($searchCriteria)->getItems();

        // Group shipments by order ID
        $groupedShipments = [];
        foreach ($shipments as $shipment) {
            $orderId = $shipment->getOrderId();
            if (!isset($groupedShipments[$orderId])) {
                $groupedShipments[$orderId] = [];
            }
            $groupedShipments[$orderId][] = $shipment;

            // Cache tracking info
            $this->cacheTrackingInfo($shipment);
        }

        // Cache shipments by order
        foreach ($groupedShipments as $orderId => $orderShipments) {
            $this->shipmentCache[$orderId] = $orderShipments;
            $cacheKey = $this->generateCacheKey($orderId, $storeId);
            $this->cache->set(
                $cacheKey,
                $orderShipments,
                ['sales_shipment', 'order_' . $orderId],
                3600 // 1 hour cache
            );
        }
    }

    /**
     * Cache tracking information for shipment
     *
     * @param  \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @return void
     */
    private function cacheTrackingInfo($shipment): void
    {
        $tracks = $shipment->getTracks();
        if (empty($tracks)) {
            return;
        }

        foreach ($tracks as $track) {
            $trackingNumber = $track->getTrackNumber();
            if (!isset($this->trackingCache[$trackingNumber])) {
                try {
                    $trackingStatus = $this->trackingStatusFactory->create();
                    $trackingStatus->setCarrier($track->getCarrierCode())
                        ->setCarrierTitle($track->getTitle())
                        ->setTracking($trackingNumber)
                        ->setStatus($track->getStatus() ?? 'pending');

                    $this->trackingCache[$trackingNumber] = $trackingStatus;
                } catch (\Exception $e) {
                    // Skip tracking status for this carrier if it fails
                    // This prevents the entire shipment from failing due to one tracking issue
                    $this->trackingCache[$trackingNumber] = $this->trackingStatusFactory->create()
                        ->setCarrier($track->getCarrierCode())
                        ->setCarrierTitle($track->getTitle())
                        ->setTracking($trackingNumber)
                        ->setStatus('error');
                }
            }
        }
    }

    /**
     * Transform shipment data to GraphQL format
     *
     * @param  \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @param  array                                     $fields
     * @return array
     */
    private function transformShipmentData($shipment, array $fields): array
    {
        $result = [
            'id' => $shipment->getEntityId(),
            'number' => $shipment->getIncrementId(),
            'created_at' => $shipment->getCreatedAt(),
            'order_number' => $shipment->getOrder()->getIncrementId(),
            '__typename' => 'OrderShipment'
        ];

        // Add tracking if requested
        if (isset($fields['tracking'])) {
            $result['tracking'] = $this->getTrackingInfo($shipment);
        }

        // Add items if requested
        if (isset($fields['items'])) {
            $result['items'] = $this->getShipmentItems($shipment);
        }

        return $result;
    }

    /**
     * Get tracking information for shipment
     *
     * @param  \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @return array
     */
    private function getTrackingInfo($shipment): array
    {
        $tracking = [];
        foreach ($shipment->getTracks() as $track) {
            $trackingNumber = $track->getTrackNumber();
            $trackingStatus = $this->trackingCache[$trackingNumber] ?? null;

            $tracking[] = [
                'carrier' => $track->getCarrierCode(),
                'title' => $track->getTitle(),
                'number' => $trackingNumber,
                'status' => $trackingStatus ? $trackingStatus->getStatus() : 'pending',
                '__typename' => 'ShipmentTracking'
            ];
        }

        return $tracking;
    }

    /**
     * Get shipment items
     *
     * @param  \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @return array
     */
    private function getShipmentItems($shipment): array
    {
        $items = [];
        foreach ($shipment->getItems() as $item) {
            $items[] = [
                'id' => $item->getEntityId(),
                'order_item' => [
                    'id' => $item->getOrderItemId(),
                    'product_name' => $item->getName(),
                    'product_sku' => $item->getSku(),
                    '__typename' => 'OrderItemInterface'
                ],
                'quantity_shipped' => $item->getQty(),
                '__typename' => 'ShipmentItem'
            ];
        }

        return $items;
    }

    /**
     * Generate cache key
     *
     * @param  int $orderId
     * @param  int $storeId
     * @return string
     */
    private function generateCacheKey(int $orderId, int $storeId): string
    {
        return sprintf(
            'order_shipments_%d_store_%d',
            $orderId,
            $storeId
        );
    }
}
