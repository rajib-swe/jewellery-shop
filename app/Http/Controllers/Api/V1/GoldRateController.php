<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexGoldRateRequest;
use App\Http\Requests\StoreGoldRateRequest;
use App\Http\Requests\UpdateGoldRateRequest;
use App\Http\Resources\GoldRateResource;
use App\Models\GoldRate;
use App\Services\GoldRateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GoldRateController extends Controller
{
    public function index(
        IndexGoldRateRequest $request,
        GoldRateService $goldRates,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $goldRates->paginate(
            $validated,
            $validated['per_page'] ?? 15,
            $validated['page'] ?? 1,
        );

        return GoldRateResource::collection($paginator->withQueryString());
    }

    public function store(
        StoreGoldRateRequest $request,
        GoldRateService $goldRates,
    ): GoldRateResource {
        $goldRate = $goldRates->create($request->validated(), $request->user());

        return GoldRateResource::make($goldRate)->additional([
            'meta' => [
                'message' => 'Gold rate saved.',
            ],
        ]);
    }

    public function show(GoldRate $goldRate): GoldRateResource
    {
        return GoldRateResource::make($goldRate->load('createdBy'));
    }

    public function update(
        UpdateGoldRateRequest $request,
        GoldRate $goldRate,
        GoldRateService $goldRates,
    ): GoldRateResource {
        return GoldRateResource::make($goldRates->update($goldRate, $request->validated()));
    }

    public function destroy(GoldRate $goldRate, GoldRateService $goldRates): Response
    {
        $goldRates->delete($goldRate);

        return response()->noContent();
    }

    public function latest(GoldRateService $goldRates): AnonymousResourceCollection
    {
        return GoldRateResource::collection($goldRates->latest());
    }
}
