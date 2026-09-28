<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSupplierRequest;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierLedgerResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function index(IndexSupplierRequest $request, SupplierService $suppliers): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $suppliers->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return SupplierResource::collection($paginator->withQueryString());
    }

    public function store(StoreSupplierRequest $request, SupplierService $suppliers): SupplierResource
    {
        return SupplierResource::make($suppliers->create($request->validated()))->additional([
            'meta' => [
                'message' => 'Supplier created.',
            ],
        ]);
    }

    public function show(Supplier $supplier, SupplierService $suppliers): SupplierResource
    {
        return SupplierResource::make($suppliers->find($supplier));
    }

    public function update(
        UpdateSupplierRequest $request,
        Supplier $supplier,
        SupplierService $suppliers,
    ): SupplierResource {
        return SupplierResource::make($suppliers->update($supplier, $request->validated()))->additional([
            'meta' => [
                'message' => 'Supplier updated.',
            ],
        ]);
    }

    public function destroy(Supplier $supplier, SupplierService $suppliers): Response
    {
        $suppliers->delete($supplier);

        return response()->noContent();
    }

    public function ledger(Supplier $supplier, SupplierService $suppliers): SupplierLedgerResource
    {
        return SupplierLedgerResource::make([
            'supplier' => $supplier,
            'ledger' => $suppliers->ledger($supplier),
        ]);
    }

    public function addPayment(
        StoreSupplierPaymentRequest $request,
        Supplier $supplier,
        PurchaseService $purchases,
        SupplierService $suppliers,
    ): JsonResponse {
        $purchases->addPayment($supplier, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Payment recorded.',
            'data' => SupplierResource::make($suppliers->find($supplier))->resolve($request),
        ], 201);
    }
}
