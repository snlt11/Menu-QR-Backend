<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function __invoke(string $tenant_slug, string $qr_token): JsonResponse
    {
        $table = DB::table('shop_tables')
            ->where('qr_token', $qr_token)
            ->where('status', 'active')
            ->first();

        if (! $table) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $profile = DB::table('shop_profile')->first();

        $now = now();
        $collections = DB::table('menu_collections')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('display_order')
            ->get();

        $collectionItems = DB::table('menu_collection_items as mci')
            ->join('menu_items as mi', 'mi.id', '=', 'mci.menu_item_id')
            ->whereIn('mci.menu_collection_id', $collections->pluck('id'))
            ->where('mi.status', 'active')
            ->where('mi.is_available', true)
            ->orderBy('mci.sort_order')
            ->select('mci.menu_collection_id', 'mci.sort_order', 'mci.is_featured', 'mi.*')
            ->get()
            ->groupBy('menu_collection_id');

        $categories = DB::table('menu_categories')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $items = DB::table('menu_items')
            ->where('status', 'active')
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
