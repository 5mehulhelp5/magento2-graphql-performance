# Security Guide

## Overview
This guide covers security best practices and configuration options for the GraphQL Performance module.

## Security Features

### 1. Query Validation
The module implements comprehensive query validation:

```php
use Sterk\GraphQlPerformance\Model\Security\RequestValidator;

class SecurityExample
{
    public function validateQuery(string $query): void
    {
        $this->requestValidator->validate($request, $query, $variables);
    }
}
```

#### Configuration
```xml
<graphql_performance>
    <security>
        <query_validation>
            <max_depth>10</max_depth>
            <max_complexity>300</max_complexity>
            <disable_introspection>1</disable_introspection>
            <allowed_operations>query,mutation</allowed_operations>
        </query_validation>
    </security>
</graphql_performance>
```

### 2. Rate Limiting
Built-in rate limiting protects against abuse:

```php
use Sterk\GraphQlPerformance\Model\Security\RateLimiter;

class RateLimitExample
{
    public function checkRateLimit(string $clientId): void
    {
        $this->rateLimiter->checkLimit($clientId);
    }
}
```

#### Configuration
```xml
<graphql_performance>
    <security>
        <rate_limiting>
            <enabled>1</enabled>
            <max_requests>1000</max_requests>
            <time_window>3600</time_window>
            <by_ip>1</by_ip>
            <by_token>1</by_token>
            <whitelist>127.0.0.1,192.168.1.*</whitelist>
        </rate_limiting>
    </security>
</graphql_performance>
```

### 3. Input Validation
Secure input validation for all GraphQL operations:

```php
use Sterk\GraphQlPerformance\Model\Security\InputValidator;

class InputValidationExample
{
    public function validateInput(array $input): void
    {
        $this->inputValidator->validate($input);
    }
}
```

#### Configuration
```xml
<graphql_performance>
    <security>
        <input_validation>
            <max_input_length>8000</max_input_length>
            <allowed_mime_types>image/jpeg,image/png</allowed_mime_types>
            <max_upload_size>10</max_upload_size>
        </input_validation>
    </security>
</graphql_performance>
```

### 4. Authentication & Authorization
Integration with Magento's authentication system:

```php
use Sterk\GraphQlPerformance\Model\Security\AuthValidator;

class AuthExample
{
    public function validateAuth(string $token): void
    {
        $this->authValidator->validate($token);
    }
}
```

#### Configuration
```xml
<graphql_performance>
    <security>
        <authentication>
            <token_lifetime>3600</token_lifetime>
            <require_https>1</require_https>
            <allowed_roles>customer,admin</allowed_roles>
        </authentication>
    </security>
</graphql_performance>
```

## Best Practices

### 1. Query Complexity Management
- Set appropriate complexity limits
- Monitor query patterns
- Implement query whitelisting

### 2. Cache Security
- Use secure cache keys
- Implement proper cache invalidation
- Handle sensitive data appropriately

### 3. Error Handling
- Avoid exposing internal errors
- Implement proper logging
- Handle edge cases gracefully

### 4. Data Protection
- Implement field-level security
- Handle sensitive data properly
- Use proper encryption

## Security Configurations

### 1. Basic Security Settings
```xml
<graphql_performance>
    <security>
        <enable_cors>0</enable_cors>
        <allowed_origins>*</allowed_origins>
        <enable_csrf_validation>1</enable_csrf_validation>
        <csrf_token_lifetime>3600</csrf_token_lifetime>
    </security>
</graphql_performance>
```

### 2. Advanced Security Settings
```xml
<graphql_performance>
    <security>
        <advanced>
            <enable_query_batching>0</enable_query_batching>
            <max_batch_size>10</max_batch_size>
            <enable_persisted_queries>1</enable_persisted_queries>
            <persisted_query_ttl>86400</persisted_query_ttl>
        </advanced>
    </security>
</graphql_performance>
```

### 3. Monitoring Settings
```xml
<graphql_performance>
    <security>
        <monitoring>
            <log_security_events>1</log_security_events>
            <alert_on_violations>1</alert_on_violations>
            <violation_threshold>10</violation_threshold>
            <alert_email>security@example.com</alert_email>
        </monitoring>
    </security>
</graphql_performance>
```

## Security Headers
The module automatically sets recommended security headers:

```php
use Sterk\GraphQlPerformance\Model\Security\HeaderManager;

class HeaderExample
{
    public function setSecurityHeaders(): void
    {
        $this->headerManager->setSecurityHeaders();
    }
}
```

### Default Headers
```php
[
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'"
]
```

## Audit Logging
Comprehensive security audit logging:

```php
use Sterk\GraphQlPerformance\Logger\SecurityLogger;

class AuditExample
{
    public function logSecurityEvent(string $event, array $context): void
    {
        $this->securityLogger->info($event, $context);
    }
}
```

### Log Format
```json
{
    "timestamp": "2024-03-20T10:00:00Z",
    "event": "rate_limit_exceeded",
    "client_ip": "192.168.1.1",
    "user_id": "customer_123",
    "request_id": "req_abc123",
    "details": {
        "limit": 1000,
        "current": 1001,
        "window": 3600
    }
}
```

## Incident Response
Built-in incident response capabilities:

```php
use Sterk\GraphQlPerformance\Model\Security\IncidentManager;

class IncidentExample
{
    public function handleIncident(string $type, array $details): void
    {
        $this->incidentManager->handle($type, $details);
    }
}
```

### Configuration
```xml
<graphql_performance>
    <security>
        <incident_response>
            <enable_auto_blocking>1</enable_auto_blocking>
            <block_duration>3600</block_duration>
            <notification_email>security@example.com</notification_email>
            <log_level>critical</log_level>
        </incident_response>
    </security>
</graphql_performance>
```

## Troubleshooting

### Common Issues

1. Rate Limiting Issues
```php
// Check rate limit configuration
if ($this->rateLimiter->isLimited($clientId)) {
    // Handle rate limit exceeded
}
```

2. Authentication Issues
```php
// Verify token validity
try {
    $this->authValidator->validate($token);
} catch (AuthenticationException $e) {
    // Handle authentication failure
}
```

3. Query Validation Issues
```php
// Check query complexity
try {
    $this->queryValidator->validate($query);
} catch (ComplexityException $e) {
    // Handle complexity validation failure
}
```
