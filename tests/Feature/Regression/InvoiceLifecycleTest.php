<?php

use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\Item;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

function invoicePayload(int $customerId, Item $item, int $quantity, string $number): array
{
    $total = $item->price * $quantity;

    return [
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(7)->format('Y-m-d'),
        'invoice_number' => $number,
        'customer_id' => $customerId,
        'template_name' => 'invoice1',
        'exchange_rate' => 1,
        'discount' => 0,
        'discount_val' => 0,
        'discount_type' => 'fixed',
        'sub_total' => $total,
        'total' => $total,
        'tax' => 0,
        'notes' => 'Regression invoice',
        'items' => [[
            'item_id' => $item->id,
            'name' => $item->name,
            'description' => 'Regression line item',
            'quantity' => $quantity,
            'price' => $item->price,
            'discount_type' => 'fixed',
            'discount_val' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
        ]],
        'taxes' => [],
    ];
}

test('customer can be created, listed and edited through the api', function () {
    $created = postJson('api/v1/customers', [
        'name' => 'Regression Customer',
        'email' => 'regression.customer@example.com',
        'currency_id' => 1,
        'billing' => ['name' => 'Regression Customer', 'address_street_1' => '1 Main St'],
        'shipping' => ['name' => 'Regression Customer', 'address_street_1' => '1 Main St'],
    ])->assertOk();

    $id = $created->json('data.id');

    getJson('api/v1/customers?search=Regression')
        ->assertOk()
        ->assertJsonFragment(['email' => 'regression.customer@example.com']);

    putJson("api/v1/customers/{$id}", [
        'name' => 'Regression Customer Renamed',
        'email' => 'regression.customer@example.com',
        'currency_id' => 1,
        'billing' => ['name' => 'Regression Customer', 'address_street_1' => '2 Main St'],
        'shipping' => ['name' => 'Regression Customer', 'address_street_1' => '2 Main St'],
    ])->assertOk()->assertJsonPath('data.name', 'Regression Customer Renamed');

    $this->assertDatabaseHas('customers', ['id' => $id, 'name' => 'Regression Customer Renamed']);
});

test('invoice with a line item can be created, listed, edited and rendered as pdf', function () {
    CompanySetting::setSettings(['save_pdf_to_disk' => 'YES'], $this->companyId);

    $customer = Customer::factory()->create();
    $item = Item::factory()->create(['price' => 2500]);

    $created = postJson('api/v1/invoices', invoicePayload($customer->id, $item, 2, 'INV-REG-0001'))
        ->assertOk()
        ->assertJsonPath('data.total', 5000)
        ->assertJsonPath('data.customer_id', $customer->id);

    $invoice = Invoice::findOrFail($created->json('data.id'));
    expect($invoice->items)->toHaveCount(1);
    expect($invoice->unique_hash)->not->toBeEmpty();
    expect($invoice->getMedia('invoice'))->toHaveCount(1);

    getJson('api/v1/invoices?invoice_number=INV-REG-0001')
        ->assertOk()
        ->assertJsonFragment(['invoice_number' => 'INV-REG-0001']);

    putJson("api/v1/invoices/{$invoice->id}", invoicePayload($customer->id, $item, 3, 'INV-REG-0001'))
        ->assertOk()
        ->assertJsonPath('data.total', 7500);

    $invoice->refresh();
    expect($invoice->items)->toHaveCount(1);
    expect((int) $invoice->items->first()->quantity)->toBe(3);
    expect($invoice->getMedia('invoice'))->not->toBeEmpty();

    $pdf = get("/invoices/pdf/{$invoice->unique_hash}")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');
    expect($pdf->getContent())->toContain('%PDF-')->toContain('%%EOF');

    get("/invoices/pdf/{$invoice->unique_hash}?preview=1")
        ->assertOk()
        ->assertSee('INV-REG-0001');
});
