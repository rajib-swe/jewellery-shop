<?php

namespace App\Http\Controllers\Api\V1;

use App\CashDirection;
use App\CashSourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashBookSummaryRequest;
use App\Http\Requests\IndexCashTransactionRequest;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Http\Resources\CashBookSummaryResource;
use App\Http\Resources\CashTransactionResource;
use App\Services\CashBookService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashBookController extends Controller
{
    public function index(IndexCashTransactionRequest $request, CashBookService $cashBook): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $cashBook->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return CashTransactionResource::collection($paginator->withQueryString());
    }

    /**
     * Opening, in, out and closing for one day, plus the per-method split.
     */
    public function summary(CashBookSummaryRequest $request, CashBookService $cashBook): CashBookSummaryResource
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today')->startOfDay();

        return CashBookSummaryResource::make($cashBook->summaryFor($date, $request->validated()));
    }

    /**
     * Money that moves without a source document, such as a bank withdrawal or
     * a float top-up. A sale, pawn or supplier payment is never posted here.
     */
    public function store(StoreCashTransactionRequest $request, CashBookService $cashBook): CashTransactionResource
    {
        $validated = $request->validated();
        $direction = CashDirection::from($validated['direction']);
        $amount = (float) $validated['amount'];

        $transaction = $direction === CashDirection::In
            ? $cashBook->recordIn(CashSourceType::CashAdjustment, null, $amount, $request->user(), $validated)
            : $cashBook->recordOut(CashSourceType::CashAdjustment, null, $amount, $request->user(), $validated);

        return CashTransactionResource::make($transaction->load('user:id,name'))->additional([
            'meta' => [
                'message' => 'Cash entry recorded.',
            ],
        ]);
    }
}
