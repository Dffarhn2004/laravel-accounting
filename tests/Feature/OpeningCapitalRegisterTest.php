<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\OpeningBalance;
use App\Models\User;
use App\Support\Accounting\ReferenceTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PRD 04 #8 Data Modal Awal. The input form itself stays covered by OpeningPositionTest. */
class OpeningCapitalRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private FiscalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Modal Co', 'code' => 'MOD']);
        $branch = $this->company->branches()->create(['name' => 'Pusat', 'code' => 'PST']);
        $this->period = $this->company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        ReferenceTemplateDefaults::seed($this->company);
        $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        $this->actingAs($this->user);
    }

    private function id(string $code): int
    {
        return $this->company->accounts()->where('code', $code)->value('id');
    }

    private function postModal(): void
    {
        $this->post('/inputan/modal', [
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->period->id,
            'posted_on' => '2026-01-01',
            'items' => [
                ['account_id' => $this->id('1101'), 'amount' => '1000000', 'description' => 'Kas'],
                ['account_id' => $this->id('2101'), 'amount' => '300000', 'description' => 'Hutang'],
                ['account_id' => $this->id('1503'), 'amount' => '20000000', 'description' => 'Mobil', 'acquired_on' => '2024-01-01', 'accumulated' => '5000000'],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function test_register_lists_items_and_accumulated_depreciation_without_the_capital_plug(): void
    {
        $this->postModal();

        $this->get('/data/d-modal')->assertOk()->assertInertia(fn ($p) => $p
            ->component('accounting/data-modal-awal')
            ->has('columns', 16)
            ->where('columns.15', 'Nilai Kredit')
            ->has('rows', 4)
            ->where('rows.0.debit_code', '1101')
            ->where('rows.0.credit_code', '3102')
            ->where('rows.0.debit', '1000000.00')
            ->where('rows.1.position', 'Kredit')
            ->where('rows.1.debit_code', '3102')
            ->where('rows.1.credit_code', '2101')
            ->where('rows.2.debit_code', '1503')
            ->where('rows.3.credit_code', '1553')
            ->where('rows.3.debit_code', '3102')
            ->where('rows.3.credit', '5000000.00')
            ->where('totals.count', 1)
            ->where('totals.debit', '26300000.00')
            ->where('totals.credit', '26300000.00'));

        $names = collect($this->get('/data/d-modal')->original->getData()['page']['props']['rows'])->pluck('description');
        $this->assertFalse($names->contains('Modal awal pemilik'));

        $csv = $this->get('/data/d-modal/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Tanggal Cut-off', $csv);
        $this->assertStringContainsString('3102', $csv);
        $this->assertStringNotContainsString('Modal awal pemilik', $csv);
    }

    public function test_reversal_frees_the_branch_for_a_new_setoran_and_hides_saldo_neraca(): void
    {
        $other = $this->company->branches()->create(['name' => 'Cabang', 'code' => 'CBG']);
        $this->post('/inputan/saldo', [
            'branch_id' => $other->id,
            'fiscal_period_id' => $this->period->id,
            'posted_on' => '2026-01-01',
            'items' => [
                ['account_id' => $this->id('1101'), 'amount' => '10'],
                ['account_id' => $this->id('3101'), 'amount' => '10'],
            ],
        ])->assertSessionHasNoErrors();
        $saldo = OpeningBalance::where('method', 'saldo_neraca')->firstOrFail();
        $this->get('/data/d-modal')->assertInertia(fn ($p) => $p->has('rows', 0));
        $this->post("/data/d-modal/{$saldo->id}/reverse", ['reason' => 'Bukan modal'])->assertNotFound();

        $this->postModal();
        $this->post('/inputan/saldo', [
            'branch_id' => $this->user->branch_id,
            'fiscal_period_id' => $this->period->id,
            'posted_on' => '2026-01-01',
            'items' => [
                ['account_id' => $this->id('1101'), 'amount' => '10'],
                ['account_id' => $this->id('3101'), 'amount' => '10'],
            ],
        ])->assertSessionHasErrors('branch_id');

        $opening = OpeningBalance::where('method', 'modal_awal')->firstOrFail();
        $this->post("/data/d-modal/{$opening->id}/reverse", ['reason' => 'Salah cut-off', 'posted_on' => '2026-01-02'])->assertSessionHasNoErrors();
        $opening->refresh();
        $this->assertSame('reversed', $opening->status);
        $this->assertNotNull($opening->reversed_at);

        $this->postModal();
        $this->assertSame(2, OpeningBalance::where('method', 'modal_awal')->count());
        $this->assertSame(1, OpeningBalance::where('method', 'modal_awal')->whereNull('reversed_at')->count());
    }

    public function test_another_company_cannot_see_or_reverse_the_opening(): void
    {
        $this->postModal();
        $opening = OpeningBalance::firstOrFail();
        $other = Company::create(['name' => 'Lain', 'code' => 'LAIN']);
        $this->actingAs(User::factory()->create(['company_id' => $other->id]));

        $this->get('/data/d-modal')->assertInertia(fn ($p) => $p->has('rows', 0));
        $this->post("/data/d-modal/{$opening->id}/reverse", ['reason' => 'Bukan milik kami'])->assertNotFound();
    }
}
