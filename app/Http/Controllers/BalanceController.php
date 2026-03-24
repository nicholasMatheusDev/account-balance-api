<?php

namespace App\Http\Controllers;

use App\Application\GetBalance;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function __invoke(Request $request, GetBalance $useCase)
    {
        $data = $request->validate([
            'account_id' => ['required', 'string'],
        ]);

        $result = $useCase->handle($data['account_id']);

        if (! $result->found) {
            return response('0', 404)->header('Content-Type', 'text/plain');
        }

        return response((string) $result->balance, 200)->header('Content-Type', 'text/plain');
    }
}
