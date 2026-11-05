<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Reports', description: 'Business report endpoints')]
class ReportController extends Controller
{
    #[OA\Get(
        path: '/reports/sales',
        summary: 'Sales report',
        description: 'Return sales data grouped by period',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'group_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month'], default: 'day')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sales report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function sales(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make(
            $reports->sales($period['from'], $period['to'], $request->validated('group_by') ?? 'day'),
        );
    }

    #[OA\Get(
        path: '/reports/stock',
        summary: 'Stock report',
        description: 'Return current stock summary by karat and category',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function stock(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->stock());
    }

    #[OA\Get(
        path: '/reports/pawn-outstanding',
        summary: 'Pawn outstanding report',
        description: 'Return all outstanding pawn balances',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pawn outstanding report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function pawnOutstanding(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->pawnOutstanding());
    }

    #[OA\Get(
        path: '/reports/overdue-pawns',
        summary: 'Overdue pawns report',
        description: 'Return all overdue pawn transactions',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Overdue pawns report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function overduePawns(ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->overduePawns());
    }

    #[OA\Get(
        path: '/reports/interest-earned',
        summary: 'Interest earned report',
        description: 'Return interest earned over a period',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Interest earned report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function interestEarned(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make($reports->interestEarned($period['from'], $period['to']));
    }

    #[OA\Get(
        path: '/reports/customers/{customer}/ledger',
        summary: 'Customer ledger report',
        description: 'Return the transaction ledger for a specific customer',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                in: 'path',
                required: true,
                description: 'Customer ID',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer ledger report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Customer not found'),
        ]
    )]
    public function customerLedger(
        ReportRequest $request,
        Customer $customer,
        ReportService $reports,
    ): ReportResource {
        $period = $this->period($request);

        return ReportResource::make($reports->customerLedger($customer, $period['from'], $period['to']));
    }

    #[OA\Get(
        path: '/reports/suppliers/{supplier}/ledger',
        summary: 'Supplier ledger report',
        description: 'Return the transaction ledger for a specific supplier',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'supplier',
                in: 'path',
                required: true,
                description: 'Supplier ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier ledger report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Supplier not found'),
        ]
    )]
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

    #[OA\Get(
        path: '/reports/profit',
        summary: 'Profit summary report',
        description: 'Return profit and loss summary for a period',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profit summary report data',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function profitSummary(ReportRequest $request, ReportService $reports): ReportResource
    {
        $period = $this->period($request);

        return ReportResource::make($reports->profitSummary($period['from'], $period['to']));
    }

    #[OA\Get(
        path: '/reports/dashboard',
        summary: 'Dashboard report',
        description: 'Return key business metrics for the dashboard',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard metrics',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function dashboard(ReportRequest $request, ReportService $reports): ReportResource
    {
        return ReportResource::make($reports->dashboard());
    }

    #[OA\Get(
        path: '/reports/sales-trend',
        summary: 'Sales trend report',
        description: 'Return sales trend data over a number of days',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sales trend data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'rows', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
