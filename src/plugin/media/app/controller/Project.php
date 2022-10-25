<?php

namespace plugin\media\app\controller;

use support\Request;

class Project
{
    public function index(Request $request)
    {
        if (strtoupper($request->method()) != 'POST') {
            return view("form");
        }

        /* @todo validate data */

        /** Generate project's token */
        $token = generateId();

        /** Compose project's data */
        $projectData = [
            'name' => (string) $request->post('name'),
            'description' => (string) $request->post('description'),
            'email' => (string) $request->post('email'),
            'token' => (string) $token,
        ];

        /** Save project's data to database */
        db()->insert(config('plugin.framex.media.app.database.projects'), $projectData);

        return response([
            'token' => $token
        ]);
    }
}
