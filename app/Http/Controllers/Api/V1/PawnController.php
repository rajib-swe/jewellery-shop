<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForfeitPawnRequest;
use App\Http\Requests\IndexPawnRequest;
use App\Http\Requests\RedeemPawnRequest;
use App\Http\Requests\RenewPawnRequest;
use App\Http\Requests\StorePawnPaymentRequest;
use App\Http\Requests\StorePawnRequest;
use App\Http\Resources\PawnResource;
use App\Models\Pawn;
use App\Services\PawnService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;

class PawnController extends Controller
{
    public function index(IndexPawnRequest $request, PawnService $pawns): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $pawns->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return PawnResource::collection($paginator->withQueryString());
    }

    public function store(StorePawnRequest $request, PawnService $pawns): PawnResource
    {
        $items = (array) $request->input('items', []);
        $photos = [];

        foreach ($items as $index => $item) {
            $photo = is_array($item) ? ($item['photo'] ?? null) : null;

            if ($photo instanceof UploadedFile) {
                $photos[$index] = $photo;
            }
        }

        $pawn = $pawns->create(
            $request->safe()->except(['items', 'items.*.photo']),
            $request->user(),
            $photos,
        );

        return PawnResource::make($pawn)->additional([
            'meta' => [
                'message' => 'Pawn recorded.',
            ],
        ]);
    }

    public function show(Pawn $pawn, PawnService $pawns): PawnResource
    {
        return PawnResource::make($pawns->find($pawn));
    }

    public function addPayment(
        StorePawnPaymentRequest $request,
        Pawn $pawn,
        PawnService $pawns,
    ): PawnResource {
        $updatedPawn = $pawns->addPayment($pawn, $request->validated(), $request->user());

        return PawnResource::make($updatedPawn)->additional([
            'meta' => [
                'message' => 'Payment recorded.',
            ],
        ]);
    }

    public function redeem(RedeemPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $redeemedPawn = $pawns->redeem($pawn, $request->validated(), $request->user());

        return PawnResource::make($redeemedPawn)->additional([
            'meta' => [
                'message' => 'Pawn redeemed.',
            ],
        ]);
    }

    public function renew(RenewPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $renewedPawn = $pawns->renew($pawn, $request->validated(), $request->user());

        return PawnResource::make($renewedPawn)->additional([
            'meta' => [
                'message' => 'Pawn renewed.',
            ],
        ]);
    }

    public function forfeit(ForfeitPawnRequest $request, Pawn $pawn, PawnService $pawns): PawnResource
    {
        $forfeitedPawn = $pawns->forfeit($pawn, $request->validated(), $request->user());

        return PawnResource::make($forfeitedPawn)->additional([
            'meta' => [
                'message' => 'Pawn forfeited.',
            ],
        ]);
    }
}
