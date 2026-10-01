# Repository Guide

## Layout

- The Magento module is in `src/Company/Plugin`; its registered module name is `Company_Plugin` and its frontend route prefix is `/plugin`.
- The module's Composer manifest is `src/Company/Plugin/composer.json`. There is no root Composer project or Magento bootstrap in this repository.
- Standalone regression tests are in `tests/`. The GitHub Actions workflow is `.github/workflows/php.yml`.

## Working Rules

- Preserve existing Magento controller routes, XML payload behavior, and database/job semantics unless a task explicitly changes them.
- Keep changes scoped. Do not describe unverified XML schemas, PHP/Magento compatibility, or product capabilities as supported facts.
- The module manifest declares Magento 2.3 and PHP versions, but the automated workflow only checks PHP 8.1-8.3 and does not run a Magento integration suite. Treat broader compatibility as unverified.
- Do not treat the legacy AES helper as suitable for new encryption use. See `SECURITY.md` for its limitations.

## Checks Before Completion

Run the checks available without a Magento installation:

```sh
php tests/EncoderTest.php
find src tests -name '*.php' -print0 | xargs -0 -n 1 php -l
```

The test script covers the shared authentication encoder using local Magento stand-ins; it is not an end-to-end Magento test. Report any checks that could not be run and any failures without implying broader coverage.
