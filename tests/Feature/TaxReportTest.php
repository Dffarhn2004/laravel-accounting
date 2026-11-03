<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\JournalReversalService;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PRD 05 #10 Laporan Pajak: masa per pajak, FIFO setoran, reversal pairs, buku pembantu, beban pajak.
 */
class TaxReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $branchId;

    private int $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->company = Company::create(['name' => 'Pajak Co', 'code' => 'PJK']);
        $this->branchId = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST'])->id;
        $this->periodId = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id;
        ReferenceTemplateDefaults::seed($this->company);
        $this->actingAs(User::factory()->create(['company_id' => $this->company->id]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function id(string $code): int
    {
        return (int) $this->company->accounts()->where('code', $code)->value('id');
    }

    private function journal(string $date, string $debitCode, string $creditCode, string $amount, string $key): JournalEntry
    {
        return app(JournalPostingService::class)->post([
            'company_id' => $this->company->id,
            'branch_id' => $this->branchId,
            'fiscal_period_id' => $this->periodId,
            'posted_on' => $date,
            'description' => 'Pajak '.$key,
            'idempotency_key' => 'tax-'.$key,
            'source_kind' => 'manual',
            'lines' => [
                ['account_id' => $this->id($debitCode), 'debit' => $amount, 'credit' => '0'],
                ['account_id' => $this->id($creditCode), 'debit' => '0', 'credit' => $amount],
            ],
        ]);
    }

    private function page(string $query = ''): array
    {
        $response = $this->get('/laporan/lap-pajak'.$query)->assertOk();
        $this->assertSame('accounting/lap-pajak', $response->viewData('page')['component']);

        return $response->viewData('page')['props'];
    }

    public function test_setoran_go_to_the_oldest_masa_and_reconcile_to_the_ledger(): void
    {
        $this->journal('2026-01-20', '1101', '2301', '100000', 'ppn-jan');
        $this->journal('2026-02-20', '1101', '2301', '50000', 'ppn-feb');
        $this->journal('2026-03-10', '2301', '1101', '120000', 'ppn-setor');
        $this->journal('2026-01-05', '1403', '1101', '5000', 'masukan');
        $this->journal('2026-01-15', '2302', '1101', '10000', 'pph21-muka');
        $this->journal('2026-02-15', '1101', '2302', '30000', 'pph21-feb');

        $page = $this->page();
        $masa = collect($page['masa'])->keyBy(fn ($r) => $r['tax'].'-'.$r['period']);

        $this->assertSame('paid', $masa['ppn-2026-01']['status']);
        $this->assertSame('100000.00', $masa['ppn-2026-01']['paid']);
        $this->assertSame('partial', $masa['ppn-2026-02']['status']);
        $this->assertSame('20000.00', $masa['ppn-2026-02']['paid']);
        $this->assertSame('30000.00', $masa['ppn-2026-02']['remaining']);
        $this->assertSame('2026-03-31', $masa['ppn-2026-02']['due_estimate']);
        $this->assertTrue($masa['ppn-2026-02']['overdue']);
        $this->assertFalse($masa['ppn-2026-01']['overdue']);
        $this->assertSame('5000.00', $masa['ppn-2026-01']['credit']);

        // A setoran with nothing open waits and pays the next masa that accrues.
        $this->assertSame('partial', $masa['pph21-2026-02']['status']);
        $this->assertSame('10000.00', $masa['pph21-2026-02']['paid']);
        $this->assertSame('20000.00', $masa['pph21-2026-02']['remaining']);
        $this->assertSame('2026-03-10', $masa['pph21-2026-02']['due_estimate']);

        $summary = collect($page['summary'])->keyBy('tax');
        $this->assertSame('30000.00', $summary['ppn']['outstanding']);
        $this->assertSame('150000.00', $summary['ppn']['accrued']);
        $this->assertSame('120000.00', $summary['ppn']['paid']);
        $this->assertSame('5000.00', $summary['ppn']['credit_balance']);

        foreach ($page['reconciliation'] as $r) {
            $this->assertSame('0.00', $r['difference'], $r['tax']);
        }
    }

    public function test_a_reversed_entry_and_its_reversal_are_not_an_obligation(): void
    {
        $this->journal('2026-01-20', '1101', '2301', '100000', 'ppn-jan');
        $wrong = $this->journal('2026-04-10', '1101', '2301', '999000', 'ppn-wrong');
        app(JournalReversalService::class)->reverse($wrong, ['posted_on' => '2026-04-12', 'idempotency_key' => 'rev-wrong']);

        $page = $this->page('?tax=ppn');
        $this->assertSame(['2026-01'], collect($page['masa'])->pluck('period')->all());
        $this->assertSame('100000.00', collect($page['summary'])->firstWhere('tax', 'ppn')['outstanding']);
        $this->assertSame('0.00', $page['reconciliation'][0]['difference']);
    }

    public function test_filters_narrow_the_masa_and_the_buku_pembantu(): void
    {
        $this->journal('2026-01-20', '1101', '2301', '100000', 'ppn-jan');
        $this->journal('2026-02-20', '1101', '2301', '50000', 'ppn-feb');
        $this->journal('2026-03-10', '2301', '1101', '100000', 'ppn-setor');

        $paid = $this->page('?tax=ppn&status=paid');
        $this->assertSame(['2026-01'], collect($paid['masa'])->pluck('period')->all());

        $overdue = $this->page('?tax=ppn&status=overdue');
        $this->assertSame(['2026-02'], collect($overdue['masa'])->pluck('period')->all());

        $feb = $this->page('?tax=ppn&month=2');
        $this->assertSame(['2026-02'], collect($feb['masa'])->pluck('period')->all());
        $this->assertCount(1, $feb['ledger']['rows']);
        $this->assertSame('50000.00', $feb['ledger']['rows'][0]['credit']);

        $this->get('/laporan/lap-pajak?tax=bukan-pajak')->assertSessionHasErrors('tax');
    }

    public function test_beban_pajak_block_lists_the_tax_expense_accounts(): void
    {
        $this->journal('2026-03-31', '5501', '1101', '7000', 'beban');

        $expense = $this->page()['expense'];
        $this->assertSame('7000.00', $expense['total']);
        $this->assertSame('5501', $expense['rows'][0]['code']);
        $this->assertSame('7000.00', $expense['rows'][0]['months'][3]);
    }

    public function test_another_company_sees_nothing_and_export_is_csv(): void
    {
        $this->journal('2026-01-20', '1101', '2301', '100000', 'ppn-jan');
        $this->get('/laporan/lap-pajak/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $branch = $other->branches()->create(['name' => 'Lain', 'code' => 'L']);
        $other->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($other);
        $this->actingAs(User::factory()->create(['company_id' => $other->id, 'branch_id' => $branch->id]));

        $page = $this->page();
        $this->assertSame([], $page['masa']);
        $this->assertSame([], $page['ledger']['rows']);
    }
}
