<?php

/**
 * @package     Triangle Media Plugin
 * @link        https://github.com/Triangle-org/Media
 *
 * @author      localzet <creator@localzet.ru>
 *
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * @license     AGPL-3.0-or-later
 */

return [
    'enable' => true,
    'project_registration' => false,
    'image_uri' => 'https://example.ru/media/', // URL для изображений
    'image_path' => public_path() . '/media/upload/', // Директория для изображений

    'max_file_size' => 15 * 1024 * 1024,
    'max_image_pixels' => 40000000,
    'max_image_frames' => 50,
    'max_image_memory' => 256 * 1024 * 1024,
    'max_image_disk' => 512 * 1024 * 1024,
    'max_image_time' => 10,
    'target_type' => 'image/png',
    'mime_types' => [
        'image/jpg',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/bmp',
        'image/tiff',
    ],
    'target_ext' => 'png',
    'exts' => [
        'jpg',
        'png',
        'jpeg',
        'gif',
        'bmp',
        'tiff',
    ],

    'rate_limiter' => [
        'enable' => false,
        'quota' => 3,
        'cycle' => 60
    ],

    'database' => [
        'images' => 'Media_Images',
        'projects' => 'Media_Projects'
    ],

    'filters' => [
        'crop' => [
            'title' => 'crop',
            'pattern' => '{width|int}[x{height|int}[&{x|int},{y|int}]]' // W[xH[&X,Y]]
        ],
        'resize' => [
            'title' => 'resize',
            'pattern' => '{width|int}[x{height|int}]' // W[xH]
        ],
        'pixelize' => [
            'title' => 'pixelize',
            'pattern' => '{pixels|int}'
        ],
        'cover' => [
            'title' => 'cover',
            'pattern' => '{color|string}'
        ]
    ]
];
