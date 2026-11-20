<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace support {
    final class Request {}
}

namespace {
    final class TestResponse
    {
        public function __construct(public string $body, public string $contentType) {}
    }

    final class TestStorage
    {
        public function get(string $key): string
        {
            return 'cached-image-bytes';
        }

        public function set(string $key, mixed $value): void {}
    }

    function config(string $key, mixed $default = null): mixed
    {
        return $key === 'plugin.triangle.media.app.target_type' ? 'image/png' : $default;
    }

    function storage(): TestStorage
    {
        return new TestStorage();
    }

    function responseBlob(string $blob, string $type = 'text/plain'): TestResponse
    {
        return new TestResponse($blob, $type);
    }

    require dirname(__DIR__) . '/src/plugin/media/app/controller/Processing.php';

    $response = (new \plugin\media\app\controller\Processing())->index(new \support\Request(), 'image-id');
    if (!$response instanceof TestResponse || $response->contentType !== 'image/png') {
        throw new RuntimeException('Processed image responses must use the configured image content type.');
    }

    echo "Processing response content-type checks passed.\n";
}
