<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSaleRequest;
use App\Http\Requests\StoreSalePaymentRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(IndexSaleRequest $request, SaleService $sales): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $sales->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return SaleResource::collection($paginator->withQueryString());
    }

    public function store(StoreSaleRequest $request, SaleService $sales): SaleResource
    {
        $sale = $sales->create(
            $request->validated(),
            $request->user(),
            $request->user()->can('manage gold rates'),
        );

        return SaleResource::make($sale)->additional([
            'meta' => [
                'message' => 'Sale recorded.',
            ],
        ]);
    }

    public function show(Sale $sale, SaleService $sales): SaleResource
    {
        return SaleResource::make($sales->find($sale));
    }

    public function addPayment(
        StoreSalePaymentRequest $request,
        Sale $sale,
        SaleService $sales,
    ): SaleResource {
        $updatedSale = $sales->addPayment($sale, $request->validated(), $request->user());

        return SaleResource::make($updatedSale)->additional([
            'meta' => [
                'message' => 'Payment recorded.',
            ],
        ]);
    }

    public function void(VoidSaleRequest $request, Sale $sale, SaleService $sales): SaleResource
    {
        $voidedSale = $sales->void($sale, $request->user(), $request->validated('reason'));

        return SaleResource::make($voidedSale)->additional([
            'meta' => [
                'message' => 'Sale voided.',
            ],
        ]);
    }
}
