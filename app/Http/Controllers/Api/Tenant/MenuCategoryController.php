<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('menu_categories')->orderBy('sort_order')->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $slug = $this->uniqueSlug(Str::slug($data['name']));

        $id = (string) Str::uuid();
        DB::table('menu_categories')->insert([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('menu_categories')->where('id', $id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_categories')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_categories')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $id);
        }

        DB::table('menu_categories')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('menu_categories')->where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_categories')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Category not found.'], 404);
        }

        DB::table('menu_categories')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'string', 'exists:menu_categories,id'],
            'order.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($data['order'] as $item) {
            DB::table('menu_categories')
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order'], 'updated_at' => now()]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    private function uniqueSlug(string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (DB::table('menu_categories')->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
