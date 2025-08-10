<?php
declare(strict_types=1);

namespace Sterk\GraphQlPerformance\Model\Security;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Sterk\GraphQlPerformance\Model\Config;

class QueryValidator
{
    private const MAX_QUERY_LENGTH = 8000;
    private const RESTRICTED_FIELDS = [
        'password',
        'token',
        'secret',
        'key'
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Schema $schema
    ) {}

    /**
     * Validate GraphQL query
     *
     * @param string $query
     * @param array $variables
     * @throws GraphQlInputException
     */
    public function validate(string $query, array $variables = []): void
    {
        $this->validateQueryLength($query);
        $this->validateQuerySyntax($query);
        $this->validateComplexity($query);
        $this->validateDepth($query);
        $this->validateRestrictedFields($query);
        $this->validateVariables($variables);
    }

    /**
     * Validate query length
     *
     * @param string $query
     * @throws GraphQlInputException
     */
    private function validateQueryLength(string $query): void
    {
        if (strlen($query) > self::MAX_QUERY_LENGTH) {
            throw new GraphQlInputException(
                __('Query exceeds maximum length of %1 characters', self::MAX_QUERY_LENGTH)
            );
        }
    }

    /**
     * Validate query syntax
     *
     * @param string $query
     * @throws GraphQlInputException
     */
    private function validateQuerySyntax(string $query): void
    {
        try {
            DocumentValidator::validate($this->schema, \GraphQL\Language\Parser::parse($query));
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Invalid query syntax: %1', $e->getMessage()));
        }
    }

    /**
     * Validate query complexity
     *
     * @param string $query
     * @throws GraphQlInputException
     */
    private function validateComplexity(string $query): void
    {
        $maxComplexity = $this->config->getMaxQueryComplexity();
        $complexity = new QueryComplexity($maxComplexity);
        $complexity->setRawVariableValues([]);

        $errors = DocumentValidator::validate(
            $this->schema,
            \GraphQL\Language\Parser::parse($query),
            [$complexity]
        );

        if (!empty($errors)) {
            throw new GraphQlInputException(
                __('Query complexity of %1 exceeds maximum allowed complexity of %2', 
                    $complexity->getComplexity(),
                    $maxComplexity
                )
            );
        }
    }

    /**
     * Validate query depth
     *
     * @param string $query
     * @throws GraphQlInputException
     */
    private function validateDepth(string $query): void
    {
        $maxDepth = $this->config->getMaxQueryDepth();
        $queryDepth = new QueryDepth($maxDepth);

        $errors = DocumentValidator::validate(
            $this->schema,
            \GraphQL\Language\Parser::parse($query),
            [$queryDepth]
        );

        if (!empty($errors)) {
            throw new GraphQlInputException(
                __('Query depth exceeds maximum allowed depth of %1', $maxDepth)
            );
        }
    }

    /**
     * Validate for restricted fields
     *
     * @param string $query
     * @throws GraphQlInputException
     */
    private function validateRestrictedFields(string $query): void
    {
        $ast = \GraphQL\Language\Parser::parse($query);
        
        $restrictedFieldFound = false;
        $foundField = '';

        Visitor::visit($ast, [
            'enter' => function (Node $node) use (&$restrictedFieldFound, &$foundField) {
                if ($node instanceof Node && $node->kind === NodeKind::FIELD) {
                    foreach (self::RESTRICTED_FIELDS as $restrictedField) {
                        if (stripos($node->name->value, $restrictedField) !== false) {
                            $restrictedFieldFound = true;
                            $foundField = $node->name->value;
                            break;
                        }
                    }
                }
                return null;
            }
        ]);

        if ($restrictedFieldFound) {
            throw new GraphQlInputException(
                __('Access to field "%1" is restricted', $foundField)
            );
        }
    }

    /**
     * Validate variables
     *
     * @param array $variables
     * @throws GraphQlInputException
     */
    private function validateVariables(array $variables): void
    {
        foreach ($variables as $name => $value) {
            // Validate variable names
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                throw new GraphQlInputException(
                    __('Invalid variable name: %1', $name)
                );
            }

            // Validate variable values
            $this->validateVariableValue($name, $value);
        }
    }

    /**
     * Validate variable value
     *
     * @param string $name
     * @param mixed $value
     * @throws GraphQlInputException
     */
    private function validateVariableValue(string $name, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->validateVariableValue($name . '.' . $key, $item);
            }
            return;
        }

        if (is_string($value)) {
            // Check for potential SQL injection
            if (preg_match('/(union|select|insert|update|delete|drop|alter|create|rename)\s/i', $value)) {
                throw new GraphQlInputException(
                    __('Invalid value for variable: %1', $name)
                );
            }

            // Check for potential XSS
            if (preg_match('/<script|javascript:|data:|vbscript:|onload=|onerror=/i', $value)) {
                throw new GraphQlInputException(
                    __('Invalid value for variable: %1', $name)
                );
            }
        }
    }
}

