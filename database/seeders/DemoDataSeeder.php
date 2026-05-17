<?php

namespace Database\Seeders;

use App\Actions\CreateTenantAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/menu-data.php');

        foreach ($data as $slug => $shop) {
            $this->seedShop($slug, $shop);
        }
    }

    private function seedShop(string $slug, array $shop): void
    {
        // Drop any leftover tenant DB so this seeder is idempotent across migrate:fresh runs.
        DB::statement('DROP DATABASE IF EXISTS `'.config('tenancy.database.prefix').$slug.'`');

        $owner = User::firstOrCreate(
            ['email' => $shop['owner']['email']],
            [
                'name' => $shop['owner']['name'],
                'phone' => $shop['owner']['phone'],
                'password' => bcrypt($shop['owner']['password']),
                'global_role' => 'shop_owner',
                'status' => 'active',
            ],
        );

        Tenant::where('slug', $slug)->delete();

        $tenant = app(CreateTenantAction::class)->execute(
            $owner,
            $shop['name'],
            $slug,
        );

        $tenant->run(function () use ($tenant, $shop) {
            $this->updateShopProfile($shop);
            $this->seedShopSettings();
            $tableIds = $this->seedTables($shop);
            $categoryIds = $this->seedCategories($shop);
            $itemIds = $this->seedItems($tenant, $shop, $categoryIds);
            $this->seedCollections($shop, $itemIds);

            unset($tableIds);
        });

        $this->command?->info("seeded: {$slug}");
    }

    private function updateShopProfile(array $shop): void
    {
        DB::table('profile')->update([
            'phone' => $shop['phone'],
            'address' => $shop['address'],
            'currency' => $shop['currency'],
            'service_charge_rate' => $shop['service_charge_rate'],
            'tax_rate' => $shop['tax_rate'],
            'opening_hours' => $shop['opening_hours'],
            'updated_at' => now(),
        ]);
    }

    private function seedShopSettings(): void
    {
        DB::table('settings')->update([
            'payment_timing' => 'pay_after_meal',
            'allow_guest_order' => true,
            'allow_member_self_checkout' => true,
            'allow_cashier_checkout' => true,
            'allow_pay_after_meal' => true,
            'points_enabled' => true,
            'earn_rate_amount' => 1000,
            'earn_rate_points' => 1,
            'redeem_rate_points' => 1,
            'redeem_rate_amount' => 100,
            'updated_at' => now(),
        ]);
    }

    private function seedTables(array $shop): array
    {
        $ids = [];
        foreach ($shop['tables'] as $t) {
            $id = (string) Str::uuid();
            $ids[$t['number']] = $id;
            DB::table('tables')->insert([
                'id' => $id,
                'table_number' => $t['number'],
                'table_name' => $t['name'],
                'qr_token' => 'tbl_'.Str::lower(Str::random(10)),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $ids;
    }

    private function seedCategories(array $shop): array
    {
        $ids = [];
        foreach ($shop['categories'] as $sort => $cat) {
            $id = (string) Str::uuid();
            $ids[$cat['key']] = $id;
            DB::table('menu_categories')->insert([
                'id' => $id,
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'sort_order' => $sort + 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $ids;
    }

    private function seedItems(Tenant $tenant, array $shop, array $categoryIds): array
    {
        $ids = [];
        foreach ($shop['items'] as $item) {
            $id = (string) Str::uuid();
            $ids[$item['slug']] = $id;
            DB::table('menu_items')->insert([
                'id' => $id,
                'menu_category_id' => $categoryIds[$item['category']],
                'name' => $item['name'],
                'slug' => $item['slug'],
                'description' => $item['description'] ?? null,
                'price' => $item['price'],
                'currency' => $shop['currency'],
                'image_url' => rtrim(config('app.url'), '/').'/photos/'.$tenant->slug.'/'.$item['slug'].'.jpg',
                'is_available' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $ids;
    }

    private function seedCollections(array $shop, array $itemIds): void
    {
        foreach ($shop['collections'] as $col) {
            $colId = (string) Str::uuid();
            DB::table('menu_collections')->insert([
                'id' => $colId,
                'name' => $col['name'],
                'slug' => Str::slug($col['name']),
                'description' => null,
                'layout_type' => $col['layout_type'],
                'display_order' => $col['display_order'],
                'status' => $col['status'],
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($col['items'] as $sort => $itemSlug) {
                if (! isset($itemIds[$itemSlug])) {
                    continue;
                }
                DB::table('menu_collection_items')->insert([
                    'id' => (string) Str::uuid(),
                    'menu_collection_id' => $colId,
                    'menu_item_id' => $itemIds[$itemSlug],
                    'sort_order' => $sort + 1,
                    'is_featured' => $sort === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
