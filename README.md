# Company Plugin for Magento

`Company_Plugin` is a Magento 2 module for exchanging catalog, inventory, customer, shipping, and order data with the Company integration service. The source is under `src/Company/Plugin`; it is not a standalone Magento application.

## What it does

The module exposes integration routes under `/plugin`:

### Magento to integration service

Export controllers provide product, order, inventory/price, category, attribute, customer, shipping-method, option, and CSV data for the external integration service to retrieve.

### Integration service to Magento

The service posts XML to the import controllers. Supported jobs include products, product images and descriptions, product deletion, inventory/price, shipping, refunds, order cancellation/void/payment capture/field updates, and customers. Import requests are stored as rows in Magento's `job` table. Requests can be processed inline or left queued; the authenticated `/plugin/import/cron` controller processes submitted jobs. This repository does not define a Magento `crontab.xml`, so schedule that route externally if automatic queue processing is required.

The route names and module values are implementation details, not a complete API contract. The repository does not include a formal XML schema or a full parameter reference. Confirm payload shape and business rules with the integration provider before connecting a production store.

Illustrative XML payloads are available in [`Sample/`](Sample/). Treat them as examples, not production-ready requests.

## Requirements and compatibility

- A Magento 2 installation with the dependencies declared in `src/Company/Plugin/composer.json`: `magento/framework`, `magento/module-ui`, and `magento/module-config`.
- The module manifest identifies the package as Magento 2.3 and version `7.10.7`. It lists several PHP versions, but those declarations are not a verified compatibility matrix. The automated checks in this repository cover only PHP 8.1, 8.2, and 8.3, and do not boot Magento. Verify the exact Magento/PHP combination in a staging environment before deployment.

## Install in an existing Magento project

From the Magento project root, copy the module into the standard `app/code` location. Replace `/path/to/this/repository` with the checkout path.

```sh
mkdir -p app/code/Company
cp -R /path/to/this/repository/src/Company/Plugin app/code/Company/Plugin
php bin/magento module:enable Company_Plugin
php bin/magento setup:upgrade
php bin/magento cache:flush
php bin/magento cache:clean
```

Run `php bin/magento setup:di:compile` as part of deployment when the store is in production mode. Back up the database before enabling the module; setup creates its job table.

## Authentication and transport

The data export/import actions use HTTP Basic authentication against Magento admin-user credentials. Send credentials in the `Authorization` header, never in a URL or query string. Use HTTPS end to end; Basic authentication is only base64-encoded and does not encrypt credentials. Use a unique integration account, rotate its password, and restrict access to the integration routes at the network or web-server layer where practical.

See [SECURITY.md](SECURITY.md) for reporting guidance and implementation caveats.

## Tests

The standalone authentication regression checks do not require Magento or Composer dependencies:

```sh
php tests/EncoderTest.php
find src tests -name '*.php' -print0 | xargs -0 -n 1 php -l
```

GitHub Actions runs these checks on PHP 8.1, 8.2, and 8.3. This is not a Magento integration test suite.

## License

The package manifest declares OSL-3.0 and AFL-3.0. See [LICENSE.txt](src/Company/Plugin/LICENSE.txt) and [LICENSE_AFL.txt](src/Company/Plugin/LICENSE_AFL.txt).
