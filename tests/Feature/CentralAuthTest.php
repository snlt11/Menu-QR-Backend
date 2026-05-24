<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = makeDemoShop();
});

afterEach(function () {
    try {
        if (isset($this->tenant) && $this->tenant) {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        }
    } catch (Throwable $e) {
    }
});

test('admin can create a tenant + owner row inside the new tenant DB', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@menuqr.asia', 'password' => bcrypt('554433221100'),
        'global_role' => 'admin', 'status' => 'active',
    ]);
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    // Only admin row exists in central before the call.
    $adminCount = User::where('global_role', 'admin')->count();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/tenants', [
            'name' => 'Mingalar BBQ',
            'slug' => 'mingalar-bbq',
            'owner_name' => 'Ko Min',
            'owner_email' => 'min@example.com',
            'password' => 'password',
        ])->assertStatus(201)
        ->assertJsonPath('data.slug', 'mingalar-bbq')
        ->assertJsonPath('data.login_url', '/t/mingalar-bbq/login');

    // No new row written to central.users — owner lives only inside tenant.users.
    expect(User::where('global_role', 'admin')->count())->toBe($adminCount);
    expect(User::where('email', 'min@example.com')->exists())->toBeFalse();

    $newTenant = Tenant::where('slug', 'mingalar-bbq')->first();
    $ownerRow = $newTenant->run(fn () => DB::table('users')->where('email', 'min@example.com')->first());
    expect($ownerRow->role)->toBe('owner');

    $newTenant->database()->manager()->deleteDatabase($newTenant);
});

test('non-admin token cannot create a tenant', function () {
    $token = ownerLogin($this); // tenant owner token

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/tenants', [
            'name' => 'Sneaky',
            'slug' => 'sneaky',
            'owner_name' => 'X',
            'owner_email' => 'x@example.com',
            'password' => 'password',
        ])->assertStatus(401);
});

test('admin tenant create rejects a duplicate slug', function () {
    $admin = User::create([
        'name' => 'Admin', 'email' => 'admin@menuqr.asia', 'password' => bcrypt('554433221100'),
        'global_role' => 'admin', 'status' => 'active',
    ]);
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/admin/tenants', [
            'name' => 'Other Shop',
            'slug' => 'shophouse',
            'owner_name' => 'Foo',
            'owner_email' => 'foo@example.com',
            'password' => 'password',
        ])->assertStatus(422);
});

test('admin login succeeds and returns a token', function () {
    User::create([
        'name' => 'Admin', 'email' => 'admin@menuqr.asia', 'password' => bcrypt('554433221100'),
        'global_role' => 'admin', 'status' => 'active',
    ]);

    $res = $this->postJson('/api/admin/login', [
        'email' => 'admin@menuqr.asia',
        'password' => '554433221100',
    ])->assertOk();

    expect($res['data']['user']['global_role'])->toBe('admin');
    expect($res['data']['token'])->toBeString();
});

test('resolve returns tenant for an existing slug', function () {
    $this->getJson('/api/tenants/resolve?slug=shophouse')
        ->assertOk()
        ->assertJsonPath('data.tenant.slug', 'shophouse')
        ->assertJsonPath('data.login_url', '/t/shophouse/login');
});

test('resolve returns 404 for an unknown slug', function () {
    $this->getJson('/api/tenants/resolve?slug=nope')
        ->assertStatus(404);
});

test('login succeeds for the seeded owner', function () {
    $res = $this->postJson('/api/t/shophouse/login', [
        'email' => 'koaung@example.com',
        'password' => 'password',
    ])->assertOk();

    expect($res['data']['user']['role'])->toBe('owner');
    expect($res['data']['redirect_url'])->toBe('/t/shophouse/dashboard');
});

test('login fails on wrong password', function () {
    $this->postJson('/api/t/shophouse/login', [
        'email' => 'koaung@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);
});

test('login fails on wrong tenant slug', function () {
    $this->postJson('/api/t/nope/login', [
        'email' => 'koaung@example.com',
        'password' => 'password',
    ])->assertStatus(404);
});
