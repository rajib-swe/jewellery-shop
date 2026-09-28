<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseDayRequest;
use App\Http\Resources\CashBookSummaryResource;
use App\Http\Resources\DailyClosingResource;
use App\Models\DailyClosing;
use App\Services\CashBookService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DailyClosingController extends Controller
{
    public function index(CashBookService $cashBook): AnonymousResourceCollection
    {
        return DailyClosingResource::collection(
            DailyClosing::query()
                ->with(['closedBy:id,name', 'reopenedBy:id,name'])
                ->orderByDesc('date')
                ->paginate(perPage: 31),
        );
    }

    public function show(CloseDayRequest $request, CashBookService $cashBook): CashBookSummaryResource
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay();

        return CashBookSummaryResource::make($cashBook->summaryFor($date));
    }

    public function store(CloseDayRequest $request, CashBookService $cashBook): DailyClosingResource
    {
        $date = CarbonImmutable::parse($request->validated('date'))->startOfDay();

        $closing = $cashBook->close($date, $request->user(), [], $request->validated('note'));

        return DailyClosingResource::make($closing->load(['closedBy:id,name', 'reopenedBy:id,name']))->additional([
            'meta' => [
                'message' => 'Day closed.',
            ],
        ]);
    }

    public function reopen(
        DailyClosing $closing,
        Request $request,
        CashBookService $cashBook,
    ): DailyClosingResource {
        $reopened = $cashBook->reopen($closing, $request->user());

        return DailyClosingResource::make($reopened->load(['closedBy:id,name', 'reopenedBy:id,name']))->additional([
            'meta' => [
                'message' => 'Day reopened.',
            ],
        ]);
    }
}
