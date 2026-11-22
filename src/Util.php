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

namespace Triangle\Media;

class Util
{
    /**
     * Get image uri to Capella server
     *
     * @param string $id - image's id
     *
     * @return string image uri
     */
    static function getImageUri($id)
    {
        return config('plugin.triangle.media.app.image_uri', "https://" . request()->host() . "/") . $id;
    }

    /**
     * Get image's id from name
     *
     * 52df7fbf-ff1d-44e7-803a-e9f04d03d542.jpg
     *   -> 52df7fbf-ff1d-44e7-803a-e9f04d03d542
     *
     * @param string $name
     *
     * @return string
     */
    static function imageNameToId($name)
    {
        $defaultExtension = (string)config('plugin.triangle.media.app.target_ext');

        /**
         * Allow getting images with extension at the end of uri
         */
        if (preg_match('/\A(?P<id>[a-zA-Z0-9_-]{1,128})\.' . preg_quote($defaultExtension, '/') . '\z/iD', $name, $matches)) {
            return $matches['id'];
        }

        // if (stripos($name, '.') === false) {
        //     return $name . "." . $defaultExtension;
        // }

        return $name;
    }

    /**
     * Return path to image source by id
     *
     * If you store images in a cloud then upgrade this function
     * for getting image's source from the cloud
     *
     * @param string $id - image's id
     *
     * @throws \Exception
     *
     * @return string
     *
     */
    static function getPathToImageSource($id)
    {
        $directory = config('plugin.triangle.media.app.image_path');
        $extension = config('plugin.triangle.media.app.target_ext');

        if (!is_string($id) || !preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/D', $id)
            || !is_string($directory) || $directory === ''
            || !is_string($extension) || !preg_match('/\A[a-zA-Z0-9]{1,8}\z/D', $extension)) {
            throw new \InvalidArgumentException('Invalid image identifier or storage configuration.');
        }

        $imageRoot = realpath($directory);
        if ($imageRoot === false) {
            throw new \RuntimeException('Configured image directory does not exist.');
        }

        $imageUrl = $imageRoot . DIRECTORY_SEPARATOR . $id . '.' . $extension;

        if (!is_file($imageUrl)) {
            throw new \RuntimeException('Image does not exist in the configured storage directory.');
        }

        $resolvedImage = realpath($imageUrl);
        $rootPrefix = rtrim($imageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $comparisonPath = PHP_OS_FAMILY === 'Windows' ? strtolower((string)$resolvedImage) : $resolvedImage;
        $comparisonRoot = PHP_OS_FAMILY === 'Windows' ? strtolower($rootPrefix) : $rootPrefix;

        if ($resolvedImage === false || !str_starts_with($comparisonPath, $comparisonRoot)) {
            throw new \RuntimeException('Image path escapes the configured storage directory.');
        }

        return $resolvedImage;
    }
}
