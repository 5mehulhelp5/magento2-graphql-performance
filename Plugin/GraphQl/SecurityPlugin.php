<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Plugin\GraphQl;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Query\QueryProcessor;
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
     * @param string $source GraphQL query source
     * @param string|null $operationName Operation name
     * @param array|null $variables Query variables
     * @param array|null $extensions GraphQL extensions
     * @return array
     */
    public function beforeProcess(
        QueryProcessor $subject,
        string $source,
        ?string $operationName = null,
        ?array $variables = null,
        ?array $extensions = null
    ): array {
        $this->requestValidator->validate(
            $this->request,
            $source,
            $variables ?? []
        );

        return [$source, $operationName, $variables, $extensions];
    }
}
