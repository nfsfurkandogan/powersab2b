<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Dealer::class);

        $user = $request->user();

        $dealers = Dealer::query()
            ->when(! $user->hasRole('admin'), fn ($query) => $query->where('id', $user->dealer_id))
            ->orderBy('name')
            ->paginate(25);

        return response()->json($dealers);
    }

    public function show(Dealer $dealer): JsonResponse
    {
        $this->authorize('view', $dealer);

        return response()->json([
            'dealer' => $dealer,
        ]);
    }

    public function update(Request $request, Dealer $dealer): JsonResponse
    {
        $this->authorize('update', $dealer);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $dealer->fill($validated)->save();

        return response()->json([
            'dealer' => $dealer->fresh(),
        ]);
    }
}
