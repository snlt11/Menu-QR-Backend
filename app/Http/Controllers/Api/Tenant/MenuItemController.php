<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('menu_items')
            ->when($request->filled('category'), fn ($q) => $q->where('menu_category_id', $request->string('category')))
            ->orderByDesc('created_at')
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
            'image' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'is_available' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $slug = $this->uniqueSlug($data['menu_category_id'], Str::slug($data['name']));

        $imageUrl = $data['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->uploadImage($request->file('image'), $slug);
        }

        $id = (string) Str::uuid();
        DB::table('menu_items')->insert([
            'id' => $id,
            'menu_category_id' => $data['menu_category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'MMK',
            'image_url' => $imageUrl,
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
            'image' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'is_available' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        if (isset($data['name']) && $data['name'] !== $row->name) {
            $categoryId = $data['menu_category_id'] ?? $row->menu_category_id;
            $data['slug'] = $this->uniqueSlug($categoryId, Str::slug($data['name']), $id);
        }

        if ($request->hasFile('image')) {
            $slugForFile = $data['slug'] ?? $row->slug;
            $data['image_url'] = $this->uploadImage($request->file('image'), $slugForFile);
        }

        // The `image` field is a file upload, not a DB column — strip it before update.
        unset($data['image']);

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

    /**
     * Store an uploaded image on the s3 disk (MinIO in dev) and return the public URL.
     */
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
