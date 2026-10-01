# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.10.0] - 2023-05-01
### Added
- Added support for inventory processing irrespetive of whether MSI is enabled or not.

## [7.8.7] - 2021-06-23
### Fixed
- Removed decprecated code Api\StockItemRepositoryInterface from ProductService
- Added msi-wise inventory output for product and invprice controllers.
- Added feature to import inventory from msi sources.

## [7.8.6] - 2021-05-25
### Fixed
- Variable declaration error in refund controller.

## [7.8.5] - 2021-05-17
### Added
- Added support for sending -1 as all customer group id(32000).

## [7.8.2] - 2021-02-01
### Added
- Added support for query string is_user_defined to filter user defined and system attributes.

## [7.8.1] - 2020-12-29
### Fixed
- Added filter to avoid script tag in output rendered by magento 2.
- Added support for importing product descriptions via xml.
