<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Market\TcmbMarketRateService;
use Illuminate\Http\JsonResponse;

class MarketRateController extends Controller
{
    public function __invoke(TcmbMarketRateService $rates): JsonResponse
    {
        return response()->json($rates->today());
    }
}
