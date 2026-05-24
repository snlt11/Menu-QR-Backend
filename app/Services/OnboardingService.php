<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantOnboardingKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    private function generatePlainKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $key = '';

        for ($i = 0; $i < 8; $i++) {
            $key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $key;
    }

    public function generateKey(Tenant $tenant): string
    {
        TenantOnboardingKey::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        $plainKey = $this->generatePlainKey();

        TenantOnboardingKey::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'key_hash' => Hash::make($plainKey),
            'status' => 'active',
        ]);

        $tenant->update(['onboarding_status' => 'pending']);

        return $plainKey;
    }

    public function verifyKey(string $key): Tenant
    {
        $normalized = strtoupper(trim($key));

        $onboardingKey = TenantOnboardingKey::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->first(function (TenantOnboardingKey $ok) use ($normalized) {
                return Hash::check($normalized, $ok->key_hash);
            });

        if (! $onboardingKey) {
            throw ValidationException::withMessages([
                'onboarding_key' => ['Invalid or expired onboarding key.'],
            ]);
        }

        if ($onboardingKey->expires_at && $onboardingKey->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'onboarding_key' => ['This onboarding key has expired.'],
            ]);
        }

        $onboardingKey->update(['last_used_at' => now()]);

        return $onboardingKey->tenant;
    }

    public function completeOnboarding(string $key, string $password): Tenant
    {
        $normalized = strtoupper(trim($key));

        $onboardingKey = TenantOnboardingKey::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->first(function (TenantOnboardingKey $ok) use ($normalized) {
                return Hash::check($normalized, $ok->key_hash);
            });

        if (! $onboardingKey) {
            throw ValidationException::withMessages([
                'onboarding_key' => ['Invalid or expired onboarding key.'],
            ]);
        }

        if ($onboardingKey->expires_at && $onboardingKey->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'onboarding_key' => ['This onboarding key has expired.'],
            ]);
        }

        $tenant = $onboardingKey->tenant;

        $tenant->run(function () use ($password) {
            $updated = DB::table('users')
                ->where('role', 'owner')
                ->update([
                    'password' => Hash::make($password),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw ValidationException::withMessages([
                    'onboarding_key' => ['Owner account was not found. Please contact platform admin.'],
                ]);
            }
        });

        $onboardingKey->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $tenant->update([
            'onboarding_status' => 'completed',
            'onboarded_at' => now(),
        ]);

        return $tenant;
    }
}
