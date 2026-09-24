<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerHistoryResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class CustomerController extends Controller
{
    public function index(
        IndexCustomerRequest $request,
        CustomerService $customers,
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $paginator = $customers->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1),
        );

        return CustomerResource::collection($paginator->withQueryString());
    }

    public function store(
        StoreCustomerRequest $request,
        CustomerService $customers,
    ): CustomerResource {
        $photo = $request->file('photo');
        $customer = $customers->create(
            $request->safe()->except(['photo', 'remove_photo']),
            $photo instanceof UploadedFile ? $photo : null,
        );

        return CustomerResource::make($customer)->additional([
            'meta' => [
                'message' => 'Customer created.',
            ],
        ]);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerService $customers,
    ): CustomerResource {
        $photo = $request->file('photo');
        $updatedCustomer = $customers->update(
            $customer,
            $request->safe()->except(['photo', 'remove_photo']),
            $photo instanceof UploadedFile ? $photo : null,
            $request->boolean('remove_photo'),
        );

        return CustomerResource::make($updatedCustomer)->additional([
            'meta' => [
                'message' => 'Customer updated.',
            ],
        ]);
    }

    public function destroy(Customer $customer, CustomerService $customers): Response
    {
        $customers->delete($customer);

        return response()->noContent();
    }

    public function history(Customer $customer, CustomerService $customers): CustomerHistoryResource
    {
        return CustomerHistoryResource::make($customers->history($customer));
    }
}
