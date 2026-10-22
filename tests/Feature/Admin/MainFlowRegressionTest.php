<?php

use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('admin can log in with session credentials', function () {
    postJson('login', [
        'email' => 'admin@craterapp.com',
        'password' => 'crater@123',
    ])->assertSuccessful();

    $this->assertAuthenticatedAs(User::find(1));
});

test('admin login rejects a wrong password', function () {
    postJson('login', [
        'email' => 'admin@craterapp.com',
        'password' => 'wrong-password',
    ])->assertStatus(422);

    $this->assertGuest();
});

test('api login issues a sanctum token', function () {
    postJson('api/v1/auth/login', [
        'username' => 'admin@craterapp.com',
        'password' => 'crater@123',
        'device_name' => 'pest',
    ])
        ->assertOk()
        ->assertJsonStructure(['type', 'token']);

    $this->assertDatabaseHas('personal_access_tokens', ['name' => 'pest']);
});

describe('authenticated pdf flows', function () {
    beforeEach(function () {
        $user = User::find(1);
        $this->company = $user->companies()->first();
        $this->withHeaders(['company' => $this->company->id]);
        Sanctum::actingAs($user, ['*']);
    });

    test('invoice pdf is generated', function () {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        get("/invoices/pdf/{$invoice->unique_hash}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    test('estimate pdf is generated', function () {
        $estimate = Estimate::factory()->create(['company_id' => $this->company->id]);

        get("/estimates/pdf/{$estimate->unique_hash}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    test('payment receipt pdf is generated', function () {
        $payment = Payment::factory()->create(['company_id' => $this->company->id]);

        get("/payments/pdf/{$payment->unique_hash}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    test('profit and loss report pdf is generated', function () {
        get("/reports/profit-loss/{$this->company->unique_hash}?from_date=2026-01-01&to_date=2026-12-31")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});
