<?php

/**
 * @package     FrameX (FX) Media Plugin
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */

return [
        'enable' => true,
        'project_registration' => true,
        'image_uri' => 'https://example.ru/media/', // URL для изображений
        'image_path' => public_path() . '/media/upload/', // Директория для изображений

        'max_file_size' => 15 * 1024 * 1024,
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
        ]
];
