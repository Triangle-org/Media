# Changelog

Notable changes are documented here. This project follows Semantic Versioning.

## 2.0.0 - 2026-11-22

### Changed

- Require Triangle Engine 9 and its Server 7 runtime.
- Expand CI to PHP 8.1 and 8.5 with lowest and latest dependency sets, Composer audit, lint, and executable tests.
- Add English and Russian installation, configuration, endpoint, and security guidance.
- Standardize PHP copyright and SPDX headers to AGPL-3.0-or-later.

### Fixed

- Enforce the project-registration setting and validate registration requests.
- Reject image identifiers that could escape the configured image directory.
- Enforce frame and pixel limits when processing previously stored images.
- Return transformed image bytes with the configured image MIME type.
