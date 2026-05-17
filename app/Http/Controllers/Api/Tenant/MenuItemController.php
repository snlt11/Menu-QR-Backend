<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('menu_items')
            ->when($request->filled('category'), fn ($q) => $q->where('menu_category_id', $request->string('category')))
            ->orderBy('name')
            ->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_category_id' => ['required', 'string', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'is_available' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $slug = $this->uniqueSlug($data['menu_category_id'], Str::slug($data['name']));

        $id = (string) Str::uuid();
        DB::table('menu_items')->insert([
            'id' => $id,
            'menu_category_id' => $data['menu_category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'MMK',
            'image_url' => $data['image_url'] ?? null,
            'is_available' => $data['is_available'] ?? true,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('menu_items')->where('id', $id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_items')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_items')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        $data = $request->validate([
            'menu_category_id' => ['sometimes', 'string', 'exists:menu_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'is_available' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $categoryId = $data['menu_category_id'] ?? $row->menu_category_id;
            $data['slug'] = $this->uniqueSlug($categoryId, Str::slug($data['name']), $id);
        }

        DB::table('menu_items')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('menu_items')->where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_items')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        DB::table('menu_items')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    private function uniqueSlug(string $categoryId, string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (DB::table('menu_items')
            ->where('menu_category_id', $categoryId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
