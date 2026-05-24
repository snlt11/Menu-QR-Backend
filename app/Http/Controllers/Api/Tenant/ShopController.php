<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Services\TableAvailabilityHelper;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = tenant();

        $tables = Table::where('status', 'active')
            ->orderBy('table_number')
            ->get(['id', 'table_number', 'table_name', 'qr_token']);

        $enriched = TableAvailabilityHelper::enrichCollection($tables);

        $baseUrl = config('app.frontend_url') ?: config('app.url');
        $base = rtrim($baseUrl, '/').'/s/'.$tenant->slug.'/table/';

        $enriched = collect($enriched)->map(function ($t) use ($base) {
            $t['qr_url'] = $base.$t['qr_token'];

            return $t;
        })->all();

        return response()->json([
            'status' => 200,
            'data' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'tables' => $enriched,
            ],
        ]);
    }
}
