# Security policy

## Supported versions

The latest published release is `v1.1.0`, which targets Triangle Engine `^8.1`. This branch prepares `2.0.0` for Engine 9, but that version has not been released. Report vulnerabilities with the exact package version and Engine version; the release and support status of `2.0.0` will be stated when it is published.

## Report a vulnerability

Please report suspected vulnerabilities privately through GitHub's **Report a vulnerability** feature for this repository. Include the affected version, the relevant endpoint or configuration, and a minimal reproduction. Do not include real project tokens, uploaded private images, or production data.

## Deployment guidance

- Keep project registration disabled unless the application intentionally offers public registration.
- Protect upload and registration routes with application authentication or an appropriate reverse-proxy policy.
- Configure only trusted reverse proxies in the Engine request settings; forwarded client-IP headers are ignored for untrusted peers.
- Enable the plugin rate limiter and configure its backing cache before exposing public upload routes.
- Replace the example `image_uri`, use a private writable storage directory where possible, and restrict outbound network access from the server.
- Keep `ext-imagick` patched and apply conservative ImageMagick policy limits in addition to the per-request limits configured by the plugin.
- Treat project tokens as bearer credentials; do not place them in logs, public links, or analytics events.

The URL downloader blocks non-public destinations, pins a validated address, disables redirects and proxies, and enforces transfer limits. These controls do not replace network egress filtering or application-level authorization.
