<?php
/**
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace plugin\media\app\controller;

use support\Request;
use Triangle\Engine\File;
use Triangle\Media\Uploader;
use Triangle\Media\RateLimiter;

class Upload
{
    public function index(Request $request)
    {
        if (strtoupper($request->method()) !== 'POST') {
            return response("Метод не поддерживается", 405);
        }
        /** Middleware to reduce image upload intensity */
        $rate = $this->checkRateLimits($request);
        if ($rate !== true) {
            return $rate;
        };

        /** Check project's token */
        $projectId = $this->tryToFindProjectIdByToken($request);
        if ($projectId === false) {
            return response("Некорректный токен проекта", 403);
        }

        /** Process form data */
        $file = $request->file('file');
        if ($file !== null) {
            return $this->uploadFile($request, $projectId, $file);
        }

        $link = $request->post('link');
        if (is_string($link) && trim($link) !== '') {
            return $this->uploadLink($request, $projectId, trim($link));
        } else {
            return response("Некорректный запрос", 400);
        }
    }

    /**
     * Function processed uploading file
     *
     * @param string $projectId Source project for image.
     */
    protected function uploadFile(Request $request, string $projectId, mixed $file)
    {
        if (!$file instanceof File || !$file->isValid() || trim((string)$file->getUploadName()) === '') {
            return response("Некорректный файл", 400);
        }

        $uploader = new Uploader($projectId, $request->getRequestIp());
        $imageData = $uploader->uploadFile($file);

        return $this->returnImageData($imageData);
    }

    /**
     * Function processed uploading by link
     *
     * @param string $projectId Source project for image.
     */
    protected function uploadLink(Request $request, string $projectId, string $link)
    {
        $uploader = new Uploader($projectId, $request->getRequestIp());
        $imageData = $uploader->uploadLink($link);

        return $this->returnImageData($imageData);
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
            'id' => basename($imageData['link'], '.' . config('plugin.triangle.media.app.target_ext')),
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
    private function checkRateLimits(Request $request)
    {
        if (RateLimiter::instance()->isEnabled()) {
            $ip = $request->getRequestIp() ?? 'unknown';
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
    private function tryToFindProjectIdByToken(Request $request)
    {
        $token = $request->post('token');

        if (is_string($token) && $token !== '') {
            $mongoResponse = db()->where('token', $token)->getOne(config('plugin.triangle.media.app.database.projects'));

            if (!empty($mongoResponse['id'])) {
                /** Return project's id */
                return $mongoResponse['id'];
            }
        }

        return false;
    }
}
