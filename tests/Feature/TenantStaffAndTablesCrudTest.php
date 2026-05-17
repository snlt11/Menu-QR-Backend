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

    $login = $this->postJson('/api/t/shophouse/login', [
        'email' => 'koaung@example.com',
        'password' => 'password',
    ])->assertOk();

    $this->token = $login['data']['token'];
});

afterEach(function () {
    try {
        if (isset($this->tenant) && $this->tenant) {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        }
    } catch (\Throwable $e) {
    }
});

function authed($test)
{
    return $test->withHeader('Authorization', 'Bearer '.test()->token);
}

test('staff create requires an existing central user', function () {
    authed($this)->postJson('/api/t/shophouse/staff', [
        'name' => 'Random Person',
        'email' => 'noone@example.com',
        'role' => 'cashier',
    ])->assertStatus(422)
      ->assertJsonPath('message', 'No central user with that email — register them first.');
});

test('staff create succeeds when the central user exists', function () {
    User::create([
        'name' => 'Ma Hnin',
        'email' => 'hnin@example.com',
        'password' => bcrypt('password'),
        'global_role' => 'staff',
    ]);

    $res = authed($this)->postJson('/api/t/shophouse/staff', [
        'name' => 'Ma Hnin',
        'email' => 'hnin@example.com',
        'role' => 'cashier',
    ])->assertStatus(201);

    expect($res['data']['role'])->toBe('cashier');
    expect($res['data']['status'])->toBe('active');

    authed($this)->getJson('/api/t/shophouse/staff')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('table create auto-generates a qr_token and returns a QR URL', function () {
    $res = authed($this)->postJson('/api/t/shophouse/tables', [
        'table_number' => 'A1',
        'table_name' => 'Table A1',
    ])->assertStatus(201);

    expect($res['data']['qr_token'])->toStartWith('tbl_');
    expect($res['data']['qr_url'])->toContain('/s/shophouse/table/'.$res['data']['qr_token']);
});

test('tables list, show, update, destroy', function () {
    $created = authed($this)->postJson('/api/t/shophouse/tables', ['table_number' => 'A1'])->assertStatus(201);
    $id = $created['data']['id'];

    authed($this)->getJson('/api/t/shophouse/tables')->assertOk()->assertJsonCount(1, 'data');
    authed($this)->getJson("/api/t/shophouse/tables/{$id}")->assertOk()
        ->assertJsonPath('data.table_number', 'A1');

    authed($this)->putJson("/api/t/shophouse/tables/{$id}", ['table_name' => 'VIP A1'])
        ->assertOk()
        ->assertJsonPath('data.table_name', 'VIP A1');

    authed($this)->deleteJson("/api/t/shophouse/tables/{$id}")->assertOk();
    authed($this)->getJson("/api/t/shophouse/tables/{$id}")->assertStatus(404);
});
