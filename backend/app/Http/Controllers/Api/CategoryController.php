<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return Category::query()
            ->when($request->boolean('include_inactive'), fn ($q) => $q, fn ($q) => $q->where('is_active', true))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $data['is_active'] ?? true;

        return response()->json(Category::create($data), 201);
    }

    public function show(Category $category)
    {
        return $category->loadCount('items');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => "sometimes|required|string|max:100|unique:categories,name,{$category->id}",
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
        $category->update($data);

        return $category->fresh()->loadCount('items');
    }

    public function destroy(Category $category)
    {
        if ($category->items()->exists()) {
            return response()->json(['message' => 'Category is referenced by inventory items; deactivate it instead.'], 422);
        }
        $category->delete();

        return response()->noContent();
    }
}
