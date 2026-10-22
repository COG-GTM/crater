<?php

use Crater\Models\Invoice;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('admin can log in to the web session with seeded credentials', function () {
    postJson('login', [
        'email' => 'admin@craterapp.com',
        'password' => 'crater@123',
    ])->assertSuccessful();

    $this->assertAuthenticatedAs(User::find(1), 'web');
});

test('web login rejects a wrong password', function () {
    postJson('login', [
        'email' => 'admin@craterapp.com',
        'password' => 'not-the-password',
    ])->assertStatus(422);

    $this->assertGuest('web');
});

test('api token login returns a bearer token that authorizes api calls', function () {
    $response = postJson('api/v1/auth/login', [
        'username' => 'admin@craterapp.com',
        'password' => 'crater@123',
        'device_name' => 'regression-suite',
    ])->assertOk()->assertJsonPath('type', 'Bearer');

    $token = $response->json('token');
    expect($token)->toBeString()->not->toBeEmpty();

    $companyId = User::find(1)->companies()->first()->id;

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'company' => $companyId,
    ])->getJson('api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@craterapp.com');
});

test('api token login rejects a wrong password', function () {
    postJson('api/v1/auth/login', [
        'username' => 'admin@craterapp.com',
        'password' => 'not-the-password',
        'device_name' => 'regression-suite',
    ])->assertStatus(422);
});

test('unauthenticated api requests are rejected', function () {
    getJson('api/v1/customers')->assertUnauthorized();
});

test('guests are redirected to login from the admin app and pdf endpoints', function () {
    $invoice = Invoice::factory()->create();

    get('/admin/dashboard')->assertRedirect();
    get("/invoices/pdf/{$invoice->unique_hash}")->assertRedirect('/login');
});

test('login page renders the spa shell', function () {
    get('/login')->assertOk()->assertSee('window.Crater.start()', false);
});
