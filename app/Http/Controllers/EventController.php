<?php

namespace App\Http\Controllers;

use App\Services\ProcessEventService;
use App\Services\Results\EventResult;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function __invoke(Request $request, ProcessEventService $events)
    {
        $payload = $request->validate([
            'type' => ['required', 'string', Rule::in(['deposit', 'withdraw', 'transfer'])],
            'origin' => ['nullable', 'string', 'required_if:type,withdraw,transfer'],
            'destination' => ['nullable', 'string', 'required_if:type,deposit,transfer'],
            'amount' => ['required', 'integer', 'min:0'],
        ]);

        $result = $events->handle($payload, $request->header('Idempotency-Key'));

        return match ($result->status) {
            EventResult::STATUS_NOT_FOUND => response('0', 404)->header('Content-Type', 'text/plain'),
            EventResult::STATUS_CONFLICT => response()->json($result->payload, 409),
            EventResult::STATUS_INSUFFICIENT_FUNDS => response()->json($result->payload, 422),
            default => response()->json($result->payload, 201),
        };
    }
}
