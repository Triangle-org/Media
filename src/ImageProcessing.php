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

use Exception;
use Imagick;
use ImagickException;
use ImagickPixel;

/**
 * Class ImageProcessing
 *
 * @example create new class with image from path in constructor
 * $image = new ImageProcessing("C:/Users/image.png");
 * @example use filters
 * $image->resizeImage(400,300);
 * @example getImageBlob returns the image sequence as a blob
 * $image->getImageBlob();
 */

class ImageProcessing
{
    public $extension;
    public $height;
    public $width;
    public $path = null;

    private $imagick;

    /**
     * ImageProcessing constructor
     *
     * @param string $path - local path to image
     *
     * @throws Exception
     */
    public function __construct($path)
    {
        $this->imagick = new Imagick();
        $this->readImage($path);

        /** Fix image's orientation */
        $this->autoRotateImage();

        /** Remove all additional image's data */
        $this->imagick->stripImage();
    }

    /**
     * Crop image by dimensions with coordinates
     *
     * @example crop image with coordinates
     * $image->cropImage(100,100,0,10);
     * @example crop image by center coordinate
     * $image->cropImage(200,150);
     * $image->cropImage(100);
     *
     * @param int      $cropWidth
     * @param int      $cropHeight
     * @param int|null $x          - crop x
     * @param int|null $y          - crop y
     *
     * @throws Exception
     */
    public function cropImage($cropWidth, $cropHeight = null, $x = null, $y = null)
    {
        if ($cropWidth == null && $cropHeight == null) {
            throw new Exception("Incorrect input dimensions");
        }

        if ($cropWidth == null) {
            $cropWidth = $cropHeight;
        } elseif ($cropHeight == null) {
            $cropHeight = $cropWidth;
        }

        $cropWidth = (int)$cropWidth;
        $cropHeight = (int)$cropHeight;
        self::assertDimensionsWithinLimit($cropWidth, $cropHeight);

        if ($x == null || $y == null) {
            $ratio = max($cropHeight / $this->height, $cropWidth / $this->width);

            $scaledWidth = (int)ceil($ratio * $this->width);
            $scaledHeight = (int)ceil($ratio * $this->height);
            self::assertDimensionsWithinLimit($scaledWidth, $scaledHeight);

            $this->imagick->scaleImage($scaledWidth, $scaledHeight);

            $this->recalculateDimensions();

            $x = $this->width / 2 - $cropWidth / 2;
            $y = $this->height / 2 - $cropHeight / 2;

            $this->imagick->cropImage($cropWidth, $cropHeight, $x, $y);
        } else {
            $x = (int)$x;
            $y = (int)$y;
            if ($x < 0 || $y < 0 || $x > $this->width - $cropWidth || $y > $this->height - $cropHeight) {
                throw new Exception('Crop area is outside the source image.');
            }

            $this->imagick->cropImage($cropWidth, $cropHeight, $x, $y);
        }

        $this->recalculateDimensions();
    }

    /**
     * Resize image with dimensions
     *
     * @example resize image by height and width
     * $image->resizeImage(400,300);
     * $image->resizeImage(100);
     *
     * @param int $resizeWidth
     * @param int $resizeHeight
     *
     * @throws Exception
     */
    public function resizeImage($resizeWidth, $resizeHeight = 0)
    {
        $resizeWidth = (int)$resizeWidth;
        $resizeHeight = (int)$resizeHeight;

        if ($resizeWidth < 0 || $resizeHeight < 0 || (!$resizeWidth && !$resizeHeight)) {
            throw new Exception('Incorrect input dimensions');
        }

        if ($resizeWidth > 0 && $resizeHeight > 0) {
            self::assertDimensionsWithinLimit($resizeWidth, $resizeHeight);
        } elseif ($resizeWidth > 0) {
            $scaledHeight = (int)ceil($this->height * ($resizeWidth / $this->width));
            self::assertDimensionsWithinLimit($resizeWidth, $scaledHeight);
        } else {
            $scaledWidth = (int)ceil($this->width * ($resizeHeight / $this->height));
            self::assertDimensionsWithinLimit($scaledWidth, $resizeHeight);
        }

        if ($resizeWidth == 0) {
            $this->imagick->scaleImage(0, $resizeHeight);
        } elseif ($resizeHeight == 0) {
            $this->imagick->scaleImage($resizeWidth, 0);
        } else {
            $ratio = min($resizeHeight / $this->height, $resizeWidth / $this->width);

            $this->imagick->scaleImage($ratio * $this->width, $ratio * $this->height);
        }

        $this->recalculateDimensions();
    }

