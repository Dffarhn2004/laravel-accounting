<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Accounting\ReferenceChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD docs/reference-catatrapi/02-SYSTEM.md "Master Akun" and 03 "Saldo Neraca" (45 accounts).
 */
class MasterAccountTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        $company = Company::create(['name' => 'COA Co', 'code' => 'COA']);
        $branch = $company->branches()->create(['name' => 'Main', 'code' => 'MAIN']);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);

        return [$company, $user];
    }

    public function test_reference_chart_is_consistent(): void
    {
        $rows = ReferenceChartOfAccounts::all();
        $codes = array_column($rows, 'code');

        $this->assertSame($codes, array_values(array_unique($codes)), 'duplicate code');

        foreach ($rows as $row) {
            $this->assertNotNull(ReferenceChartOfAccounts::typeForCode($row['code']), $row['code']);
            $this->assertNotSame('', $row['definition'], $row['code']);
            $this->assertNotSame('', $row['module'], $row['code']);
        }

        $types = array_unique(array_map(fn ($r) => ReferenceChartOfAccounts::typeForCode($r['code']), $rows));
        sort($types);
        $this->assertSame(['asset', 'equity', 'expense', 'income', 'liability'], $types);

        $balanceSheet = array_filter($rows, fn ($r) => in_array($r['code'][0], ['1', '2', '3'], true));
        $this->assertCount(45, $balanceSheet, 'Saldo Neraca grid needs 45 accounts');
    }

    public function test_seed_creates_the_chart_and_is_idempotent_and_keeps_renames(): void
    {
        [$company] = $this->company();

        $first = ReferenceChartOfAccounts::seed($company);
        $this->assertSame(count(ReferenceChartOfAccounts::all()), $first);

        $company->accounts()->where('code', '1101')->update(['name' => 'Kas Toko']);

        $this->assertSame(0, ReferenceChartOfAccounts::seed($company));
        $this->assertSame('Kas Toko', $company->accounts()->where('code', '1101')->value('name'));
        $this->assertTrue((bool) $company->accounts()->where('code', '1552')->value('is_contra'));
        $this->assertSame('asset', $company->accounts()->where('code', '1552')->value('type'));
    }

    public function test_account_can_be_added_and_renamed_but_code_and_type_stay_fixed(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);

        $this->post('/accounting/master-data/accounts', [
            'code' => '5210', 'name' => 'Beban Parkir', 'type' => 'expense', 'module' => 'Beban Operasional', 'definition' => 'Biaya parkir.',
        ])->assertSessionHasNoErrors();

        $account = $company->accounts()->where('code', '5210')->firstOrFail();

        $this->patch("/accounting/master-data/accounts/{$account->id}", [
            'code' => '1111', 'type' => 'asset', 'name' => 'Beban Parkir & Tol', 'definition' => 'Parkir dan tol.',
        ])->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Beban Parkir & Tol', $account->name);
        $this->assertSame('5210', $account->code);
        $this->assertSame('expense', $account->type);
    }

    public function test_code_prefix_must_match_type_and_be_unique(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);
        ReferenceChartOfAccounts::seed($company);

        $this->post('/accounting/master-data/accounts', ['code' => '4999', 'name' => 'X', 'type' => 'expense'])
            ->assertSessionHasErrors('type');
        $this->post('/accounting/master-data/accounts', ['code' => '1101', 'name' => 'Duplikat', 'type' => 'asset'])
            ->assertSessionHasErrors('code');
        $this->post('/accounting/master-data/accounts', ['code' => '9100', 'name' => 'X', 'type' => 'asset'])
            ->assertSessionHasErrors('code');
    }

    public function test_used_account_cannot_be_deactivated_but_unused_can(): void
    {
        [$company, $user] = $this->company();
        $this->actingAs($user);
        ReferenceChartOfAccounts::seed($company);

        $cash = $company->accounts()->where('code', '1101')->firstOrFail();
        $equity = $company->accounts()->where('code', '3101')->firstOrFail();
        $entry = $company->journalEntries()->create([
            'branch_id' => $company->branches()->value('id'),
            'fiscal_period_id' => $company->fiscalPeriods()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->id,
            'posted_on' => '2026-01-05',
            'description' => 'Setoran',
            'source_type' => 'manual',
            'source_id' => 1,
            'idempotency_key' => 'coa-test',
        ]);
        $entry->lines()->createMany([
            ['account_id' => $cash->id, 'debit' => '100000', 'credit' => '0'],
            ['account_id' => $equity->id, 'debit' => '0', 'credit' => '100000'],
        ]);

        $this->post("/accounting/master-data/accounts/{$cash->id}/deactivate")->assertSessionHasErrors('account');
        $this->assertTrue((bool) $cash->refresh()->is_active);

        $unused = $company->accounts()->where('code', '5209')->firstOrFail();
        $this->post("/accounting/master-data/accounts/{$unused->id}/deactivate")->assertSessionHasNoErrors();
        $this->assertFalse((bool) $unused->refresh()->is_active);
    }

    public function test_other_company_account_is_not_editable(): void
    {
        [, $user] = $this->company();
        $other = Company::create(['name' => 'Other', 'code' => 'OTH']);
        $foreign = $other->accounts()->create(['code' => '1101', 'name' => 'Kas', 'type' => 'asset']);

        $this->actingAs($user);
        $this->patch("/accounting/master-data/accounts/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();
    }

    public function test_cash_bank_page_without_company_is_forbidden_not_a_server_error(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/transactions/cash-bank')->assertForbidden();
    }
}
