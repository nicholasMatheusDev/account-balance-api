<?php

namespace App\Http\Controllers;

use App\Services\ResetStateService;

class ResetController extends Controller
{
    public function __invoke(ResetStateService $service)
    {
        $service->handle();

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