    /**
     * Pixelize image
     *
     * @example pixelize image by pixels count
     * $image->pixelizeImage(15);
     *
     * @param int $pixels
     *
     * @throws Exception
     */
    public function pixelizeImage($pixels)
    {
        $pixels = (int)$pixels;
        if ($pixels < 1 || $pixels > max($this->width, $this->height)) {
            throw new Exception('Incorrect ratio');
        }

        if ($this->width > $this->height) {
            $ratio = $this->width / $pixels;
            $this->resizeImage($this->width / $ratio, 0);
            $this->resizeImage($this->width * $ratio, 0);
        } else {
            $ratio = $this->height / $pixels;
            $this->resizeImage(0, $this->height / $ratio);
            $this->resizeImage(0, $this->height * $ratio);
        }
    }

    /**
     * Add a cover for target image
     *
     * @param string $color         - hex code of cover's color without hash symbol. Ex: eff2f5
     * @param int    $width         - width of cover for image
     * @param float  $maxWidthRatio - max part of cover's width to stretch image
     * @param int    $margin        - number pof pixels up and down image on cover
     *
     * @throws Exception|ImagickException
     */
    public function addCover($color, $width = 1000, $maxWidthRatio = 0.6, $margin = 10)
    {
        /**
         * Check for color validness
         * - Color must be a hexadecimal digit
         * - 3 or 6 symbols string length
         */
        $isValidColor = ctype_xdigit($color) && (strlen($color) == 6 || strlen($color) == 3);

        if (!$isValidColor) {
            throw new Exception("Color must be in a hex format without hash symbol");
        }

        /**
         * Count max image's width to be placed onto cover
         */
        $maxImageWidth = $width * $maxWidthRatio;

        /**
         * Resize image if it is wider than target part of cover's width
         */
        if ($this->width > $maxImageWidth) {
            $this->resizeImage($maxImageWidth);
        }

        /**
         * Count cover's height
         *
         * •-------------------------•  -
         * | cover                   |  | margin
         * |      •-----------•      |  -
         * |      |           |      |  |
         * |      |   image   |      |  | image's height
         * |      |           |      |  |
         * |      •-----------•      |  -
         * |                         |  | margin
         * •-------------------------•  -
         *        |-----------|
         *         image's width is not more
         *         than the 60% of cover's width
         */
        $coverHeight = $margin * 2 + $this->height;
        self::assertDimensionsWithinLimit((int)$width, (int)$coverHeight);

        /**
         * Create a new image as a cover
         */
        $cover = new Imagick();
        $cover->newImage($width, $coverHeight, new ImagickPixel('#' . $color));
        $cover->setImageFormat('png');

        /**
         * Count image's position
         */
        $imagePosition = [
            'x' => $width / 2 - $this->width / 2,
            'y' => $margin
        ];

        /**
         * Compose cover and image
         */
        $cover->compositeImage($this->imagick, Imagick::COMPOSITE_IN, $imagePosition['x'], $imagePosition['y']);

        /**
         * Save composed image as a processed image
         */
        $this->imagick = $cover;

        /**
         * Update dimensions
         */
        $this->recalculateDimensions();
    }

    /**
     * Get image blob
     *
     * @return string - blob image
     */
    public function getImageBlob()
    {
        return $this->imagick->getImageBlob();
    }

