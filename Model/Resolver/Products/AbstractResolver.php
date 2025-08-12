<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Resolver\Products;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Sterk\GraphQlPerformance\Model\Cache\ResolverCache;
use Sterk\GraphQlPerformance\Model\Performance\QueryTimer;

abstract class AbstractResolver implements ResolverInterface
{
    public function __construct(
        protected readonly ResolverCache $cache,
        protected readonly QueryTimer $queryTimer
    ) {}

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $cacheKey = $this->generateCacheKey($field, $context, $info, $value ?? [], $args ?? []);
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData !== null) {
            return $cachedData;
        }

        $this->queryTimer->start($field->getName());
        $result = $this->resolveData($field, $context, $info, $value ?? [], $args ?? []);
        $this->queryTimer->stop($field->getName());

        $this->cache->set(
            $cacheKey,
            $result,
            $this->getCacheTags(),
            3600
        );

        return $result;
    }

    abstract protected function resolveData(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = [],
        array $args = []
    ): array;

    abstract protected function getEntityType(): string;

    abstract public function getCacheTags(): array;

    private function generateCacheKey(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value,
        array $args
    ): string {
        $keyParts = [
            $this->getEntityType(),
            $field->getName(),
            $info->operation->name->value ?? 'query',
            json_encode($args),
            json_encode($value)
        ];

        if (method_exists($context, 'getExtensionAttributes')) {
            $store = $context->getExtensionAttributes()->getStore();
            if ($store) {
                $keyParts[] = $store->getId();
            }
        }

        return implode(':', array_filter($keyParts));
    }
}
