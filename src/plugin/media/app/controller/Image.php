<?php

namespace plugin\media\app\controller;

use support\Request;

use FrameX\Media\Util;

class Image
{
    public function index(Request $request, $requestUri)
    {
        /** Get the image id from  request URI */
        $imageId = Util::imageNameToId($requestUri);

        /** Check if image exist */
        Util::getPathToImageSource($imageId);

        /** Create a link to the image */
        $imageURL = Util::getImageUri($imageId);

        return view("image", ['imageURL' => $imageURL]);
    }
}
