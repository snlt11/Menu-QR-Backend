<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'description' => 'For restaurants testing Menu QR.',
                'price' => 0,
                'currency' => 'MMK',
                'billing_cycle' => 'trial',
                'trial_days' => 14,
                'max_owners' => 1,
                'max_staff' => 1,
                'max_kitchen' => 1,
                'features' => [
                    '1 owner account',
                    '1 staff account',
                    '1 kitchen account',
                    'QR menu',
                    'Table ordering',
                    'Basic order management',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For restaurants ready to use Menu QR daily.',
                'price' => 50000,
                'currency' => 'MMK',
                'billing_cycle' => 'monthly',
                'trial_days' => null,
                'max_owners' => null,
                'max_staff' => null,
                'max_kitchen' => null,
                'features' => [
                    'More staff access',
                    'Kitchen screen',
                    'Customer/order management',
                    'Loyalty points',
                    'Reports',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Custom',
                'slug' => 'custom',
                'description' => 'For multi-branch restaurants or custom workflows.',
                'price' => null,
                'currency' => 'MMK',
                'billing_cycle' => 'custom',
                'trial_days' => null,
                'max_owners' => null,
                'max_staff' => null,
                'max_kitchen' => null,
                'features' => [
                    'Multi-branch support',
                    'Custom setup',
                    'Advanced support',
                    'Custom integrations',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }

        $this->command?->info('Seeded '.count($plans).' plans.');
    }
}
