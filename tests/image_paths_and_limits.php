<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace {
    function config(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'plugin.triangle.media.app.target_ext' => 'png',
            'plugin.triangle.media.app.image_path' => $GLOBALS['media_test_directory'],
            'plugin.triangle.media.app.max_image_pixels' => 40000000,
            'plugin.triangle.media.app.max_image_frames' => 50,
            default => $default,
        };
    }

    function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    require dirname(__DIR__) . '/src/Util.php';
    require dirname(__DIR__) . '/src/ImageProcessing.php';

    $GLOBALS['media_test_directory'] = sys_get_temp_dir() . '/triangle-media-path-' . bin2hex(random_bytes(6));
    $outsidePath = $GLOBALS['media_test_directory'] . '-outside.png';
    mkdir($GLOBALS['media_test_directory'], 0700);
    file_put_contents($outsidePath, 'outside');

    expect(\Triangle\Media\Util::imageNameToId('image-id.png') === 'image-id', 'PNG filename should resolve to its identifier.');
    expect(\Triangle\Media\Util::imageNameToId('../image-id.png') === '../image-id.png', 'A path must not be reduced to an identifier.');

    foreach (['../outside', '..\\..\\outside', 'nested/image', 'nested\\image'] as $invalidId) {
        try {
            \Triangle\Media\Util::getPathToImageSource($invalidId);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException("Expected unsafe image identifier to be rejected: $invalidId");
    }

    if (function_exists('symlink') && @symlink($outsidePath, $GLOBALS['media_test_directory'] . '/linked.png')) {
        $symlinkRejected = false;
        try {
            \Triangle\Media\Util::getPathToImageSource('linked');
        } catch (RuntimeException) {
            $symlinkRejected = true;
        }

        @unlink($GLOBALS['media_test_directory'] . '/linked.png');
        expect($symlinkRejected, 'Image storage must reject symlinks that leave the configured directory.');
    }

    @unlink($outsidePath);
    @rmdir($GLOBALS['media_test_directory']);

    \Triangle\Media\ImageProcessing::assertDimensionsWithinLimit(2000, 2000);
    \Triangle\Media\ImageProcessing::assertFrameCountWithinLimit(50);

    try {
        \Triangle\Media\ImageProcessing::assertDimensionsWithinLimit(7000, 7000);
    } catch (\Exception) {
        try {
            \Triangle\Media\ImageProcessing::assertFrameCountWithinLimit(51);
        } catch (\Exception) {
            echo "Image path and processing limit checks passed.\n";
            exit(0);
        }

        throw new RuntimeException('Image processing must reject frame counts above the configured limit.');
    }

    throw new RuntimeException('Image processing must reject dimensions above the configured pixel limit.');
}
