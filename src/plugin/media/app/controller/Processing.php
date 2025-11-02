<?php

namespace plugin\media\app\controller;

use support\Request;

use Triangle\Media\Util;
use Triangle\Media\UriDispatcher;
use Triangle\Media\ImageProcessing;

class Processing
{
    /**
     * @var array
     */
    const FILTERS = [
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
    ];

    public function index(Request $request, $requestUri)
    {
        $imageData = storage()->get($requestUri);

        if (!$imageData || empty($imageData)) {
            $imageData = $this->returnImage($requestUri);
            storage()->set($requestUri, $imageData);
        }

        return responseBlob($imageData);
    }

    protected function returnImage($requestUri)
    {
        $dispatcher = new UriDispatcher($requestUri, self::FILTERS);
        $imageId = $dispatcher->id;
        $filters = $dispatcher->parsedFilters;

        /**
         * Try to get path to image by id
         */
        $imageUrl = Util::getPathToImageSource($imageId);

        $imageProcessing = new ImageProcessing($imageUrl);

        foreach ($filters as $filter) {
            switch ($filter['filter']) {

                case 'crop':

                    $params = $filter['params'];

                    $width = $params['width'];
                    $height = $params['height'] ?? null;
                    $x = $params['x'] ?? null;
                    $y = $params['y'] ?? null;

                    $imageProcessing->cropImage($width, $height, $x, $y);

                    break;

                case 'resize':

                    $params = $filter['params'];

                    $width = $params['width'];
                    $height = $params['height'] ?? null;

                    $imageProcessing->resizeImage($width, $height);

                    break;

                case 'pixelize':

                    $params = $filter['params'];

                    $pixels = $params['pixels'];

                    $imageProcessing->pixelizeImage($pixels);

                    break;

                case 'cover':

                    $params = $filter['params'];

                    $color = $params['color'];

                    $imageProcessing->addCover($color);

                    break;
            }
        }

        $blob = $imageProcessing->getImageBlob();

        return $blob;
    }
}
