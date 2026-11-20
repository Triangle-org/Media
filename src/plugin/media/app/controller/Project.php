<?php

/**
 * @package     Triangle Media Plugin
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace plugin\media\app\controller;

use support\Request;
use Triangle\Media\RateLimiter;

class Project
{
    public function index(Request $request)
    {
        if (!config('plugin.triangle.media.app.project_registration', false)) {
            return response('Project registration is disabled', 403);
        }

        $method = strtoupper($request->method());
        if ($method === 'GET') {
            return view("form");
        }

        if ($method !== 'POST') {
            return response('Method not allowed', 405);
        }

        if (RateLimiter::instance()->isEnabled()) {
            $key = 'MEDIA_PROJECT_REGISTER_' . ($request->getRequestIp() ?? 'unknown');
            if (!RateLimiter::instance()->check($key)) {
                return response(RateLimiter::instance()->errorMessage(), 429);
            }
        }

        $name = $request->post('name');
        $description = $request->post('description', '');
        $email = $request->post('email', '');

        if (!is_string($name) || !is_string($description) || !is_string($email)) {
            return response('Invalid project data', 422);
        }

        $name = trim($name);
        $description = trim($description);
        $email = trim($email);

        if ($name === '' || strlen($name) > 120 || strlen($description) > 2000
            || strlen($email) > 254
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return response('Invalid project data', 422);
        }

        /** Generate project's token */
        $token = generateId();

        /** Compose project's data */
        $projectData = [
            'name' => $name,
            'description' => $description,
            'email' => $email,
            'token' => (string) $token,
        ];

        /** Save project's data to database */
        db()->insert(config('plugin.triangle.media.app.database.projects'), $projectData);

        return response([
            'token' => $token
        ]);
    }
}
