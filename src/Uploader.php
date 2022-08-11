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

/**
 * Parent class, which describes acceptable extension,
 * file size and methods that check these parameters.
 */
class Uploader
{
    /**
     * Project's dentifier of image target
     *
     * @var string
     */
    public $projectId = '';

    /**
     * Check uploads dir, prepare project's ID
     *
     * @param string $projectId
     */
    public function __construct($projectId)
    {
        if (!file_exists(config('plugin.framex.media.app.image_uri')) || !is_writable(config('plugin.framex.media.app.image_uri'))) {
            $errorMessage = config('plugin.framex.media.app.image_uri') . ' directory should be writable';

            trigger_error($errorMessage, E_USER_ERROR);
            error_log($errorMessage);
        }

        $this->projectId = $projectId;
    }

    /**
     * Check extension
     *
     * @param string $mime
     *
     * @return bool
     */
    protected function isValidMimeType($mime)
    {
        return in_array($mime, config('plugin.framex.media.app.mime_types'));
    }

    /**
     * Check file size
     *
     * @param $size
     *
     * @return bool
     */
    protected function isValidSize($size)
    {
        return (int) $size <= config('plugin.framex.media.app.max_file_size');
    }

    /**
     * Save file to uploads dir
     *
     * @param string $filepath - path to the file or url
     *
     * @throws \Exception
     *
     * @return array - saved file data
     *
     */
    protected function saveFileToUploadDir($filepath)
    {
        /** Get file hash */
        $hash = hash_file('sha256', $filepath);

        /** Check for a saved copy */
        $duplicateImageData = $this->findDuplicateByHash($hash);

        if ($duplicateImageData) {
            return $duplicateImageData;
        }

        /** Generate filename */
        $path = config('plugin.framex.media.app.image_uri') . generateId() . "." . config('plugin.framex.media.app.target_ext');

        /** Save file to uploads dir */
        file_put_contents($path, file_get_contents($filepath));

        /** Get MIME-type from file */
        $mimeType = mime_content_type($path);

        if (!$this->isValidMimeType($mimeType)) {
            unlink($path);
            throw new \Exception('Wrong source mime-type: ' . $mimeType);
        }

        /** Get uploaded image */
        $image = new \Imagick($path);

        /** Add white background */
        // $image->setImageBackgroundColor(new \ImagickPixel('white'));
        // $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        /** Convert image to TARGET_EXT */
        $image->setImageFormat(config('plugin.framex.media.app.target_ext'));
        $image->setImageCompressionQuality(90);
        $image->writeImage($path);

        /** Save image resolution */
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        /** Get image size in bytes */
        $imageSize = strlen($image->getImageBlob());

        /**
         * Finding main color
         * 1) resize to 1x1 image with gaussian blur
         * 2) get color of top left pixel
         * 3) convert color from rgb to hex
         */
        $image->resizeImage(1, 1, \Imagick::FILTER_GAUSSIAN, 1);
        $color = $image->getImagePixelColor(1, 1)->getColor();
        $colorHex = sprintf("#%02x%02x%02x", $color['r'], $color['g'], $color['b']);

        $imageData = [
            'author' => $this->getAuthor(),
            'filepath' => $path,
            'width' => $width,
            'height' => $height,
            'color' => $colorHex,
            'mime' => config('plugin.framex.media.app.target_type'),
            'size' => $imageSize,
            'hash' => $hash,
            'projectId' => $this->projectId
        ];

        /** Save image data to DB */
        db()->insert(config('plugin.framex.media.app.database.images'), $imageData);

        return $imageData;
    }

    /**
     * Return request source IP address
     *
     * @return string
     */
    protected function getAuthor()
    {
        return getRequestIp();
    }

    /**
     * Try to find already saved image by hash
     *
     * @param string $hash
     */
    protected function findDuplicateByHash($hash)
    {
        /** Check for a hash existing */
        $response = db()->where('hash', $hash)->getOne(config('plugin.framex.media.app.database.images'));

        if (!!$response) {
            /* File already exist */
            return $response;
        }

        return null;
    }

    /**
     * Wrapper for saving image. Returns image's URL.
     *
     * Upgrade this function if you need to upload image
     * to a cloud or insert data to DB.
     * Also upgrade function \Methods::getPathToImageSource()
     * to set up getting image source.
     *
     * @param $file - temp file
     *
     * @throws \Exception
     *
     * @return array - image data
     *
     */
    protected function saveImage($file)
    {
        /** Copy temp file to upload dir */
        $imageData = $this->saveFileToUploadDir($file);
        $label = explode('.', basename($imageData['filepath']))[0];

        /** Get image's URL by id */
        $imageData['link'] = Util::getImageUri($label);

        return $imageData;
    }

    /**
     * Check and upload image
     *
     * @param string $file - path to image
     * @param string $size - image size
     * @param string $mime - image mime-type
     *
     * @throws \Exception
     *
     * @return array - uploaded image data
     *
     */
    protected function upload($file, $size, $mime)
    {
        if (!$file || !$size || !$mime) {
            throw new \Exception('Source is damaged');
        }

        if (!$this->isValidSize($size)) {
            throw new \Exception('Source is too big');
        }

        if (!$this->isValidMimeType($mime)) {
            throw new \Exception('Wrong source mime-type: ' . $mime);
        }

        /** Upload file and get its ID */
        $imageData = $this->saveImage($file);

        return $imageData;
    }

    /**
     * Public function for file uploading via POST form-data
     *
     * @param null|array|\localzet\FrameX\Http\UploadFile $data - image file from $_FILES
     *
     * @throws \Exception
     *
     * @return array - uploaded image data
     *
     */
    public function uploadFile($data)
    {
        if (!file_exists($data->getPathname())) {
            throw new \Exception('File is missing ' . $data->getPathname());
        }

        $mime = mime_content_type($data->getPathname());

        return $this->upload($data->getPathname(), $data->getSize(), $mime);
    }

    /**
     * Public function for uploading image by url
     *
     * @param {string} $url - path to image
     *
     * @throws \Exception
     *
     * @return array - uploaded image data
     *
     */
    public function uploadLink($url)
    {
        $size = request()->header('Content-Length');
        $mime = request()->header('Content-Type');

        return $this->upload($url, $size, $mime);
    }
}
