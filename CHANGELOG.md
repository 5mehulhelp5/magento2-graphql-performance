# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2024-03-21
### Fixed
- Fixed empty catch blocks with proper error logging
- Improved array performance by removing array_merge in loops
- Fixed lines exceeding maximum length
- Added missing PHP DocBlocks for better code documentation
- Fixed closing brace formatting issues
- Replaced deprecated getReadConnection/getWriteConnection methods

## [1.1.0] - 2024-03-21
### Added
- Full PHP 8.1 compatibility
- Constructor property promotion for cleaner code
- Intersection types for optimized fields
- Readonly properties for better immutability
- Never return type for improved error handling
- Enums for configuration paths, cache strategies, and security patterns
- Final classes for better inheritance control
- Enhanced static analysis configuration
- Improved type safety throughout the codebase

### Changed
- Updated all dev dependencies to latest versions
- Improved error handling in connection pools
- Enhanced type declarations across the module
- Optimized cache key generation
- Strengthened security validations

### Developer Experience
- Better IDE support through enhanced type hints
- Clearer code organization with PHP 8.1 features
- Improved static analysis coverage
- More maintainable and self-documenting code

## [1.0.0] - 2024-03-20
### Added
- Initial release
- DataLoader implementation for batch loading
- Cache infrastructure with tag management
- Performance monitoring and metrics collection
- Database connection pooling
- Query complexity analysis
- Field-level resolvers for all major entities
- Cache warming functionality
- Comprehensive documentation

### Optimized Entities
- Products
- Categories
- Customers
- CMS Pages/Blocks
- Orders
- Invoices
- Credit Memos
- Brand Categories
- Cart
- Checkout

### Performance Features
- Query-level caching
- Field-level result caching
- Batch loading optimization
- N+1 query prevention
- Cache tag management
- Automatic cache warming
- Performance metrics collection
- Resource usage monitoring

### Documentation
- Installation guide
- Configuration options
- Usage examples
- Best practices
- Troubleshooting guide
- API documentation
