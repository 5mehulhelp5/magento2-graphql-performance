# Changelog

## [1.1.7] - 2024-03-13

### Added
- Improved product price handling in QueryCachePlugin
- Added default data structures for missing fields
- Added Turkish currency (TRY) support
- Added proper error handling for price-related queries
- Added optimized caching for price data
- Added default aggregations for product listings
- Added proper handling for missing price ranges
- Added support for discount percentage calculations
- Added stock status defaults

### Fixed
- Fixed null price handling in product queries
- Fixed missing currency information
- Fixed empty aggregations in product listings
- Fixed price range structure in responses
- Fixed discount calculations
- Fixed stock status handling

### Changed
- Optimized price data caching
- Improved error messages for missing data
- Enhanced product query response structure
- Updated default currency to TRY
- Improved handling of missing fields

## [1.1.6] - 2024-03-12

### Added
- Added specific handlers for Edip Saat queries
- Added support for Turkish characters in URLs
- Added parent category lookup
- Added manufacturer information retrieval
- Added detailed error logging

### Fixed
- Fixed category search with Turkish characters
- Fixed product detail handling
- Fixed empty response handling
- Fixed GraphQL error formatting
- Fixed cache lifetime type issues

### Changed
- Improved error responses
- Enhanced category page handling
- Optimized query caching
- Updated validation rules

## [1.1.5] - 2024-03-11

### Added
- Added introspection query handling
- Added schema generation support
- Added type resolver directives

### Fixed
- Fixed parameter order in plugins
- Fixed schema handling in CacheWarmer
- Fixed security validation
- Fixed cache plugin issues

### Changed
- Updated plugin execution order
- Improved error handling
- Enhanced security patterns
