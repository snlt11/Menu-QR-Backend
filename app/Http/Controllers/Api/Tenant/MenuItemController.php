<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\StoreMenuItemRequest;
use App\Http\Requests\Api\Tenant\UpdateMenuItemRequest;
use App\Models\MenuItem;
use App\Services\PrivateS3Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function __construct(
        private PrivateS3Service $s3,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = MenuItem::when($request->filled('category'), fn ($q) => $q->where('menu_category_id', $request->string('category')))
            ->orderByDesc('created_at')
            ->orderBy('name')
            ->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug($data['menu_category_id'], Str::slug($data['name']));
        $tenantSlug = tenant('slug');

        $imagePath = null;
        $imageUrl = $data['image_url'] ?? null;

        if ($request->hasFile('image')) {
            $imagePath = $this->s3->uploadPrivateFile(
                $request->file('image'),
                'menu-items',
                $tenantSlug,
            );
        }

        $item = MenuItem::create([
            'menu_category_id' => $data['menu_category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'MMK',
            'image_path' => $imagePath,
            'image_url' => $imageUrl,
            'is_available' => $data['is_available'] ?? true,
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 201,
            'data' => MenuItem::where('id', $item->id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuItem::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(UpdateMenuItemRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuItem::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        $data = $request->validated();
        $tenantSlug = tenant('slug');

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $categoryId = $data['menu_category_id'] ?? $row->menu_category_id;
            $data['slug'] = $this->uniqueSlug($categoryId, Str::slug($data['name']), $id);
        }

        if ($request->hasFile('image')) {
            $oldPath = $row->image_path;
            $newPath = $this->s3->uploadPrivateFile(
                $request->file('image'),
                'menu-items',
                $tenantSlug,
            );

            $data['image_path'] = $newPath;

            $this->s3->deleteIfOwned($oldPath, ["menu-items/{$tenantSlug}/"]);
        }

        if (isset($data['remove_image']) && $data['remove_image']) {
            $oldPath = $row->image_path;
            $data['image_path'] = null;
            $data['image_url'] = null;
            $this->s3->deleteIfOwned($oldPath, ["menu-items/{$tenantSlug}/"]);
        }

        unset($data['image'], $data['remove_image']);

        $row->update($data);

        return response()->json([
            'status' => 200,
            'data' => MenuItem::where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = MenuItem::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Menu item not found.'], 404);
        }

        $tenantSlug = tenant('slug');
        $this->s3->deleteIfOwned($row->image_path, ["menu-items/{$tenantSlug}/"]);

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    private function uniqueSlug(string $categoryId, string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $i = 2;
        while (MenuItem::where('menu_category_id', $categoryId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
