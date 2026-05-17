<?php

use App\Actions\CreateTenantAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    DB::statement('DROP DATABASE IF EXISTS `tenant_shophouse`');

    $this->owner = User::create([
        'name' => 'Ko Aung',
        'email' => 'koaung@example.com',
        'password' => bcrypt('password'),
        'global_role' => 'shop_owner',
    ]);

    $this->tenant = app(CreateTenantAction::class)
        ->execute($this->owner, 'Shwe Food House', 'shophouse');

    $this->menuItemId = null;
    $this->qrToken = 'tbl_test_'.Str::random(6);

    $token = $this->qrToken;
    $this->tenant->run(function () use ($token, &$menuItemId) {
        $catId = (string) Str::uuid();
        DB::table('menu_categories')->insert([
            'id' => $catId, 'name' => 'Rice', 'slug' => 'rice', 'sort_order' => 1, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $itemId = (string) Str::uuid();
        DB::table('menu_items')->insert([
            'id' => $itemId, 'menu_category_id' => $catId,
            'name' => 'Chicken Fried Rice', 'slug' => 'chicken-fried-rice',
            'price' => 8500, 'currency' => 'MMK',
            'is_available' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tables')->insert([
            'id' => (string) Str::uuid(),
            'table_number' => 'A1', 'table_name' => 'Table A1',
            'qr_token' => $token, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuItemId = $itemId;
    });

    // Re-read the menu item id from the tenant DB after running the seed callback.
    $this->menuItemId = $this->tenant->run(function () {
        return DB::table('menu_items')->where('slug', 'chicken-fried-rice')->value('id');
    });
});

afterEach(function () {
    try {
        if (isset($this->tenant) && $this->tenant) {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        }
    } catch (\Throwable $e) {
    }
});

test('guest can view the menu by QR token', function () {
    $res = $this->getJson("/api/s/shophouse/table/{$this->qrToken}/menu")->assertOk();

    expect($res['data']['shop']['slug'])->toBe('shophouse');
    expect($res['data']['table']['qr_token'])->toBe($this->qrToken);
    expect($res['data']['categories'][0]['items'][0]['name'])->toBe('Chicken Fried Rice');
});

test('guest can create an order with snapshot prices and then pay via demo QR', function () {
    // 1. Create order
    $createRes = $this->postJson("/api/s/shophouse/table/{$this->qrToken}/orders", [
        'items' => [
            ['menu_item_id' => $this->menuItemId, 'quantity' => 2, 'instruction' => 'less oil'],
        ],
    ])->assertStatus(201);

    $orderId = $createRes['data']['order']['id'];
    expect((float) $createRes['data']['order']['subtotal_amount'])->toBe(17000.0);
    expect($createRes['data']['order']['customer_type'])->toBe('guest');
    expect($createRes['data']['order']['payment_status'])->toBe('unpaid');
    expect($createRes['data']['items'][0]['snapshot_name'])->toBe('Chicken Fried Rice');

    // 2. Start payment session
    $payRes = $this->postJson("/api/s/shophouse/orders/{$orderId}/payments", [
        'method' => 'qr_payment',
        'shown_on' => 'customer_phone',
    ])->assertStatus(201);

    $sessionId = $payRes['data']['session']['id'];
    expect($payRes['data']['session']['qr_payload'])->toContain('PAY|ORDER=');

    // 3. Confirm demo payment
    $this->postJson("/api/s/shophouse/payment-sessions/{$sessionId}/confirm-demo")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.status', 'completed');

    // 4. Status endpoint reflects paid + completed
    $this->getJson("/api/s/shophouse/orders/{$orderId}/status")
        ->assertOk()
        ->assertJsonPath('data.order.payment_status', 'paid')
        ->assertJsonPath('data.order.status', 'completed');
});

test('order is rejected when no item is valid', function () {
    $this->postJson("/api/s/shophouse/table/{$this->qrToken}/orders", [
        'items' => [
            ['menu_item_id' => (string) Str::uuid(), 'quantity' => 1],
        ],
    ])->assertStatus(422);
});

test('order is rejected for a wrong qr token', function () {
    $this->postJson('/api/s/shophouse/table/tbl_nope/orders', [
        'items' => [
            ['menu_item_id' => $this->menuItemId, 'quantity' => 1],
        ],
    ])->assertStatus(404);
});
