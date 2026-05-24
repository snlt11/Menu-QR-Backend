<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\AttachCollectionItemRequest;
use App\Http\Requests\Api\Tenant\ReorderRequest;
use App\Http\Requests\Api\Tenant\StoreMenuCollectionRequest;
use App\Http\Requests\Api\Tenant\UpdateMenuCollectionRequest;
use App\Models\MenuCollection;
use App\Models\MenuCollectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuCollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = MenuCollection::orderBy('display_order')->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(StoreMenuCollectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['layout_type'] = $this->normalizeLayout($data['layout_type']);
        $slug = $this->uniqueSlug(Str::slug($data['name']));

        $collection = MenuCollection::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'layout_type' => $data['layout_type'],
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 'draft',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return response()->json([
            'status' => 201,
            'data' => MenuCollection::where('id', $collection->id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $collection = MenuCollection::where('id', $id)->first();
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

    public function update(UpdateMenuCollectionRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuCollection::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $data = $request->validated();

        if (isset($data['layout_type'])) {
            $data['layout_type'] = $this->normalizeLayout($data['layout_type']);
        }

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['name']), $id);
        }

        $row->update($data);

        return response()->json([
            'status' => 200,
            'data' => MenuCollection::where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuCollection::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $data = $request->validated();

        foreach ($data['order'] as $item) {
            MenuCollection::where('id', $item['id'])->update([
                'display_order' => $item['display_order'],
            ]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    public function attachItem(AttachCollectionItemRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $collection = MenuCollection::where('id', $id)->first();
        if (! $collection) {
            return response()->json(['status' => 404, 'message' => 'Collection not found.'], 404);
        }

        $data = $request->validated();

        $exists = MenuCollectionItem::where('menu_collection_id', $id)
            ->where('menu_item_id', $data['menu_item_id'])
            ->exists();
        if ($exists) {
            return response()->json(['status' => 409, 'message' => 'Item already attached to this collection.'], 409);
        }

        $pivot = MenuCollectionItem::create([
            'menu_collection_id' => $id,
            'menu_item_id' => $data['menu_item_id'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        return response()->json([
            'status' => 201,
            'data' => MenuCollectionItem::where('id', $pivot->id)->first(),
        ], 201);
    }

    public function detachItem(string $tenant_slug, string $id, string $itemId): JsonResponse
    {
        $row = MenuCollectionItem::where('menu_collection_id', $id)->where('id', $itemId)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Pivot row not found.'], 404);
        }

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $itemId]]);
    }

    public function reorderItems(ReorderRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $data = $request->validated();

        foreach ($data['order'] as $item) {
            MenuCollectionItem::where('id', $item['id'])
                ->where('menu_collection_id', $id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 200, 'data' => $data['order']]);
    }

    private function normalizeLayout(string $layout): string
    {
        return match ($layout) {
            'horizontal_cards' => 'horizontal_scroll',
            'large_featured_cards' => 'large_featured',
            'grid_cards', 'horizontal_scroll', 'large_featured', 'compact_list', 'split_feature', 'mini_cards' => $layout,
            default => 'grid_cards',
        };
    }

    private function uniqueSlug(string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (MenuCollection::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
