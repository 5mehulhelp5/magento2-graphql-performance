# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.6] - 2024-03-23

### Fixed

- Fixed GraphQL plugin parameter order to match Magento's expectations
- Fixed schema handling in CacheWarmer to use proper GraphQL schema object
- Updated SecurityPlugin to handle operation name parameter correctly
- Updated QueryCachePlugin to match new parameter order
- Fixed dependency injection configuration for schema generation
- Fixed plugin execution order in di.xml to ensure correct parameter handling
- Added proper sort order for SecurityPlugin, QueryCachePlugin, and ConnectionManagerPlugin
- Fixed introspection query handling in all plugins
- Added special handling for __schema and __type queries
- Fixed RateLimiter to properly handle both Request objects and string identifiers
- Fixed SecurityPattern enum getDescription method
- Fixed ConfigPath enum getPath method to correctly use enum value
- Fixed Config class to use ConfigPath enum values correctly
- Removed introspection query blocking from SecurityPattern
- Added isAuthRequired method to Config class
- Fixed QueryComplexityValidatorPlugin parameter order and schema handling
- Improved error handling and type safety in security plugins

## [1.1.5] - 2024-03-15

### Added

- Added support for GraphQL query batching
- Added performance metrics collection
- Added cache warming functionality
- Added rate limiting configuration

### Changed

- Improved error messages for better debugging
- Updated documentation with new features
- Optimized cache key generation

### Fixed

- Fixed issue with cache invalidation
- Fixed memory leak in connection pooling
- Fixed race condition in rate limiting

## [1.1.4] - 2024-02-28

### Added

- Added support for GraphQL field-level caching
- Added query complexity validation
- Added security patterns for query validation

### Changed

- Improved cache tag management
- Updated security validation rules
- Enhanced performance monitoring

### Fixed

- Fixed issue with cache key generation
- Fixed memory usage in large queries
- Fixed connection pooling issues

## [1.1.3] - 2024-02-15

### Added

- Added GraphQL query caching
- Added connection pooling
- Added basic security validation

### Changed

- Improved error handling
- Updated logging format
- Enhanced configuration options

### Fixed

- Fixed various performance issues
- Fixed memory leaks
- Fixed security vulnerabilities

## [1.1.2] - 2024-02-01

### Added

- Initial release with basic functionality
- Added GraphQL performance optimization
- Added simple caching mechanism

### Changed

- Updated documentation
- Improved code organization
- Enhanced error handling

### Fixed

- Fixed minor bugs
- Fixed documentation errors
- Fixed configuration issues
