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
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $out;

    public function test_render_previews(): void
    {
        $this->out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'goldapp_pdf_previews';
        if (! is_dir($this->out)) {
            mkdir($this->out, 0777, true);
        }

        config(['app.admin.password' => 'test-password-123']);
        $this->seed(DatabaseSeeder::class);

        Setting::query()->updateOrCreate(['key' => 'shop_name'], ['value' => 'অঞ্জলী জুয়েলার্স']);
        Setting::query()->updateOrCreate(['key' => 'shop_address'], ['value' => 'মেইন রোড, জেনারেল হাসপাতাল সংলগ্ন, মুরাদনগর, কুমিল্লা।']);
        Setting::query()->updateOrCreate(['key' => 'shop_phone'], ['value' => '01888-481487']);
        Setting::query()->updateOrCreate(['key' => 'invoice_footer'], ['value' => 'বিক্রিত গহনার মূল্য অহেরযোগ্য, তবে নির্দিষ্ট অলঙ্কার তিন দিনের মধ্যে বদলায়ে দেওয়া হবে।']);
        Setting::query()->updateOrCreate(['key' => 'vat_percentage'], ['value' => '5.00']);

        $user = User::factory()->create()->assignRole('manager');

        $lines = [
            ['name' => 'স্বর্ণের চুড়ি', 'karat' => 22, 'w' => '10.000', 'making' => '500.00'],
            ['name' => 'নকল সোনা (হাতে লেখা)', 'karat' => 22, 'w' => '2.000', 'making' => '500.00'],
            ['name' => 'রুপারের দুলাবিছনা', 'karat' => 21, 'w' => '5.000', 'making' => '300.00'],
        ];

        $payloadItems = [];

        foreach ($lines as $i => $line) {
            $item = Item::factory()->for(Category::query()->firstOrFail(), 'category')->create([
                'status' => ItemStatus::InStock,
                'tag_no' => 'PREVIEW-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => $line['name'],
                'karat' => $line['karat'],
                'gross_weight' => $line['w'],
                'stone_weight' => '0.000',
                'net_weight' => $line['w'],
                'making_type' => 'fixed',
                'making_value' => $line['making'],
            ]);
            $payloadItems[] = ['item_id' => $item->id];
        }

        GoldRate::query()->updateOrCreate(['karat' => 22, 'effective_date' => today()->toDateString()], ['rate_per_gram' => '14200.00', 'created_by' => User::role('admin')->value('id')]);
        GoldRate::query()->updateOrCreate(['karat' => 21, 'effective_date' => today()->toDateString()], ['rate_per_gram' => '13500.00', 'created_by' => User::role('admin')->value('id')]);

        $customer = Customer::factory()->create([
            'name' => 'আনোয়ার হোসেন',
            'phone' => '01712999999',
            'address' => 'বাড়ি ১২, রোড ৩, কুমিল্লা সদর',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'items' => $payloadItems,
            'payments' => [['method' => 'cash', 'amount' => '150000.00']],
            'exchanges' => [
                ['description' => 'পুরোনো নকল হাত', 'karat' => 22, 'weight' => '5.000'],
            ],
            'notes' => 'গ্রাহক পরদিন আসবেন।',
        ]);

        if (! $response->isSuccessful()) {
            fwrite(STDERR, json_encode($response->json(), JSON_UNESCAPED_UNICODE)."\n");
            $this->fail('sale failed');
        }

        $sale = Sale::query()->with(['customer', 'user', 'items', 'payments.user', 'exchanges'])->findOrFail($response->json('data.id'));

        $documents = app(DocumentService::class);

        foreach (['a4', 'thermal'] as $size) {
            $data = $documents->saleViewData($sale, $size);
            file_put_contents("{$this->out}/invoice-{$size}.html", view("pdf.sale-invoice-{$size}", $data)->render());
            file_put_contents("{$this->out}/invoice-{$size}.pdf", $documents->saleInvoice($sale, $size)->output());
        }

        $payment = $sale->payments()->firstOrFail();
        $payment->loadMissing(['sale.customer', 'user:id,name']);

        foreach (['a4', 'thermal'] as $size) {
            $data = $documents->receiptViewData($payment, $size);
            file_put_contents("{$this->out}/receipt-{$size}.html", view("pdf.payment-receipt-{$size}", $data)->render());
            file_put_contents("{$this->out}/receipt-{$size}.pdf", $documents->paymentReceipt($payment, $size)->output());
        }

        // Isolated font-metric probe: how tall is a single Bangla line?
        $probe = Pdf::loadHTML(
            '<html><head><style>'
            .'@font-face{font-family:hs;src:url("'.resource_path('fonts/HindSiliguri-Regular.ttf').'")}'
            .'body{font-family:hs;font-size:10.5px;margin:0}'
            .'</style></head><body><p>আনোয়ার হোসেন টাকা ১,৫০,০০০.০০</p></body></html>'
        )->setPaper([0, 0, 210, 297]);

        $probe->getDomPDF()->render();

        fwrite(STDERR, "\nprobe pages: ".$probe->getDomPDF()->getCanvas()->get_page_count()."\n");
        fwrite(STDERR, 'probe content height: '.round($this->streamHeight($probe->getDomPDF()->getCanvas()), 2)." pt\n");

        $this->measure($documents, $sale, $payment);

        fwrite(STDERR, "rendered into {$this->out}\n");

        $this->assertTrue(true);
    }

    private function streamHeight($canvas): float
    {
        return 0.0;
    }

    private function measure(DocumentService $documents, Sale $sale, $payment): void
    {
        foreach ([['a4', 210, 297], ['thermal', 80, 900]] as [$size, $width, $height]) {
            $pdf = $documents->saleInvoice($sale, $size);
            $pdf->setPaper([0, 0, $width, $height]);
            $pdf->getDomPDF()->render();

            fwrite(STDERR, sprintf(
                "  invoice %-8s pages=%d streamHeight=%6.1f mm\n",
                $size,
                $pdf->getDomPDF()->getCanvas()->get_page_count(),
                $this->streamHeight($pdf->getDomPDF()->getCanvas()) / 72 * 25.4,
            ));
        }

        foreach ([['a4', 210, 297], ['thermal', 80, 900]] as [$size, $width, $height]) {
            $pdf = $documents->paymentReceipt($payment, $size);
            $pdf->setPaper([0, 0, $width, $height]);
            $pdf->getDomPDF()->render();

            fwrite(STDERR, sprintf(
                "  receipt  %-8s pages=%d streamHeight=%6.1f mm\n",
                $size,
                $pdf->getDomPDF()->getCanvas()->get_page_count(),
                $this->streamHeight($pdf->getDomPDF()->getCanvas()) / 72 * 25.4,
            ));
        }
    }
}
