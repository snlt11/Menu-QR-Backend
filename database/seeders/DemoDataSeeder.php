<?php

namespace Database\Seeders;

use App\Actions\CreateTenantAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultAdmin();

        $data = require database_path('seeders/data/menu-data.php');

        foreach ($data as $slug => $shop) {
            $this->seedShop($slug, $shop);
        }
    }

    private function seedDefaultAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@menuqr.asia'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('554433221100'),
                'global_role' => 'admin',
                'status' => 'active',
            ],
        );
        $this->command?->info('seeded admin: admin@menuqr.asia / 554433221100');
    }

    private function seedShop(string $slug, array $shop): void
    {
        // Drop any leftover tenant DB so this seeder is idempotent across migrate:fresh runs.
        DB::statement('DROP DATABASE IF EXISTS `'.config('tenancy.database.prefix').$slug.'`');

        Tenant::where('slug', $slug)->delete();

        $tenant = app(CreateTenantAction::class)->execute(
            $shop['name'],
            $slug,
            [
                'name' => $shop['owner']['name'],
                'email' => $shop['owner']['email'],
                'phone' => $shop['owner']['phone'],
                'password' => $shop['owner']['password'],
            ],
        );

        $tenant->run(function () use ($tenant, $shop) {
            $this->updateShopProfile($shop);
            $this->seedShopSettings();
            $tableIds = $this->seedTables($shop);
            $categoryIds = $this->seedCategories($shop);
            $itemIds = $this->seedItems($tenant, $shop, $categoryIds);
            $this->seedCollections($shop, $itemIds);
            $customerIds = $this->seedCustomers($shop);
            $this->seedOrders($shop, $tableIds, $itemIds, $customerIds);
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
                'public_code' => 'tbl_'.Str::lower(Str::random(10)),
                'ordering_enabled' => true,
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
                'is_available' => $item['is_available'] ?? true,
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
            $window = $this->resolveCollectionWindow($col['time_window'] ?? null);

            DB::table('menu_collections')->insert([
                'id' => $colId,
                'name' => $col['name'],
                'slug' => Str::slug($col['name']),
                'description' => null,
                'layout_type' => $col['layout_type'],
                'display_order' => $col['display_order'],
                'status' => $col['status'],
                'starts_at' => $window['starts_at'],
                'ends_at' => $window['ends_at'],
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

    /**
     * @return array{starts_at: ?string, ends_at: ?string}
     */
    private function resolveCollectionWindow(?string $window): array
    {
        if ($window === 'today') {
            return [
                'starts_at' => now()->startOfDay()->toDateTimeString(),
                'ends_at' => now()->endOfDay()->toDateTimeString(),
            ];
        }

        return ['starts_at' => null, 'ends_at' => null];
    }

    /**
     * @return array<string, string> map of customer email → customer UUID
     */
    private function seedCustomers(array $shop): array
    {
        $ids = [];
        foreach ($shop['customers'] ?? [] as $c) {
            $customerId = (string) Str::uuid();
            $ids[$c['email']] = $customerId;

            DB::table('customers')->insert([
                'id' => $customerId,
                'name' => $c['name'],
                'email' => $c['email'],
                'phone' => $c['phone'] ?? null,
                'password' => Hash::make('password'),
                'status' => 'active',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]);

            DB::table('customer_profiles')->insert([
                'id' => (string) Str::uuid(),
                'customer_id' => $customerId,
                'name' => $c['name'],
                'phone' => $c['phone'] ?? null,
                'email' => $c['email'],
                'total_points' => $c['points'] ?? 0,
                'membership_level' => 'basic',
                'created_at' => now()->subDays(7),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function seedOrders(array $shop, array $tableIds, array $itemIds, array $customerIds): void
    {
        $orders = $shop['orders'] ?? [];
        if (! $orders) {
            return;
        }

        $serviceRate = (float) ($shop['service_charge_rate'] ?? 0);
        $taxRate = (float) ($shop['tax_rate'] ?? 0);
        // Earn/redeem rates match seedShopSettings() defaults.
        $earnRateAmount = 1000;
        $redeemRateAmount = 100;

        $itemPriceLookup = collect($shop['items'])->keyBy('slug');
        $datePart = now()->format('Ymd');

        foreach ($orders as $i => $o) {
            $createdAt = now()->subMinutes($o['minutes_ago']);
            $orderId = (string) Str::uuid();
            $orderNumber = "ORD-{$datePart}-".str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $tableId = $tableIds[$o['table']] ?? null;
            $customerEmail = $o['customer'] ?? null;
            $customerId = $customerEmail ? ($customerIds[$customerEmail] ?? null) : null;
            $isMember = $customerId !== null;
            $customerType = $isMember ? 'member' : 'guest';

            // Compute line items + subtotal from menu_items prices.
            $lines = [];
            $subtotal = 0.0;
            foreach ($o['items'] as $line) {
                [$slug, $qty] = $line;
                $itemRow = $itemPriceLookup->get($slug);
                if (! $itemRow || ! isset($itemIds[$slug])) {
                    continue;
                }
                $unit = (float) $itemRow['price'];
                $lineSubtotal = $unit * $qty;
                $subtotal += $lineSubtotal;
                $lines[] = [
                    'menu_item_id' => $itemIds[$slug],
                    'snapshot_name' => $itemRow['name'],
                    'snapshot_price' => $unit,
                    'quantity' => $qty,
                    'subtotal_amount' => $lineSubtotal,
                ];
            }
            if (! $lines) {
                continue;
            }

            $serviceCharge = round($subtotal * $serviceRate / 100, 2);
            $tax = round($subtotal * $taxRate / 100, 2);
            $grossTotal = round($subtotal + $serviceCharge + $tax, 2);

            $redeemedPoints = (int) ($o['redeemed_points'] ?? 0);
            $pointDiscount = $redeemedPoints * $redeemRateAmount;
            $payable = max(0.0, $grossTotal - $pointDiscount);

            $paid = ($o['payment_status'] ?? 'unpaid') === 'paid';
            $earnedPoints = ($paid && $isMember && $earnRateAmount > 0)
                ? (int) floor($payable / $earnRateAmount)
                : 0;

            $checkoutType = $isMember ? 'self_checkout' : 'cashier_checkout';

            DB::table('orders')->insert([
                'id' => $orderId,
                'order_number' => $orderNumber,
                'table_id' => $tableId,
                'customer_id' => $customerId,
                'customer_type' => $customerType,
                'checkout_type' => $checkoutType,
                'payment_timing' => 'pay_after_meal',
                'status' => $o['status'],
                'payment_status' => $o['payment_status'] ?? 'unpaid',
                'subtotal_amount' => $subtotal,
                'service_charge_amount' => $serviceCharge,
                'tax_amount' => $tax,
                'gross_total_amount' => $grossTotal,
                'redeemed_points' => $redeemedPoints,
                'point_discount_amount' => $pointDiscount,
                'payable_amount' => $payable,
                'earned_points' => $earnedPoints,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'menu_item_id' => $line['menu_item_id'],
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_price' => $line['snapshot_price'],
                    'quantity' => $line['quantity'],
                    'subtotal_amount' => $line['subtotal_amount'],
                    'instruction' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            if ($paid) {
                $this->seedPaymentForOrder($orderId, $orderNumber, $payable, $createdAt, $isMember);
                if ($isMember) {
                    $this->seedLoyaltyForOrder($customerId, $orderId, $orderNumber, $redeemedPoints, $earnedPoints, $createdAt);
                }
            }
        }
    }

    private function seedPaymentForOrder(string $orderId, string $orderNumber, float $amount, Carbon $at, bool $isMember): void
    {
        $paymentId = (string) Str::uuid();
        DB::table('payments')->insert([
            'id' => $paymentId,
            'order_id' => $orderId,
            'method' => 'qr_payment',
            'provider' => 'demo_qr',
            'status' => 'paid',
            'amount' => $amount,
            'initiated_by' => $isMember ? 'customer' : 'cashier',
            'shown_on' => $isMember ? 'customer_phone' : 'cashier_screen',
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        DB::table('payment_sessions')->insert([
            'id' => (string) Str::uuid(),
            'payment_id' => $paymentId,
            'provider_reference' => 'QR-'.strtoupper(Str::random(10)),
            'qr_payload' => "PAY|ORDER={$orderNumber}|AMOUNT={$amount}",
            'status' => 'paid',
            'expires_at' => $at->copy()->addMinutes(15),
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        DB::table('payment_status_histories')->insert([
            'id' => (string) Str::uuid(),
            'payment_id' => $paymentId,
            'old_status' => null,
            'new_status' => 'pending',
            'provider_status' => 'PENDING',
            'message' => 'Payment session created',
            'created_at' => $at,
        ]);

        DB::table('payment_status_histories')->insert([
            'id' => (string) Str::uuid(),
            'payment_id' => $paymentId,
            'old_status' => 'pending',
            'new_status' => 'paid',
            'provider_status' => 'SUCCESS',
            'message' => 'Payment confirmed successfully',
            'created_at' => $at,
        ]);
    }

    private function seedLoyaltyForOrder(string $customerId, string $orderId, string $orderNumber, int $redeemed, int $earned, Carbon $at): void
    {
        if ($redeemed > 0) {
            DB::table('loyalty_point_transactions')->insert([
                'id' => (string) Str::uuid(),
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'type' => 'redeem',
                'points' => -$redeemed,
                'description' => "Used {$redeemed} points for order {$orderNumber}",
                'created_at' => $at,
            ]);
            DB::table('customer_profiles')
                ->where('customer_id', $customerId)
                ->decrement('total_points', $redeemed);
        }
        if ($earned > 0) {
            DB::table('loyalty_point_transactions')->insert([
                'id' => (string) Str::uuid(),
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'type' => 'earn',
                'points' => $earned,
                'description' => "Earned {$earned} points from order {$orderNumber}",
                'created_at' => $at,
            ]);
            DB::table('customer_profiles')
                ->where('customer_id', $customerId)
                ->increment('total_points', $earned);
        }
    }
}
