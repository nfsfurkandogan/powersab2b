<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ClosePosSessionRequest;
use App\Http\Requests\Pos\OpenPosSessionRequest;
use App\Http\Resources\Pos\PosSessionResource;
use App\Models\PosSession;
use App\Services\Pos\PosSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PosSessionController extends Controller
{
    public function current(Request $request, PosSessionService $posSessionService): JsonResponse
    {
        $this->authorize('viewAny', PosSession::class);

        $validated = $request->validate([
            'cashbox_id' => ['nullable', 'integer', 'exists:cashboxes,id'],
        ]);

        $session = $posSessionService->current($request->user(), $validated['cashbox_id'] ?? null);

        if (! $session) {
            return response()->json([
                'data' => null,
            ]);
        }

        $this->authorize('view', $session);

        return response()->json([
            'data' => new PosSessionResource($session),
        ]);
    }

    public function open(OpenPosSessionRequest $request, PosSessionService $posSessionService): JsonResponse
    {
        $this->authorize('open', PosSession::class);

        $validated = $request->validated();

        $session = $posSessionService->open(
            user: $request->user(),
            cashboxId: isset($validated['cashbox_id']) ? (int) $validated['cashbox_id'] : null,
            openingCash: (float) $validated['opening_cash']
        );

        return response()->json([
            'data' => new PosSessionResource($session),
        ], Response::HTTP_CREATED);
    }

    public function close(ClosePosSessionRequest $request, PosSessionService $posSessionService): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $current = $posSessionService->current($user, isset($validated['cashbox_id']) ? (int) $validated['cashbox_id'] : null);
        if (! $current) {
            return response()->json([
                'message' => 'No open POS session found for current scope.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->authorize('close', $current);

        $session = $posSessionService->closeCurrent(
            user: $user,
            closingCashCounted: (float) $validated['closing_cash_counted'],
            cashboxId: isset($validated['cashbox_id']) ? (int) $validated['cashbox_id'] : null,
        );

        return response()->json([
            'data' => new PosSessionResource($session),
            'note' => $validated['note'] ?? null,
        ]);
    }
}
