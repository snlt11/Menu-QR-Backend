<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuCollection;
use App\Models\MenuItem;
use App\Models\Profile;
use App\Models\Table;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function __invoke(string $tenant_slug, string $qr_token): JsonResponse
    {
        $table = Table::where(function ($q) use ($qr_token) {
            $q->where('qr_token', $qr_token)
                ->orWhere('public_code', $qr_token);
        })
            ->where('status', 'active')
            ->first();

        if (! $table) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $profile = Profile::first();

        $collections = MenuCollection::where('status', 'active')
            ->orderBy('display_order')
            ->get();

        $collectionItems = MenuItem::query()
            ->join('menu_collection_items as mci', 'mci.menu_item_id', '=', 'menu_items.id')
            ->whereIn('mci.menu_collection_id', $collections->pluck('id'))
            ->where('menu_items.status', 'active')
            ->where('menu_items.is_available', true)
            ->orderBy('mci.sort_order')
            ->select('menu_items.*', 'mci.menu_collection_id', 'mci.sort_order', 'mci.is_featured')
            ->get()
            ->groupBy('menu_collection_id');

        $categories = MenuCategory::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $items = MenuItem::where('status', 'active')
            ->where('is_available', true)
            ->get()
            ->groupBy('menu_category_id');

        return response()->json([
            'status' => 200,
            'data' => [
                'shop' => $profile,
                'table' => $table,
                'collections' => $collections->map(fn ($c) => [
                    'collection' => $c,
                    'items' => $collectionItems->get($c->id, collect())->values(),
                ])->values(),
                'categories' => $categories->map(fn ($c) => [
                    'category' => $c,
                    'items' => $items->get($c->id, collect())->values(),
                ])->values(),
            ],
        ]);
    }
}
