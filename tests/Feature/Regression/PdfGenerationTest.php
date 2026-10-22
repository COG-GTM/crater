<?php

use Crater\Models\Estimate;
use Crater\Models\EstimateItem;
use Crater\Models\Expense;
use Crater\Models\Invoice;
use Crater\Models\InvoiceItem;
use Crater\Models\Payment;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);
});

function assertPdfResponse($response): void
{
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    $content = $response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse
        ? $response->streamedContent()
        : $response->getContent();

    // Crater wraps dompdf's stream() Response in response()->make(), so the body can carry a
    // serialized header preamble before the PDF; assert on the PDF document markers instead.
    expect($content)->toContain('%PDF-')->toContain('%%EOF');
}

test('invoice pdf is generated', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    assertPdfResponse(get("/invoices/pdf/{$invoice->unique_hash}"));
});

test('estimate pdf is generated', function () {
    $estimate = Estimate::factory()->create();
    EstimateItem::factory()->create(['estimate_id' => $estimate->id]);

    assertPdfResponse(get("/estimates/pdf/{$estimate->unique_hash}"));
    get("/estimates/pdf/{$estimate->unique_hash}?preview=1")
        ->assertOk()
        ->assertSee($estimate->estimate_number);
});

test('payment receipt pdf is generated', function () {
    $payment = Payment::factory()->create();

    assertPdfResponse(get("/payments/pdf/{$payment->unique_hash}"));
});

test('report pdf is generated', function (string $path) {
    Invoice::factory()->create(['invoice_date' => now()->format('Y-m-d')]);
    Expense::factory()->create(['expense_date' => now()->format('Y-m-d')]);

    $query = http_build_query([
        'from_date' => now()->startOfYear()->format('Y-m-d'),
        'to_date' => now()->endOfYear()->format('Y-m-d'),
    ]);

    assertPdfResponse(get("/reports/{$path}/{$this->company->unique_hash}?{$query}"));
    assertPdfResponse(get("/reports/{$path}/{$this->company->unique_hash}?{$query}&download=1"));
    get("/reports/{$path}/{$this->company->unique_hash}?{$query}&preview=1")->assertOk();
})->with([
    'sales by customer' => 'sales/customers',
    'sales by item' => 'sales/items',
    'expenses' => 'expenses',
    'tax summary' => 'tax-summary',
    'profit and loss' => 'profit-loss',
]);
