<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\StoreMenuItemRequest;
use App\Http\Requests\Api\Tenant\UpdateMenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
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

        $imageUrl = $data['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadImage($request->file('image'), $slug);
        }

        $item = MenuItem::create([
            'menu_category_id' => $data['menu_category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'MMK',
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

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $categoryId = $data['menu_category_id'] ?? $row->menu_category_id;
            $data['slug'] = $this->uniqueSlug($categoryId, Str::slug($data['name']), $id);
        }

        if ($request->hasFile('image')) {
            $slugForFile = $data['slug'] ?? $row->slug;
            $data['image_url'] = $this->uploadImage($request->file('image'), $slugForFile);
        }

        unset($data['image']);

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

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    private function uploadImage(UploadedFile $file, string $slug): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = 'menu-items/'.tenant('slug').'/'.$slug.'-'.Str::lower(Str::random(8)).'.'.$ext;

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType() ?: 'image/jpeg',
        ]);

        return Storage::disk('s3')->url($path);
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
