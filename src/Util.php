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

namespace FrameX\Media;

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
        return config('plugin.framex.media.app.image_uri', "https://" . request()->host() . "/") . $id;
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
        $defaultExtension = config('plugin.framex.media.app.target_ext');

        /**
         * Allow getting images with extension at the end of uri
         */
        if (preg_match('/(?P<id>[\w-]+)\.' . $defaultExtension . '$/', $name, $matches)) {
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
        $imageUrl = config('plugin.framex.media.app.image_uri') . $id . '.' . config('plugin.framex.media.app.target_ext');

        if (!file_exists($imageUrl)) {
            throw new \Exception('Файл не существует в' . config('plugin.framex.media.app.image_uri') . $id . '.' . config('plugin.framex.media.app.target_ext'));
        }

        return $imageUrl;
    }
}
