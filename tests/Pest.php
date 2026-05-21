<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Provision the demo tenant `shophouse` with an owner row inside the tenant
 * `users` table. Returns the Tenant model.
 */
function makeDemoShop(string $slug = 'shophouse', string $ownerEmail = 'koaung@example.com', string $password = 'password'): \App\Models\Tenant
{
    \Illuminate\Support\Facades\DB::statement('DROP DATABASE IF EXISTS `tenant_'.$slug.'`');

    return app(\App\Actions\CreateTenantAction::class)->execute(
        'Shwe Food House',
        $slug,
        [
            'name' => 'Ko Aung',
            'email' => $ownerEmail,
            'phone' => null,
            'password' => $password,
        ],
    );
}

/**
 * Log in as the owner and return the bearer token.
 */
function ownerLogin($test, string $slug = 'shophouse', string $email = 'koaung@example.com', string $password = 'password'): string
{
    return $test
        ->postJson("/api/t/{$slug}/login", ['email' => $email, 'password' => $password])
        ->assertOk()
        ->json('data.token');
}
