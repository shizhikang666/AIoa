<?php

namespace app\controller;

use think\Response;

class Index
{
    public function index(): Response
    {
        return Response::create('', 'html', 404);
    }
}
