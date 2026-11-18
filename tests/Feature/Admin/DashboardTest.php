<?php

use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

getJson('api/v1/dashboard')->assertOk();

getJson('api/v1/search?name=ab')->assertOk();

test('dashboard chart and totals sum each fiscal month', function () {
    \Carbon\Carbon::setTestNow('2026-05-15 12:00:00');

    $companyId = User::find(1)->companies()->first()->id;
    \Crater\Models\CompanySetting::setSettings(['fiscal_year' => '1-12'], $companyId);

    foreach ([['2026-01-10', 100], ['2026-01-20', 5], ['2026-03-15', 7], ['2025-12-15', 1000], ['2027-01-15', 2000]] as [$date, $amount]) {
        \Crater\Models\Invoice::factory()->create(['invoice_date' => $date, 'base_total' => $amount]);
        \Crater\Models\Payment::factory()->create(['payment_date' => $date, 'base_amount' => $amount * 2]);
        \Crater\Models\Expense::factory()->create(['expense_date' => $date, 'base_amount' => $amount]);
    }

    $response = getJson('api/v1/dashboard')->assertOk();

    expect($response->json('chart_data.months'))->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    expect(array_map('intval', $response->json('chart_data.invoice_totals')))->toBe([105, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    expect(array_map('intval', $response->json('chart_data.expense_totals')))->toBe([105, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    expect(array_map('intval', $response->json('chart_data.receipt_totals')))->toBe([210, 0, 14, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    expect(array_map('intval', $response->json('chart_data.net_income_totals')))->toBe([105, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    expect((int) $response->json('total_sales'))->toBe(112);
    expect((int) $response->json('total_receipts'))->toBe(224);
    expect((int) $response->json('total_expenses'))->toBe(112);
    expect($response->json('total_net_income'))->toBe(112);

    $previous = getJson('api/v1/dashboard?previous_year=1')->assertOk();

    expect((int) $previous->json('chart_data.invoice_totals.11'))->toBe(1000);
    expect((int) $previous->json('total_sales'))->toBe(1000);

    \Carbon\Carbon::setTestNow();
});
