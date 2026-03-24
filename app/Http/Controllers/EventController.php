<?php

namespace App\Http\Controllers;

use App\Application\ProcessEvent;
use App\Application\Results\EventResult;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function __invoke(Request $request, ProcessEvent $useCase)
    {
        $payload = $request->validate([
            'type' => ['required', 'string', Rule::in(['deposit', 'withdraw', 'transfer'])],
            'origin' => ['nullable', 'string', 'required_if:type,withdraw,transfer'],
            'destination' => ['nullable', 'string', 'required_if:type,deposit,transfer'],
            'amount' => ['required', 'integer', 'min:0'],
        ]);

        $result = $useCase->handle($payload, $request->header('Idempotency-Key'));

        if ($result->status === EventResult::STATUS_NOT_FOUND) {
            return response('0', 404)->header('Content-Type', 'text/plain');
        }

        if ($result->status === EventResult::STATUS_CONFLICT) {
            return response()->json($result->payload, 409);
        }

        return response()->json($result->payload, 201);
    }
}
