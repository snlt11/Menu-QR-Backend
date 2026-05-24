<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Central\StoreTenantRequestRequest;
use App\Models\Tenant;
use App\Models\TenantRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantRequestController extends Controller
{
    public function store(StoreTenantRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->normalizeSlug($data['shop_name']);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'shop_name' => ['Shop name must contain at least one letter or number.'],
            ]);
        }

        if (Tenant::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'shop_name' => ['This shop is already registered. Please sign in instead.'],
            ]);
        }

        $existingActiveRequest = TenantRequest::where('requested_slug', $slug)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingActiveRequest) {
            throw ValidationException::withMessages([
                'shop_name' => ['A registration request for this shop name already exists.'],
            ]);
        }

        TenantRequest::create([
            'shop_name' => $data['shop_name'],
            'requested_slug' => $slug,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Registration request submitted. We will review your request soon.',
        ], 201);
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $slug = $request->query('slug');
        $available = ! Tenant::where('slug', $slug)->exists()
            && ! TenantRequest::where('requested_slug', $slug)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

        return response()->json(['available' => $available]);
    }

    private function normalizeSlug(string $input): string
    {
        $slug = Str::lower(trim($input));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = preg_replace('/^-+|-+$/', '', $slug);

        return $slug ?? '';
    }
}
