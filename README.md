# Triangle Media

Triangle Media is an image plugin for applications built with Triangle Engine. It accepts image uploads and remote image URLs, validates and converts accepted images to PNG, stores image metadata through the application's configured database connection, and can serve resized or transformed image responses.

## Compatibility

- PHP 8.1 or newer
- Triangle Engine 9
- PHP extensions: `curl`, `fileinfo`, and `imagick`
- A writable image directory and a database connection configured by the host application

Version `2.0.0` targets Triangle Engine 9 and Server 7 and is not compatible with Engine 8. Applications that remain on Engine 8 must use the legacy `1.1.0` release.

## Installation

For Triangle Engine 9:

```sh
composer require triangle/media:^2.0
```

For applications that remain on Engine 8:

```sh
composer require triangle/media:^1.1
```

Triangle Engine discovers the plugin controllers through Composer autoloading. The plugin's `Triangle\Media\Install::install()` method copies missing default settings from `src/Config` into `config/plugin/triangle/media`; it preserves existing application settings. The host application's plugin lifecycle should call it when installing the package.

The plugin does not include view templates. Install `triangle/view` to use the bundled view routes, then provide `index`, `form`, and `image` templates in the host application. The host may instead replace those controllers with its own presentation layer.

## Default controller paths

With Triangle Engine's default `app.plugin_uri` setting, the controllers are available at these paths:

| Path | Method | Behavior |
| --- | --- | --- |
| `/app/media` | `GET` | Renders the `index` view and sets a `token` cookie when the request includes a token. |
| `/app/media/project` | `GET`, `POST` | Returns `403` while project registration is disabled. When enabled, `GET` renders `form` and `POST` validates the fields, stores the project, and returns its token. |
| `/app/media/upload` | `POST` | Accepts an uploaded image or a remote image URL after checking the project token. |
| `/app/media/image/{image-id}` | `GET` | Renders the `image` view for an existing image. |
| `/app/media/processing/{image-id}/{filters...}` | `GET` | Applies the configured image filters and returns the generated image bytes. |

The prefix changes if the host application overrides `app.plugin_uri`. Configure the router, access control, and rate limits at the application or reverse-proxy boundary as appropriate for the deployment.

## Configuration

The defaults are installed at `config/plugin/triangle/media/app.php`:

| Setting | Default | Purpose |
| --- | --- | --- |
| `enable` | `true` | Enables plugin configuration. |
| `project_registration` | `false` | Allows project registration through the project controller when enabled. |
| `image_uri` | `https://example.ru/media/` | Public base URI returned for uploaded images; replace it with the deployed media URL. |
| `image_path` | `public_path() . '/media/upload/'` | Writable storage directory for converted images. |
| `max_file_size` | 15 MiB | Maximum accepted source-file size. |
| `max_image_pixels` | 40,000,000 | Maximum total decoded pixels across frames. |
| `max_image_frames` | 50 | Maximum number of frames in an input image. |
| `max_image_memory` | 256 MiB | ImageMagick memory and map resource limit. |
| `max_image_disk` | 512 MiB | ImageMagick disk resource limit. |
| `max_image_time` | 10 seconds | ImageMagick processing time limit. |
| `rate_limiter.enable` | `false` | Enables the configured request rate limiter. |
| `rate_limiter.quota` / `cycle` | `3` / `60` | Allowed requests per cycle when rate limiting is enabled. |

The accepted source MIME types, target PNG format, database collection names, and transformation patterns are also configurable in this file. Treat configuration values as application policy: verify the public URI, storage permissions, and resource limits before exposing upload routes.

Installation preserves an existing application configuration. After upgrading an older installation, check `project_registration` explicitly and set it to `false` if anonymous project creation is not intended.

The host application must provide compatible `db()` and `storage()` adapters. The database adapter must support `insert($table, $record)` and `where($field, $value)->getOne($table)`. The storage adapter must support `get($key)`, `set($key, $value, $ttl)` and `increment($key)`. Triangle Engine supplies `config()`, request IP resolution, and response helpers. `triangle/view` supplies `view()`; the host application supplies the `index`, `form`, and `image` templates. The package does not bundle database or storage adapters.

## Upload behavior

The upload controller accepts either a multipart file under the `file` field or a remote URL in the `link` field. Both paths validate the source MIME type and size. The uploader converts successful images to PNG, stores the image record through the Engine database helper, and returns the image identifier, URL, MIME type, dimensions, dominant color, and output size.

Remote image downloads use HTTP or HTTPS on standard ports, reject credentials and non-public IP addresses, pin DNS resolution to a validated address, do not follow redirects, and enforce a download byte limit and timeout. These controls reduce server-side request forgery risk; they do not replace outbound network policy at the host level.

The processing controller supports the configured `crop`, `resize`, `pixelize`, and `cover` filters. Results are cached through the host application's storage service.

## Development checks

```sh
composer validate --strict --no-check-publish --no-interaction
composer run check --no-interaction
composer audit --locked --no-interaction
```

The URL security test rejects local, reserved, and non-standard URL targets. Image decoding and conversion require the `imagick` extension; the repository's local test does not replace an end-to-end upload test against the host application's database, router, storage, and view configuration.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).

## Documentation

The current project handbook is available at [server.localzet.com/projects/media](https://server.localzet.com/projects/media), with [English guides](https://server.localzet.com/projects/media/en).
