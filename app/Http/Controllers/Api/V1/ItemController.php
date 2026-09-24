<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexItemRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class ItemController extends Controller
{
    public function index(
        IndexItemRequest $request,
        StockService $stock,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $stock->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return ItemResource::collection($paginator->withQueryString());
    }

    public function store(StoreItemRequest $request, StockService $stock): ItemResource
    {
        $image = $request->file('image');
        $item = $stock->createInbound(
            $request->safe()->except(['image', 'remove_image']),
            $request->user(),
            $image instanceof UploadedFile ? $image : null,
        );

        return ItemResource::make($item)->additional([
            'meta' => [
                'message' => 'Item added to inventory.',
            ],
        ]);
    }

    public function show(Item $item): ItemResource
    {
        return ItemResource::make($item->load('category'));
    }

    public function update(
        UpdateItemRequest $request,
        Item $item,
        StockService $stock,
    ): ItemResource {
        $image = $request->file('image');
        $updatedItem = $stock->update(
            $item,
            $request->safe()->except(['image', 'remove_image']),
            $request->user(),
            $image instanceof UploadedFile ? $image : null,
            $request->boolean('remove_image'),
        );

        return ItemResource::make($updatedItem)->additional([
            'meta' => [
                'message' => 'Item updated.',
            ],
        ]);
    }

    public function destroy(Item $item, StockService $stock): Response
    {
        $stock->delete($item);

        return response()->noContent();
    }
}
