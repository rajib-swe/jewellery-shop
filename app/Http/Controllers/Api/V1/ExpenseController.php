<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function index(IndexExpenseRequest $request, ExpenseService $expenses): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $paginator = $expenses->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return ExpenseResource::collection($paginator->withQueryString());
    }

    public function store(StoreExpenseRequest $request, ExpenseService $expenses): ExpenseResource
    {
        return ExpenseResource::make($expenses->create($request->validated(), $request->user()))->additional([
            'meta' => [
                'message' => 'Expense recorded.',
            ],
        ]);
    }

    public function show(Expense $expense, ExpenseService $expenses): ExpenseResource
    {
        return ExpenseResource::make($expenses->find($expense));
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        ExpenseService $expenses,
    ): ExpenseResource {
        return ExpenseResource::make($expenses->update($expense, $request->validated(), $request->user()))->additional([
            'meta' => [
                'message' => 'Expense updated.',
            ],
        ]);
    }

    public function destroy(Expense $expense, ExpenseService $expenses): Response
    {
        $expenses->delete($expense);

        return response()->noContent();
    }
}
