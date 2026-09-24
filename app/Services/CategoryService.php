<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /**
     * @return Collection<int, Category>
     */
    public function all(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, description?: ?string}  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    /**
     * @param  array{name?: string, description?: ?string}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        if ($category->items()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'A category with inventory items cannot be deleted.',
            ]);
        }

        $category->delete();
    }
}