    /**
     * Run image work with process-wide ImageMagick resource limits applied.
     */
    public static function withResourceLimits(callable $callback): mixed
    {
        $originalLimits = [];
        $memoryLimit = (int)config('plugin.triangle.media.app.max_image_memory', 268435456);
        $limits = [
            Imagick::RESOURCETYPE_MEMORY => $memoryLimit,
            Imagick::RESOURCETYPE_MAP => $memoryLimit,
            Imagick::RESOURCETYPE_DISK => (int)config('plugin.triangle.media.app.max_image_disk', 536870912),
            Imagick::RESOURCETYPE_THREAD => 1,
            Imagick::RESOURCETYPE_TIME => (int)config('plugin.triangle.media.app.max_image_time', 10),
        ];

        try {
            foreach ($limits as $resource => $limit) {
                $originalLimits[$resource] = Imagick::getResourceLimit($resource);
                if ($limit < 1 || !Imagick::setResourceLimit($resource, $limit)) {
                    throw new \RuntimeException('Unable to apply the configured image processing resource limit.');
                }
            }

            return $callback();
        } finally {
            foreach ($originalLimits as $resource => $limit) {
                @Imagick::setResourceLimit($resource, $limit);
            }
        }
    }

    /** @internal */
    public static function assertDimensionsWithinLimit(int $width, int $height): void
    {
        $maxPixels = (int)config('plugin.triangle.media.app.max_image_pixels', 40000000);
        if ($width < 1 || $height < 1 || $maxPixels < 1 || $width > intdiv($maxPixels, $height)) {
            throw new Exception('Image dimensions exceed the configured pixel limit.');
        }
    }

    /** @internal */
    public static function assertFrameCountWithinLimit(int $frameCount): void
    {
        $maxFrames = (int)config('plugin.triangle.media.app.max_image_frames', 50);
        if ($frameCount < 1 || $maxFrames < 1 || $frameCount > $maxFrames) {
            throw new Exception('Image frame count exceeds the configured limit.');
        }
    }

    /**
     * Defines available image formats
     *
     * @param string $extension - ext we want to check
     *
     * @return bool
     */
    private function isValidExtension($extension)
    {
        $extension = strtolower($extension);

        return in_array($extension, config('plugin.triangle.media.app.exts'), true);
    }

    /**
     * Calculate image dimensions
     */
    private function recalculateDimensions()
    {
        $this->width = $this->imagick->getImageWidth();
        $this->height = $this->imagick->getImageHeight();
    }

    /**
     * Read image from local path
     *
     * @param string $path - local path to image
     *
     * @throws Exception
     */
    private function readImage($path)
    {
        $this->path = $path;

        $probe = new Imagick();
        try {
            $probe->pingImage($path);
            self::assertFrameCountWithinLimit($probe->getNumberImages());

            $maxPixels = (int)config('plugin.triangle.media.app.max_image_pixels', 40000000);
            if ($maxPixels < 1) {
                throw new Exception('Image pixel limit must be greater than zero.');
            }

            $totalPixels = 0;
            foreach ($probe as $frame) {
                $frameWidth = $frame->getImageWidth();
                $frameHeight = $frame->getImageHeight();
                if ($frameWidth < 1 || $frameHeight < 1 || $frameWidth > intdiv($maxPixels, $frameHeight)) {
                    throw new Exception('Image dimensions exceed the configured pixel limit.');
                }

                $framePixels = $frameWidth * $frameHeight;
                if ($framePixels > $maxPixels - $totalPixels) {
                    throw new Exception('Image frames exceed the configured total pixel limit.');
                }
                $totalPixels += $framePixels;
            }
        } finally {
            $probe->clear();
        }

        $readResult = @$this->imagick->readImage($path);

        $this->extension = $this->imagick->getImageFormat();
        if (!$this->isValidExtension($this->extension)) {
            throw new Exception("Unsupported Extension");
        }

        $this->recalculateDimensions();
    }

    /**
     * Rotate image right by orientation's params
     */
    private function autoRotateImage()
    {
        $orientation = $this->imagick->getImageOrientation();

        switch ($orientation) {
                /** rotate 180 degrees */
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $this->imagick->rotateimage("#000", 180);
                break;

                /** rotate 90 degrees CW */
            case Imagick::ORIENTATION_RIGHTTOP:
                $this->imagick->rotateimage("#000", 90);
                break;

                /** rotate 90 degrees CCW */
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $this->imagick->rotateimage("#000", -90);
                break;
        }

        /** Save image's orientation */
        $this->imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }
}
