<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace {
    $GLOBALS['max_image_pixels'] = 40000000;
    $GLOBALS['max_image_frames'] = 50;

    function config(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'plugin.triangle.media.app.max_image_pixels' => $GLOBALS['max_image_pixels'],
            'plugin.triangle.media.app.max_image_frames' => $GLOBALS['max_image_frames'],
            'plugin.triangle.media.app.max_image_memory' => 268435456,
            'plugin.triangle.media.app.max_image_disk' => 536870912,
            'plugin.triangle.media.app.max_image_time' => 10,
            'plugin.triangle.media.app.exts' => ['png', 'gif'],
            default => $default,
        };
    }

    require dirname(__DIR__) . '/src/ImageProcessing.php';

    if (!extension_loaded('imagick')) {
        echo "ImageMagick integration checks skipped: ext-imagick is unavailable.\n";
        exit(0);
    }

    $imagePath = tempnam(sys_get_temp_dir(), 'triangle-media-test-');
    if ($imagePath === false) {
        throw new RuntimeException('Unable to create a temporary test image.');
    }

    try {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        if (!is_string($png) || file_put_contents($imagePath, $png) === false) {
            throw new RuntimeException('Unable to write a temporary test image.');
        }

        $image = new \Triangle\Media\ImageProcessing($imagePath);

        foreach ([
            static fn() => $image->resizeImage(7000),
            static fn() => $image->cropImage(7000, 1),
        ] as $operation) {
            try {
                $operation();
            } catch (\Exception) {
                continue;
            }

            throw new RuntimeException('Image processing must reject operations above the configured pixel limit.');
        }

        $animatedPath = tempnam(sys_get_temp_dir(), 'triangle-media-animated-');
        if ($animatedPath === false) {
            throw new RuntimeException('Unable to create a temporary animated image path.');
        }

        try {
            $animation = new \Imagick();
            $animation->newImage(1, 1, new \ImagickPixel('white'));
            $animation->setImageFormat('gif');

            $secondFrame = new \Imagick();
            $secondFrame->newImage(1, 1, new \ImagickPixel('black'));
            $secondFrame->setImageFormat('gif');
            $animation->addImage($secondFrame);
            $animation->writeImages($animatedPath, true);
            $animation->clear();
            $secondFrame->clear();

            $animationCheck = new \Imagick($animatedPath);
            $frameCount = $animationCheck->getNumberImages();
            $animationCheck->clear();
            if ($frameCount < 2) {
                throw new RuntimeException('Unable to create a two-frame test image.');
            }

            $GLOBALS['max_image_frames'] = 1;
            $frameLimitRejected = false;
            try {
                new \Triangle\Media\ImageProcessing($animatedPath);
            } catch (\Exception) {
                $frameLimitRejected = true;
            }

            if (!$frameLimitRejected) {
                throw new RuntimeException('Processing must enforce the frame limit on existing images.');
            }
        } finally {
            $GLOBALS['max_image_frames'] = 50;
            @unlink($animatedPath);
        }

        $previousMemoryLimit = \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_MEMORY);
        try {
            \Triangle\Media\ImageProcessing::withResourceLimits(static function (): void {
                throw new RuntimeException('Exercise resource restoration.');
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Exercise resource restoration.') {
                throw $exception;
            }
        }

        if (\Imagick::getResourceLimit(\Imagick::RESOURCETYPE_MEMORY) !== $previousMemoryLimit) {
            throw new RuntimeException('ImageMagick limits must be restored after processing.');
        }

        echo "Image processing resource checks passed.\n";
    } finally {
        @unlink($imagePath);
    }
}
