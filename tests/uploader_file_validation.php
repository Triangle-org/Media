<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Triangle\Engine {
    final class File
    {
        public function __construct(private bool $valid) {}

        public function isValid(): bool
        {
            return $this->valid;
        }
    }
}

namespace {
    function config(string $key, mixed $default = null): mixed
    {
        return $key === 'plugin.triangle.media.app.image_path' ? $GLOBALS['media_test_directory'] : $default;
    }

    $GLOBALS['media_test_directory'] = sys_get_temp_dir() . '/triangle-media-uploader-' . bin2hex(random_bytes(6));
    if (!mkdir($GLOBALS['media_test_directory'], 0700)) {
        throw new RuntimeException('Unable to create a temporary upload directory.');
    }

    require dirname(__DIR__) . '/src/Uploader.php';

    try {
        $uploader = new \Triangle\Media\Uploader('project-123');

        foreach ([new \stdClass(), new \Triangle\Engine\File(false)] as $invalidFile) {
            try {
                $uploader->uploadFile($invalidFile);
            } catch (InvalidArgumentException) {
                continue;
            }

            throw new RuntimeException('The uploader must reject invalid file inputs before reading paths.');
        }
    } finally {
        @rmdir($GLOBALS['media_test_directory']);
    }

    echo "Uploader file validation checks passed.\n";
}
