<?php

namespace App\Http\Controllers;

use App\Application\ResetState;

class ResetController extends Controller
{
    public function __invoke(ResetState $useCase)
    {
        $useCase->handle();

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
