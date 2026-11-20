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

class Index
{
    public function index(Request $request)
    {
        $response = view("index");
        $token = $request->get('token');

        if (!is_string($token) || !preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/D', $token)) {
            return $response;
        }

        return $response->cookie('token', $token, 604800, '/', '', $request->isSecure(), false, 'Lax');
    }
}
