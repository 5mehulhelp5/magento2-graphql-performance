## What's New

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

## Installation
1. Update your composer.json:
```json
{
    "require": {
        "sterk/magento2-graphql-performance": "^1.1.7"
    }
}
```
2. Run:
```bash
composer update sterk/magento2-graphql-performance
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
php bin/magento cache:flush
```

## Breaking Changes
None. This is a backward-compatible improvement release.
