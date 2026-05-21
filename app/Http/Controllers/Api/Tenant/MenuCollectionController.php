<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuCollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('menu_collections')->orderBy('display_order')->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'layout_type' => ['required', 'string', 'in:horizontal_cards,grid_cards,large_featured_cards,compact_list'],
            'display_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,expired'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $slug = $this->uniqueSlug(Str::slug($data['name']));

        $id = (string) Str::uuid();
        DB::table('menu_collections')->insert([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'layout_type' => $data['layout_type'],
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 'draft',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('menu_collections')->where('id', $id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $collection = DB::table('menu_collections')->where('id', $id)->first();
        if (! $collection) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $items = DB::table('menu_collection_items as mci')
            ->join('menu_items as mi', 'mi.id', '=', 'mci.menu_item_id')
            ->where('mci.menu_collection_id', $id)
            ->orderBy('mci.sort_order')
            ->select(
                'mci.id',
                'mci.menu_item_id',
                'mci.sort_order',
                'mci.is_featured',
                'mi.name as menu_item_name',
                'mi.price as menu_item_price',
                'mi.currency as menu_item_currency',
                'mi.status as menu_item_status',
                'mi.is_available as menu_item_is_available',
                'mi.image_url as menu_item_image_url',
            )
            ->get();

        return response()->json([
            'status' => 200,
            'data' => [
                'collection' => $collection,
                'items' => $items,
            ],
        ]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_collections')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'layout_type' => ['sometimes', 'string', 'in:horizontal_cards,grid_cards,large_featured_cards,compact_list'],
            'display_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,expired'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $id);
        }

        DB::table('menu_collections')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('menu_collections')->where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('menu_collections')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        DB::table('menu_collections')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'string', 'exists:menu_collections,id'],
            'order.*.display_order' => ['required', 'integer'],
        ]);

        foreach ($data['order'] as $item) {
            DB::table('menu_collections')
                ->where('id', $item['id'])
                ->update(['display_order' => $item['display_order'], 'updated_at' => now()]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    public function attachItem(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $collection = DB::table('menu_collections')->where('id', $id)->first();
        if (! $collection) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $data = $request->validate([
            'menu_item_id' => ['required', 'string', 'exists:menu_items,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $exists = DB::table('menu_collection_items')
            ->where('menu_collection_id', $id)
            ->where('menu_item_id', $data['menu_item_id'])
            ->exists();
        if ($exists) {
            return response()->json(['status' => 409, 'message' => 'Item already attached to this collection.'], 409);
        }

        $pivotId = (string) Str::uuid();
        DB::table('menu_collection_items')->insert([
            'id' => $pivotId,
            'menu_collection_id' => $id,
            'menu_item_id' => $data['menu_item_id'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_featured' => $data['is_featured'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('menu_collection_items')->where('id', $pivotId)->first(),
        ], 201);
    }

    public function detachItem(string $tenant_slug, string $id, string $itemId): JsonResponse
    {
        $row = DB::table('menu_collection_items')
            ->where('menu_collection_id', $id)
            ->where('id', $itemId)
            ->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Pivot row not found.'], 404);
        }

        DB::table('menu_collection_items')->where('id', $itemId)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $itemId]]);
    }

    public function reorderItems(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'string', 'exists:menu_collection_items,id'],
            'order.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($data['order'] as $item) {
            DB::table('menu_collection_items')
                ->where('id', $item['id'])
                ->where('menu_collection_id', $id)
                ->update(['sort_order' => $item['sort_order'], 'updated_at' => now()]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    private function uniqueSlug(string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (DB::table('menu_collections')->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
