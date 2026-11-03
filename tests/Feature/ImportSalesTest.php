<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\InventoryCostLayer;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Accounting\SalesImportService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * PRD 03 #2 and ACC-11 / ACC-12. One order number is one invoice; a second import does not move the ledger.
 */
class ImportSalesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Impor Co', 'code' => 'IMP']);
        $this->branchId = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST', 'is_active' => true])->id;
        $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
    }

    public function test_preview_groups_an_order_and_does_not_post(): void
    {
        $csv = $this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', ''],
            ['A-1', '2026-03-02', 'Siti', 'Gula', '1', '5000', '0', '0', '0', '0', '', ''],
        ]);

        $this->post('/inputan/impor/preview', $this->fields($csv))->assertOk()->assertSee('Siap diposting')->assertSee('A-1');

        $this->assertSame(0, Invoice::count());
    }

    public function test_tax_shipping_and_admin_hit_the_right_accounts_and_the_register(): void
    {
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '2', '10000', '1000', '1900', '500', '300', '', ''],
        ])))->assertRedirect('/inputan/impor');

        $invoice = Invoice::firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('21400.00', bcadd((string) $invoice->total, '0', 2));
        $this->assertSame('1900.00', bcadd((string) $invoice->vat_amount, '0', 2));
        $this->assertSame('500.00', bcadd((string) $invoice->other_income_total, '0', 2));
        $this->assertSame('21100.00', $this->net('1101'));
        $this->assertSame('-20000.00', $this->net('4101'));
        $this->assertSame('-500.00', $this->net('4104'));
        $this->assertSame('-1900.00', $this->net('2301'));
        $this->assertSame('1000.00', $this->net('5103'));
        $this->assertSame('300.00', $this->net('5205'));

        $this->get('/transactions/invoices')->assertOk()->assertSee('Siti')->assertSee($invoice->invoice_number);
    }

    public function test_rows_of_one_order_become_one_invoice(): void
    {
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', ''],
            ['A-1', '2026-03-02', 'Siti', 'Gula', '2', '4000', '500', '0', '0', '0', '', ''],
        ])))->assertSessionHasNoErrors();

        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, Invoice::firstOrFail()->lines()->count());
    }

    public function test_a_repeated_order_number_does_not_change_the_ledger(): void
    {
        $csv = $this->csv([['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', '']]);
        $this->post('/inputan/impor', $this->fields($csv))->assertSessionHasNoErrors();
        $journals = JournalEntry::count();
        $cash = $this->net('1101');

        $this->post('/inputan/impor', $this->fields($csv, 'again'))->assertSessionHasNoErrors();

        $this->assertSame($journals, JournalEntry::count());
        $this->assertSame($cash, $this->net('1101'));
        $this->assertSame(1, Invoice::count());
        $second = ImportBatch::orderByDesc('id')->firstOrFail();
        $this->assertSame(0, $second->order_count);
        $this->assertSame(1, $second->skipped_count);
    }

    public function test_sku_issues_stock_at_the_branch_average_not_the_file_amount(): void
    {
        $this->stock(['quantity' => '10', 'unit_cost' => '10000', 'moved_on' => '2026-03-01']);

        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '2', '25000', '0', '0', '0', '0', 'BJ', '1'],
        ])))->assertSessionHasNoErrors();

        $issue = InventoryMovement::where('movement_type', 'issue')->firstOrFail();
        $this->assertSame('20000.00', bcadd((string) $issue->total_cost, '0', 2));
        $this->assertSame('8.0000', (string) InventoryCostLayer::firstOrFail()->quantity);
        $this->assertSame('20000.00', $this->net('5101'));
    }

    public function test_insufficient_stock_posts_nothing(): void
    {
        $this->stock(['quantity' => '1', 'unit_cost' => '10000', 'moved_on' => '2026-03-01']);
        $before = JournalEntry::count();

        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', 'BJ', ''],
            ['A-2', '2026-03-02', 'Budi', 'Kopi', '1', '10000', '0', '0', '0', '0', 'BJ', ''],
        ])))->assertSessionHasErrors('file');

        $this->assertSame(0, Invoice::count());
        $this->assertSame($before, JournalEntry::count());
        $this->assertSame('1.0000', (string) InventoryCostLayer::firstOrFail()->quantity);
    }

    public function test_hpp_without_a_sku_and_an_unknown_sku_are_rejected(): void
    {
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', '5000'],
        ])))->assertSessionHasErrors('file');
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-2', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', 'TIDAK', ''],
        ]), 'unknown'))->assertSessionHasErrors('file');

        $this->assertSame(0, Invoice::count());
    }

    public function test_cancelling_a_batch_reverses_the_invoice_the_admin_fee_and_the_stock(): void
    {
        $this->stock(['quantity' => '10', 'unit_cost' => '10000', 'moved_on' => '2026-03-01']);
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '2', '10000', '0', '0', '0', '100', 'BJ', ''],
        ])))->assertSessionHasNoErrors();
        $batch = ImportBatch::firstOrFail();

        $this->post('/inputan/impor/'.$batch->id.'/reverse')->assertRedirect('/inputan/impor');

        $this->assertSame('reversed', $batch->fresh()->status);
        $this->assertSame('reversed', Invoice::firstOrFail()->status);
        $this->assertSame('10.0000', (string) InventoryCostLayer::firstOrFail()->quantity);
        $this->assertSame('-100000.00', $this->net('1101'));
        $this->assertSame('0.00', $this->net('5101'));
        $this->assertSame('0.00', $this->net('5205'));
    }

    public function test_cancel_is_refused_when_a_later_receipt_or_a_newer_stock_movement_exists(): void
    {
        $this->stock(['quantity' => '10', 'unit_cost' => '10000', 'moved_on' => '2026-03-01']);
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', 'BJ', ''],
        ])))->assertSessionHasNoErrors();
        $batch = ImportBatch::firstOrFail();
        $invoice = Invoice::firstOrFail();
        $receipt = Receipt::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchId,
            'fiscal_period_id' => $invoice->fiscal_period_id,
            'receipt_number' => 'RCP-EXTRA',
            'received_on' => '2026-03-05',
            'amount' => '1.00',
            'idempotency_key' => 'extra-receipt',
        ]);
        $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => '1.00']);

        $this->post('/inputan/impor/'.$batch->id.'/reverse')->assertSessionHasErrors('batch');
        $this->assertSame('paid', $invoice->fresh()->status);

        $receipt->allocations()->delete();
        $receipt->delete();
        $this->stock(['quantity' => '1', 'unit_cost' => '10000', 'moved_on' => '2026-03-04', 'idempotency_key' => 'later-buy']);

        $this->post('/inputan/impor/'.$batch->id.'/reverse')->assertSessionHasErrors('batch');
        $this->assertSame('active', $batch->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_another_company_can_use_the_same_order_number_and_a_branch_user_cannot_cross_branches(): void
    {
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', ''],
        ])))->assertSessionHasNoErrors();

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $branch = $other->branches()->create(['name' => 'Pusat', 'code' => 'PST', 'is_active' => true]);
        $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        $this->actingAs(User::factory()->create(['company_id' => $other->id]));
        $this->company = $other;
        $this->branchId = $branch->id;
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-1', '2026-03-02', 'Andi', 'Teh', '1', '8000', '0', '0', '0', '0', '', ''],
        ]), 'other-co'))->assertSessionHasNoErrors();
        $this->assertSame(2, Invoice::count());

        $home = Company::where('code', 'IMP')->firstOrFail();
        $foreignBranch = $home->branches()->create(['name' => 'Cabang', 'code' => 'CBG', 'is_active' => true])->id;
        $this->actingAs(User::factory()->create(['company_id' => $home->id, 'branch_id' => $this->branchOf($home)]));
        $this->company = $home;
        $this->post('/inputan/impor', $this->fields($this->csv([
            ['A-9', '2026-03-02', 'Siti', 'Kopi', '1', '10000', '0', '0', '0', '0', '', ''],
        ]), 'cross') + ['branch_id' => $foreignBranch])->assertSessionHasErrors('branch_id');
    }

    public function test_templates_download_and_an_xlsx_file_posts(): void
    {
        $csv = $this->get('/inputan/impor/template.csv');
        $csv->assertOk();
        $this->assertStringContainsString('No Order', $csv->streamedContent());
        $xlsx = $this->get('/inputan/impor/template.xlsx');
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $path = tempnam(sys_get_temp_dir(), 'jual').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(SalesImportService::HEADERS));
        $writer->addRow(Row::fromValues(['X-1', '2026-03-02', 'Siti', 'Kopi', 1, 15000, 0, 0, 0, 0, '', '']));
        $writer->close();
        $file = new UploadedFile($path, 'jual.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->post('/inputan/impor', $this->fields($file, 'xlsx'))->assertSessionHasNoErrors();
        $this->assertSame('15000.00', bcadd((string) Invoice::firstOrFail()->total, '0', 2));
    }

    public function test_oversized_and_too_many_orders_are_rejected(): void
    {
        $this->post('/inputan/impor/preview', $this->fields(UploadedFile::fake()->create('besar.csv', 3000)))
            ->assertSessionHasErrors('file');

        $rows = [];
        for ($i = 1; $i <= 501; $i++) {
            $rows[] = ['N-'.$i, '2026-03-02', 'Siti', 'Kopi', '1', '1000', '0', '0', '0', '0', '', ''];
        }
        $this->post('/inputan/impor/preview', $this->fields($this->csv($rows), 'many'))->assertSessionHasErrors('file');
        $this->assertSame(0, Invoice::count());
    }

    /** @param  list<list<string>>  $rows */
    private function csv(array $rows): UploadedFile
    {
        $lines = [implode(',', SalesImportService::HEADERS)];
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return UploadedFile::fake()->createWithContent('jual.csv', implode("\n", $lines));
    }

    /** @return array<string, mixed> */
    private function fields(UploadedFile $file, string $key = 'once'): array
    {
        return [
            'file' => $file,
            'branch_id' => $this->branchId,
            'channel' => 'Marketplace',
            'receipt_account_id' => $this->id('1101'),
            'revenue_account_id' => $this->id('4101'),
            'other_income_account_id' => $this->id('4104'),
            'admin_account_id' => $this->id('5205'),
            'idempotency_key' => $key,
        ];
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    private function net(string $code): string
    {
        $net = '0.00';
        foreach (JournalLine::where('account_id', $this->id($code))->get() as $line) {
            $net = bcadd($net, bcsub((string) $line->debit, (string) $line->credit, 2), 2);
        }

        return $net;
    }

    /** @param  array<string, mixed>  $override */
    private function stock(array $override): void
    {
        if (! $this->company->inventoryItems()->exists()) {
            $this->post('/system/template/inventory', [
                'sku' => 'BJ', 'name' => 'Biji kopi', 'unit' => 'kg', 'minimum_stock' => '1',
                'inventory_account_id' => $this->id('1301'), 'cogs_account_id' => $this->id('5101'),
            ])->assertSessionHasNoErrors();
        }
        $this->post('/inputan/persediaan', array_replace([
            'branch_id' => $this->branchId,
            'moved_on' => '2026-03-01',
            'kind' => 'purchase',
            'payment_method' => 'cash',
            'inventory_item_id' => (int) $this->company->inventoryItems()->value('id'),
            'description' => 'Beli',
            'quantity' => '10',
            'unit_cost' => '10000',
            'freight' => '0',
            'tax' => '0',
            'idempotency_key' => (string) Str::uuid(),
        ], $override))->assertSessionHasNoErrors();
    }

    private function branchOf(Company $company): int
    {
        return (int) $company->branches()->where('code', 'PST')->value('id');
    }
}
