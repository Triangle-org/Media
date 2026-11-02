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

namespace Triangle\Media;

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
        $uploadDirectory = config('plugin.triangle.media.app.image_path');
        if (!is_string($uploadDirectory) || $uploadDirectory === '') {
            throw new \RuntimeException('The configured media image_path must be a non-empty directory path.');
        }
        if (!is_dir($uploadDirectory) && !@mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('Unable to create the configured media image_path directory.');
        }
        if (!is_writable($uploadDirectory)) {
            throw new \RuntimeException('The configured media image_path must be writable.');
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
        return in_array($mime, config('plugin.triangle.media.app.mime_types'), true);
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
        return (int) $size > 0 && (int) $size <= (int) config('plugin.triangle.media.app.max_file_size');
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
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to calculate the uploaded image hash.');
        }

        /** Check for a saved copy */
        $duplicateImageData = $this->findDuplicateByHash($hash);

        if ($duplicateImageData) {
            return $duplicateImageData;
        }

        $directory = rtrim((string)config('plugin.triangle.media.app.image_path'), DIRECTORY_SEPARATOR);
        $extension = (string)config('plugin.triangle.media.app.target_ext');
        $path = $directory . DIRECTORY_SEPARATOR . generateId() . '.' . $extension;
        $originalResourceLimits = [];

        try {
            if (!copy($filepath, $path)) {
                throw new \RuntimeException('Unable to copy the uploaded image into storage.');
            }

            /** Get MIME-type from file */
            $mimeType = mime_content_type($path);

            if (!$this->isValidMimeType($mimeType)) {
                throw new \Exception('Wrong source mime-type: ' . $mimeType);
            }

            foreach ([
                \Imagick::RESOURCETYPE_MEMORY => (int)config('plugin.triangle.media.app.max_image_memory', 268435456),
                \Imagick::RESOURCETYPE_MAP => (int)config('plugin.triangle.media.app.max_image_memory', 268435456),
                \Imagick::RESOURCETYPE_DISK => (int)config('plugin.triangle.media.app.max_image_disk', 536870912),
                \Imagick::RESOURCETYPE_THREAD => 1,
                \Imagick::RESOURCETYPE_TIME => (int)config('plugin.triangle.media.app.max_image_time', 10),
            ] as $resource => $limit) {
                $originalResourceLimits[$resource] = \Imagick::getResourceLimit($resource);
                if ($limit < 1 || !\Imagick::setResourceLimit($resource, $limit)) {
                    throw new \RuntimeException('Unable to apply the configured image processing resource limit.');
                }
            }

            $image = new \Imagick();
            $image->pingImage($path);
            $frames = $image->getNumberImages();
            $maxFrames = (int)config('plugin.triangle.media.app.max_image_frames', 50);
            $maxPixels = (int)config('plugin.triangle.media.app.max_image_pixels', 40000000);
            $totalPixels = 0;
            if ($frames < 1 || $frames > $maxFrames) {
                $image->clear();
                throw new \RuntimeException('Image frame count exceeds the configured limit.');
            }
            foreach ($image as $frame) {
                $totalPixels += $frame->getImageWidth() * $frame->getImageHeight();
                if ($totalPixels > $maxPixels) {
                    $image->clear();
                    throw new \RuntimeException('Image pixel count exceeds the configured limit.');
                }
            }
            $image->clear();

            $image = new \Imagick($path);

            /** Add white background */
            // $image->setImageBackgroundColor(new \ImagickPixel('white'));
            // $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            /** Convert image to TARGET_EXT */
            $image->setImageFormat($extension);
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
                'mime' => config('plugin.triangle.media.app.target_type'),
                'size' => $imageSize,
                'hash' => $hash,
                'projectId' => $this->projectId
            ];

            /** Save image data to DB */
            db()->insert(config('plugin.triangle.media.app.database.images'), $imageData);
            $image->clear();

            return $imageData;
        } catch (\Throwable $exception) {
            if (isset($image) && $image instanceof \Imagick) {
                $image->clear();
            }
            @unlink($path);
            throw $exception;
        } finally {
            foreach ($originalResourceLimits as $resource => $limit) {
                @\Imagick::setResourceLimit($resource, $limit);
            }
        }
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
        $response = db()->where('hash', $hash)->getOne(config('plugin.triangle.media.app.database.images'));

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
        if (!$file || !is_numeric($size) || !$mime) {
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

        return $this->upload($data->getPathname(), filesize($data->getPathname()), $mime);
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
        $download = RemoteImageDownloader::download((string)$url, (int)config('plugin.triangle.media.app.max_file_size'));
        try {
            return $this->upload($download['path'], $download['size'], $download['mime']);
        } finally {
            @unlink($download['path']);
        }
    }
}
