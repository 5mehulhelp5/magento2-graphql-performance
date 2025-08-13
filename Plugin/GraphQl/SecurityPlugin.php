<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Schema;
use Magento\GraphQl\Model\Query\Context;
use Sterk\GraphQlPerformance\Model\Security\RequestValidator;

/**
 * Plugin for adding security validations to GraphQL queries
 *
 * This plugin validates GraphQL requests before they are processed,
 * implementing rate limiting, query complexity checks, and other
 * security measures.
 */
class SecurityPlugin
{
    /**
     * @param RequestValidator $requestValidator Request validator service
     * @param RequestInterface $request Current request
     */
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Validate request before processing
     *
     * @param QueryProcessor $subject Query processor instance
     * @param Schema $schema GraphQL schema
     * @param string|null $source GraphQL query source
     * @param string|null $operationName Operation name
     * @param array|null $variables Query variables
     * @param Context|null $context Query context
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function beforeProcess(
        QueryProcessor $subject,
        Schema $schema,
        ?string $source = null,
        ?string $operationName = null,
        ?array $variables = null,
        ?Context $context = null,
        ?array $extensions = null
    ): array {
        $this->requestValidator->validate(
            $this->request,
            $source,
            $variables ?? []
        );

        return [$schema, $source, $operationName, $variables, $context, $extensions];
    }
}
