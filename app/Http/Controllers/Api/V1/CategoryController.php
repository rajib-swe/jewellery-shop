<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(CategoryService $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->all());
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): CategoryResource
    {
        $category = $categories->create($request->validated());

        return CategoryResource::make($category)->additional([
            'meta' => [
                'message' => 'Category created.',
            ],
        ]);
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        CategoryService $categories,
    ): CategoryResource {
        return CategoryResource::make($categories->update($category, $request->validated()));
    }

    public function destroy(Category $category, CategoryService $categories): Response
    {
        $categories->delete($category);

        return response()->noContent();
    }
}
