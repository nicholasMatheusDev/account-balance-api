<?php

namespace App\Http\Controllers;

use App\Services\ProcessEventService;
use App\Services\Results\EventResult;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function __invoke(Request $request, ProcessEventService $service)
    {
        $payload = $request->validate([
            'type' => ['required', 'string', Rule::in(['deposit', 'withdraw', 'transfer'])],
            'origin' => ['nullable', 'string', 'required_if:type,withdraw,transfer'],
            'destination' => ['nullable', 'string', 'required_if:type,deposit,transfer'],
            'amount' => ['required', 'integer', 'min:0'],
        ]);

        $result = $service->handle($payload, $request->header('Idempotency-Key'));

        if ($result->status === EventResult::STATUS_NOT_FOUND) {
            return response('0', 404)->header('Content-Type', 'text/plain');
        }

        if ($result->status === EventResult::STATUS_CONFLICT) {
            return response()->json($result->payload, 409);
        }

        if ($result->status === EventResult::STATUS_INSUFFICIENT_FUNDS) {
            return response()->json($result->payload, 422);
        }

        return response()->json($result->payload, 201);
    }
}
