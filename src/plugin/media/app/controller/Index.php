<?php

namespace plugin\media\app\controller;

use support\Request;

class Index
{
    public function index(Request $request)
    {
        $request->get('token');

        if (!empty($request->get('token'))) {
            /**
             * Almost endless cookie lifetime is a max integer value
             * 2147483647 = 2^31 - 1
             */
            $cookie = $request->get('token');
        } else {
            $cookie = 'f36ac63b-7345-4b94-b9d4-c150824ef684';
        }

        return view("index")->cookie('token', $cookie, 2147483647);
    }
}
