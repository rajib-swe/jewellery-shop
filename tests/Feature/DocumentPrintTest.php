<?php

namespace Tests\Feature;

use App\ItemStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentService;
use App\Support\DocumentFormat;
use App\Support\Weight;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedApplication();
    }

    public function test_a4_invoice_renders_a_pdf(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user, $this->banglaCustomer());

        $response = $this->actingAs($user)->get("/sales/{$sale->id}/invoice");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertPdfBody($response);
    }

    public function test_thermal_invoice_renders_a_pdf(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $response = $this->actingAs($user)->get("/sales/{$sale->id}/invoice?size=thermal");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertPdfBody($response);
    }

    public function test_a4_invoice_html_contains_bangla_names_invoice_and_totals(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user, $this->banglaCustomer());

        $html = view('pdf.sale-invoice-a4', $this->documents()->saleViewData($sale))->render();

        $this->assertStringContainsString('আনোয়ার হোসেন', $html);
        $this->assertStringContainsString($sale->invoice_no, $html);
        $this->assertStringContainsString('109,000.00', $html);
        $this->assertStringContainsString('ITM-TEST-TAG', $html);
        $this->assertStringContainsString('হাতে লেখা পণ্য', $html);
    }

    public function test_thermal_invoice_html_shows_weight_summary_and_totals(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $html = view('pdf.sale-invoice-thermal', $this->documents()->saleViewData($sale, 'thermal'))->render();

        $this->assertStringContainsString($sale->invoice_no, $html);
        $this->assertStringContainsString('10.000', $html);
        $this->assertStringContainsString('109,000.00', $html);
    }

    public function test_a4_invoice_lists_exchange_and_payment_rows(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user, paid: '60000.00', exchanges: [[
            'description' => 'পুরোনো নকল হাত',
            'karat' => 22,
            'weight' => '5.000',
        ]]);

        $html = view('pdf.sale-invoice-a4', $this->documents()->saleViewData($sale))->render();

        $this->assertStringContainsString('পুরোনো নকল হাত', $html);
        $this->assertStringContainsString('Cash', $html);
    }

    public function test_invoice_reports_the_shop_weight_unit(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $this->putSetting('weight_unit', 'vori');

        $html = view('pdf.sale-invoice-a4', $this->documents()->saleViewData($sale->fresh()))->render();

        $this->assertStringContainsString(number_format(Weight::gramsToVori(10.0), 4, '.', ''), $html);
    }

    public function test_voided_sale_is_stamped_on_the_invoice(): void
    {
        $user = $this->userWithRole('admin');
        $sale = $this->recordSale($user);

        $this->actingAs($user)
            ->postJson("/api/v1/sales/{$sale->id}/void", ['reason' => 'গ্রাহক অজুহাত করেছেন'])
            ->assertOk();

        $html = view('pdf.sale-invoice-a4', $this->documents()->saleViewData($sale->fresh()))->render();

        $this->assertStringContainsString('বাতিলকৃত বিক্রয়', $html);
        $this->assertStringContainsString('গ্রাহক অজুহাত করেছেন', $html);
    }

    public function test_a4_receipt_renders_a_pdf(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user, $this->banglaCustomer());
        $payment = $sale->payments()->firstOrFail();

        $response = $this->actingAs($user)->get("/payments/{$payment->id}/receipt");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertPdfBody($response);
    }

    public function test_thermal_receipt_renders_a_pdf(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);
        $payment = $sale->payments()->firstOrFail();

        $response = $this->actingAs($user)->get("/payments/{$payment->id}/receipt?size=thermal");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertPdfBody($response);
    }

    public function test_receipt_html_contains_amount_customer_and_invoice(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user, $this->banglaCustomer(), paid: '10,000.00');
        $payment = $sale->payments()->orderByDesc('id')->firstOrFail();

        $html = view('pdf.payment-receipt-a4', $this->documents()->receiptViewData($payment))->render();

        $this->assertStringContainsString('আনোয়ার হোসেন', $html);
        $this->assertStringContainsString($sale->invoice_no, $html);
        $this->assertStringContainsString('10,000.00', $html);
        $this->assertStringContainsString('RCP-', $html);
    }

    public function test_documents_require_authentication(): void
    {
        $this->get('/sales/1/invoice')->assertRedirect(route('login'));
        $this->get('/payments/1/receipt')->assertRedirect(route('login'));
    }

    public function test_documents_require_the_view_sales_permission(): void
    {
        $user = User::factory()->create();
        $sale = $this->recordSale($this->userWithRole('manager'));
        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)->get("/sales/{$sale->id}/invoice")->assertForbidden();
        $this->actingAs($user)->get("/payments/{$payment->id}/receipt")->assertForbidden();
    }

    public function test_cashier_can_print_invoices(): void
    {
        $sale = $this->recordSale($this->userWithRole('manager'));

        $this->actingAs($this->userWithRole('cashier'))
            ->get("/sales/{$sale->id}/invoice")
            ->assertOk();
    }

    public function test_unknown_paper_size_is_rejected(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $this->actingAs($user)->get("/sales/{$sale->id}/invoice?size=poster")->assertSessionHasErrors('size');
    }

    public function test_missing_paper_size_defaults_to_a4(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $this->actingAs($user)->get("/sales/{$sale->id}/invoice?size=")->assertOk();
    }

    public function test_download_disposition_is_honoured(): void
    {
        $user = $this->userWithRole('manager');
        $sale = $this->recordSale($user);

        $response = $this->actingAs($user)->get("/sales/{$sale->id}/invoice?download=1");

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_amount_in_words_renders_bangla_taka(): void
    {
        $this->assertSame('দুই লক্ষ এক হাজার টাকা মাত্র', DocumentFormat::amountInWords('201000.00'));
        $this->assertSame('এক টাকা পঞ্চাশ পয়সা মাত্র', DocumentFormat::amountInWords('1.50'));
        $this->assertSame('শূন্য টাকা মাত্র', DocumentFormat::amountInWords('0'));
    }

    private function documents(): DocumentService
    {
        return app(DocumentService::class);
    }

    private function assertPdfBody(TestResponse $response): void
    {
        $body = $response->baseResponse instanceof StreamedResponse
            ? $response->streamedContent()
            : (string) $response->getContent();

        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('%%EOF', $body);
    }

    private function banglaCustomer(): Customer
    {
        return Customer::factory()->create([
            'name' => 'আনোয়ার হোসেন',
            'phone' => '01712345678',
        ]);
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $exchanges
     */
    private function recordSale(User $user, ?Customer $customer = null, string $paid = '109000.00', array $exchanges = []): Sale
    {
        $item = $this->item();

        $this->setGoldRate(22, '9000.00');

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'customer_id' => $customer?->id,
            'items' => [
                ['item_id' => $item->id],
                [
                    'name' => 'নকল সোনা (হাতে লেখা)',
                    'karat' => 22,
                    'weight' => '2.000',
                    'making_type' => 'fixed',
                    'making_value' => '500.00',
                ],
            ],
            'payments' => $paid === '0.00' ? [] : [['method' => 'cash', 'amount' => str_replace(',', '', $paid)]],
            'exchanges' => $exchanges,
        ]);

        $response->assertCreated();

        return Sale::query()->findOrFail($response->json('data.id'));
    }

    private function item(): Item
    {
        return Item::factory()->for(Category::query()->firstOrFail(), 'category')->create([
            'status' => ItemStatus::InStock,
            'tag_no' => 'ITM-TEST-TAG',
            'name' => 'স্বর্ণের চুড়ি',
            'karat' => 22,
            'gross_weight' => '10.000',
            'stone_weight' => '0.000',
            'net_weight' => '10.000',
            'making_type' => 'fixed',
            'making_value' => '500.00',
            'stone_price' => '0.00',
        ]);
    }

    private function setGoldRate(int $karat, string $rate): void
    {
        GoldRate::query()->updateOrCreate(
            ['karat' => $karat, 'effective_date' => today()->toDateString()],
            ['rate_per_gram' => $rate, 'created_by' => User::role('admin')->value('id')],
        );
    }

    private function seedApplication(): void
    {
        config(['app.admin.password' => 'test-password-123']);

        $this->seed(DatabaseSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create()->assignRole($roleName);
    }
}
