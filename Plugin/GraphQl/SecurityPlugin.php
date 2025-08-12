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
     * @param  QueryProcessor $subject
     * @param  string         $query
     * @param  array|null     $variables
     * @return array
     */
    public function beforeProcess(
        QueryProcessor $subject,
        string $query,
        ?array $variables = null
    ): array {
        $this->requestValidator->validate(
            $this->request,
            $query,
            $variables ?? []
        );

        return [$query, $variables];
    }
}
