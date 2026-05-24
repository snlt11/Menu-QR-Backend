<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\ReorderRequest;
use App\Http\Requests\Api\Tenant\StoreMenuCategoryRequest;
use App\Http\Requests\Api\Tenant\UpdateMenuCategoryRequest;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = MenuCategory::orderBy('sort_order')->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug(Str::slug($data['name']));

        $category = MenuCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 201,
            'data' => MenuCategory::where('id', $category->id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuCategory::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(UpdateMenuCategoryRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuCategory::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $id);
        }

        $row->update($data);

        return response()->json([
            'status' => 200,
            'data' => MenuCategory::where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuCategory::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $data = $request->validated();

        foreach ($data['order'] as $item) {
            MenuCategory::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    private function uniqueSlug(string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (MenuCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
