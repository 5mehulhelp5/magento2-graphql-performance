<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Performance;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use Sterk\GraphQlPerformance\Model\Config;

class CacheDirectiveManager
{
    /**
     * @var array List of field names that should never be cached
     */
    private array $nonCacheableFields = ['cart', 'customer', 'session'];

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Check if query has cache directives
     *
     * @param  Node $ast
     * @return bool
     */
    public function hasCacheDirectives(Node $ast): bool
    {
        $hasDirectives = false;
        Visitor::visit(
            $ast,
            [
            'enter' => function (Node $node) use (&$hasDirectives) {
                if ($node->kind === NodeKind::DIRECTIVE
                    && $node->name->value === 'cache'
                ) {
                    $hasDirectives = true;
                }
            }
            ]
        );
        return $hasDirectives;
    }

    /**
     * Add cache directives to query
     *
     * @param  Node $ast
     * @return Node
     */
    public function addCacheDirectives(Node $ast): Node
    {
        return Visitor::visit(
            $ast,
            [
            'leave' => function (Node $node) {
                if ($node->kind === NodeKind::FIELD) {
                    $fieldName = $node->name->value;
                    if ($this->isCacheableField($fieldName)) {
                        $node->directives[] = $this->createCacheDirective($fieldName);
                    }
                }
                return $node;
            }
            ]
        );
    }

    /**
     * Check if field is cacheable
     *
     * @param  string $fieldName
     * @return bool
     */
    private function isCacheableField(string $fieldName): bool
    {
        return !in_array($fieldName, $this->nonCacheableFields);
    }

    /**
     * Create cache directive
     *
     * @param  string $fieldName
     * @return Node
     */
    private function createCacheDirective(string $fieldName): Node
    {
        $ttl = $this->getFieldCacheTtl($fieldName);

        return new Node(
            [
            'kind' => NodeKind::DIRECTIVE,
            'name' => new Node(
                [
                'kind' => NodeKind::NAME,
                'value' => 'cache'
                ]
            ),
            'arguments' => [
                new Node(
                    [
                    'kind' => NodeKind::ARGUMENT,
                    'name' => new Node(
                        [
                        'kind' => NodeKind::NAME,
                        'value' => 'ttl'
                        ]
                    ),
                    'value' => new Node(
                        [
                        'kind' => NodeKind::INT,
                        'value' => (string)$ttl
                        ]
                    )
                    ]
                )
            ]
            ]
        );
    }

    /**
     * Get field cache TTL
     *
     * @param  string $fieldName
     * @return int
     */
    private function getFieldCacheTtl(string $fieldName): int
    {
        // Get field-specific TTL from configuration
        $ttl = $this->config->getFieldResolverConfig($fieldName, 'cache_lifetime');
        return $ttl ?: 3600; // Default to 1 hour
    }

    /**
     * Add non-cacheable field
     *
     * @param  string $fieldName
     * @return void
     */
    public function addNonCacheableField(string $fieldName): void
    {
        if (!in_array($fieldName, $this->nonCacheableFields)) {
            $this->nonCacheableFields[] = $fieldName;
        }
    }
}
