<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\StockSummaryResource;
use App\ItemStatus;
use App\Models\Item;
use App\Services\StockService;
use App\StockMovementType;

class StockController extends Controller
{
    public function summary(StockService $stock): StockSummaryResource
    {
        return StockSummaryResource::make($stock->summary());
    }

    public function adjust(
        StockAdjustmentRequest $request,
        Item $item,
        StockService $stock,
    ): ItemResource {
        $validated = $request->validated();
        $updatedItem = $stock->adjust(
            $item,
            $request->user(),
            StockMovementType::from($validated['type']),
            (float) $validated['weight'],
            ItemStatus::from($validated['status']),
            $validated['note'] ?? null,
        );

        return ItemResource::make($updatedItem)->additional([
            'meta' => [
                'message' => 'Stock adjustment recorded.',
            ],
        ]);
    }
}
