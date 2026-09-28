<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPurchaseRequest;
use App\Http\Requests\PurchaseRateRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function index(IndexPurchaseRequest $request, PurchaseService $purchases): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $purchases->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return PurchaseResource::collection($paginator->withQueryString());
    }

    public function store(StorePurchaseRequest $request, PurchaseService $purchases): PurchaseResource
    {
        $purchase = $purchases->create(
            $request->validated(),
            $request->user(),
            $request->user()->can('manage gold rates'),
        );

        return PurchaseResource::make($purchase)->additional([
            'meta' => [
                'message' => 'Purchase recorded.',
            ],
        ]);
    }

    public function show(Purchase $purchase, PurchaseService $purchases): PurchaseResource
    {
        return PurchaseResource::make($purchases->find($purchase));
    }

    /**
     * The karat rates a purchase dated on `date` would be priced at, so the form
     * can show the cost of a line before it is saved.
     */
    public function rates(PurchaseRateRequest $request, PurchaseService $purchases): JsonResponse
    {
        return response()->json([
            'data' => $purchases->previewRates(
                CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay(),
            ),
        ]);
    }
}
