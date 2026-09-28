<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\ReportService;
use App\Support\ReportPeriod;

class ReportController extends Controller
{
    public function sales(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make(
            $reports->sales($period['from'], $period['to'], $request->validated('group_by') ?? 'day'),
        );
    }

    public function stock(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->stock());
    }

    public function pawnOutstanding(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->pawnOutstanding());
    }

    public function overduePawns(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->overduePawns());
    }

    public function interestEarned(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make($reports->interestEarned($period['from'], $period['to']));
    }

    public function customerLedger(
        ReportRequest $request,
        Customer $customer,
        ReportService $reports,
    ): ReportResource {
        $period = $this->period($request);

        return ReportResource::make($reports->customerLedger($customer, $period['from'], $period['to']));
    }

    public function supplierLedger(Supplier $supplier, ReportService $reports): ReportResource
    {
        return ReportResource::make([
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'type' => $supplier->type->value,
            ],
            ...$reports->supplierLedger($supplier),
        ]);
    }

    public function profitSummary(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make($reports->profitSummary($period['from'], $period['to']));
    }

    public function dashboard(ReportRequest $request, ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->dashboard());
    }

    public function salesTrend(ReportRequest $request, ReportService $reports): ReportResource
    {
        $days = (int) ($request->validated('days') ?? 30);

        return ReportResource::make([
            'rows' => $reports->salesTrend($days),
        ]);
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    private function period(ReportRequest $request): array
    {
        return ReportPeriod::from($request);
    }
}
