# Changelog

Notable changes to this module are recorded here. The manifest currently reports version `7.10.7`; the historical entries below are the release notes present in the source package. No missing release history is inferred.

## [Unreleased]

### Security

- Authenticate integration requests from HTTP Basic `Authorization` headers instead of accepting credentials through URLs or trusting request origin.
- Remove hard-coded client metadata from module responses.

### Tests and CI

- Add standalone authentication regression checks and a GitHub Actions workflow for PHP 8.1-8.3 tests and syntax checks.

## [7.10.0] - 2023-05-01

### Added

- Process inventory whether or not MSI is enabled.

## [7.8.7] - 2021-06-23

### Fixed

- Remove deprecated `Api\StockItemRepositoryInterface` usage from `ProductService`.
- Add MSI-aware inventory output for product and inventory-price controllers.
- Add inventory import from MSI sources.

## [7.8.6] - 2021-05-25

### Fixed

- Fix a variable declaration error in the refund controller.

## [7.8.5] - 2021-05-17

### Added

- Support `-1` as the all-customer-groups ID (`32000`).

## [7.8.2] - 2021-02-01

### Added

- Support the `is_user_defined` query parameter to filter user-defined and system attributes.

## [7.8.1] - 2020-12-29

### Fixed

- Filter script tags from output rendered by Magento 2.

### Added

- Support importing product descriptions through XML.
