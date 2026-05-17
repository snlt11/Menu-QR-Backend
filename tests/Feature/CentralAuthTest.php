<?php

use App\Actions\CreateTenantAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
});

afterEach(function () {
    // Drop the spun-up tenant DB after each test.
    try {
        if (isset($this->tenant) && $this->tenant) {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        }
    } catch (\Throwable $e) {
        // best-effort cleanup
    }
});

test('register issues a Sanctum token', function () {
    $res = $this->postJson('/api/register', [
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => 'password',
        'global_role' => 'customer',
    ]);

    $res->assertStatus(201)
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);
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

    expect($res['data']['shop_user']['role'])->toBe('owner');
    expect($res['data']['redirect_url'])->toBe('/t/shophouse/dashboard');
});

test('login fails for a user without shop access', function () {
    User::create([
        'name' => 'Rando',
        'email' => 'rando@example.com',
        'password' => bcrypt('password'),
        'global_role' => 'customer',
    ]);

    $this->postJson('/api/t/shophouse/login', [
        'email' => 'rando@example.com',
        'password' => 'password',
    ])->assertStatus(403);
});

test('login fails on wrong tenant slug', function () {
    $this->postJson('/api/t/nope/login', [
        'email' => 'koaung@example.com',
        'password' => 'password',
    ])->assertStatus(404);
});
