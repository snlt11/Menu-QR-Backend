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

test('staff create requires a password', function () {
    authed($this)->postJson('/api/t/shophouse/staff', [
        'name' => 'Random Person',
        'email' => 'noone@example.com',
        'role' => 'cashier',
    ])->assertStatus(422)
      ->assertJsonPath('errors.password.0', 'The password field is required.');
});

test('staff create stays tenant-local and does not touch central users', function () {
    $centralBefore = User::count();

    $res = authed($this)->postJson('/api/t/shophouse/staff', [
        'name' => 'Ma Hnin',
        'email' => 'hnin@example.com',
        'phone' => '+95 9 123 456 789',
        'password' => 'staff-pass',
        'role' => 'cashier',
    ])->assertStatus(201);

    expect($res['data']['role'])->toBe('cashier');
    expect($res['data']['status'])->toBe('active');
    expect(array_key_exists('password', $res['data']))->toBeFalse();

    authed($this)->getJson('/api/t/shophouse/staff')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // No new row in central users — staff are tenant-local only.
    expect(User::count())->toBe($centralBefore);

    // The password lives only in the tenant DB.
    $hash = $this->tenant->run(fn () => DB::table('users')->where('email', 'hnin@example.com')->value('password'));
    expect($hash)->toBeString();
    expect(\Illuminate\Support\Facades\Hash::check('staff-pass', $hash))->toBeTrue();
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
