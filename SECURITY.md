# Security Policy

## Reporting a vulnerability

Please do not publish exploit details in a public issue. Use GitHub's private vulnerability reporting for this repository if it is enabled. If private reporting is unavailable, contact the repository owner through a private channel. No dedicated security email or response-time commitment is published in this repository.

Include the affected commit or package version, Magento and PHP versions, relevant configuration, impact, and reproducible steps. Remove credentials, customer information, and other production data from reports.

## Deployment guidance

- Serve the storefront and integration routes over HTTPS. HTTP Basic credentials are base64-encoded, not encrypted.
- The integration actions authenticate against Magento admin users. Use a unique integration credential, protect and rotate it, and restrict network access where practical. The module does not implement its own request rate limiting or a separate API-token/role scope.
- Never put credentials in URLs, query strings, logs, or support examples. Recent code authenticates using the `Authorization` header.
- The XML import controller opts out of Magento's normal CSRF rejection and relies on the shared authentication check. Treat `/plugin/import/import` as a sensitive write endpoint and limit who can reach it.
- Import payloads, processing logs, and stack traces are persisted in the Magento `job` table. Protect database access and avoid sending sensitive data that the integration does not need.

## Implementation caveats

The repository contains a legacy AES helper in `src/Company/Plugin/Lib/AES.php`. Its active methods use AES-128-CBC with a fixed all-zero IV, and CBC does not provide message authentication. The helper also truncates or zero-pads its key input. A repository search found no production call to its `encrypt` or `decrypt` methods; the encoder only receives the helper as an unused dependency. Do not rely on this helper for new or custom encryption use.

The automated tests exercise the authentication encoder with local stand-ins. They do not test Magento authorization configuration, web-server header forwarding, database handling, XML edge cases, or end-to-end transport security.
