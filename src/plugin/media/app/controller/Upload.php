<?php

namespace plugin\media\app\controller;

use support\Request;

use FrameX\Media\Uploader;
use FrameX\Media\RateLimiter;

class Upload
{
    public function index(Request $request)
    {
        if (strtoupper($request->method()) !== 'POST') {
            return response("Метод не поддерживается", 405);
        }
        /** Middleware to reduce image upload intensity */
        $rate = $this->checkRateLimits();
        if ($rate !== true) {
            return $rate;
        };

        /** Check project's token */
        $projectId = $this->tryToFindProjectIdByToken();
        if ($projectId === false) {
            return response("Некорректный токен проекта", 403);
        }

        /** Process form data */
        if (!empty($request->file())) {
            return $this->uploadFile($projectId);
        } elseif (!empty($request->post('link'))) {
            return $this->uploadLink($projectId);
        } else {
            return response("Некорректный запрос", 400);
        }
    }

    /**
     * Function processed uploading file
     *
     * @param string $projectId - source project for image
     */
    protected function uploadFile($projectId)
    {
        /** This way we have $_FILES['files'] as a one file or array with files */

        if (empty(request()->file('file')->getUploadName())) {
            return response("Некорректный файл", 400);
        } else {
            $uploader = new Uploader($projectId);

            $imageData = $uploader->uploadFile(request()->file('file'));

            return $this->returnImageData($imageData);
        }
    }

    /**
     * Function processed uploading by link
     *
     * @param string $projectId - source project for image
     */
    protected function uploadLink($projectId)
    {
        if (empty(request()->post('link'))) {
            return response("Некорректная ссылка", 400);
        } else {
            $uploader = new Uploader($projectId);

            $imageData = $uploader->uploadLink((string) request()->post('link'));

            return $this->returnImageData($imageData);
        }
    }

    /**
     * Return success result with image link
     *
     * @param array $imageData
     */
    protected function returnImageData($imageData)
    {
        return response([
            'message' => 'Image uploaded',
            /**
             * Get ID as name without extension
             */
            'id' => basename($imageData['link'], '.' . config('plugin.framex.media.app.target_ext')),
            'url' => $imageData['link'],
            'mime' => $imageData['mime'],
            'width' => $imageData['width'],
            'height' => $imageData['height'],
            'color' => $imageData['color'],
            'size' => $imageData['size']
        ]);
    }

    /**
     * Check if client has allowed to upload image
     */
    private function checkRateLimits()
    {
        if (RateLimiter::instance()->isEnabled()) {
            $ip = getRequestIp();
            $key = 'RATELIMITER_CLIENT_' . $ip;

            $requestAllowed = RateLimiter::instance()->check($key);

            if (!$requestAllowed) {
                return response(RateLimiter::instance()->errorMessage(), 429);
            }
        }

        return true;
    }

    /**
     * Try to find project by given token
     *
     * @return string project's _id from MongoDB
     */
    private function tryToFindProjectIdByToken()
    {
        $token = !empty(request()->post('token')) ? (string) request()->post('token') : '';

        if ($token) {
            $mongoResponse = db()->where('token', $token)->getOne(config('plugin.framex.media.app.database.projects'));

            if (!empty($mongoResponse['id'])) {
                /** Return project's id */
                return $mongoResponse['id'];
            }
        }

        return false;
    }
}
