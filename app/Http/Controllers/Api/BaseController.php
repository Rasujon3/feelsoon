<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\Api\UniformResponseTrait;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    use UniformResponseTrait;

    protected int $pagination = 20;
}
