<?php

namespace App\Support\Accounting;

use App\Models\Company;

/**
 * Default "Template Jurnal" configuration (PRD 02-SYSTEM.md, sections 5-10).
 * Everything here is an editable starting point, not a rule:
 * useful lives in particular MUST be reviewed by an accountant/tax adviser (ADR-008).
 */
final class ReferenceTemplateDefaults
{
    /** Section 6 and 7 groups, option groups, and their labels (UI + validation). */
    public const GROUPS = [
        'invoice_payment' => 'Metode pembayaran invoice & pelunasan',
        'outflow_payment' => 'Metode pembayaran beban / aset / persediaan',
        'treasury_type' => 'Jenis transaksi kas / bank / hutang / modal',
        'treasury_category' => 'Kategori akun Dari / Ke',
        'opening_account' => 'Akun yang boleh dipakai di modal awal',
    ];

    /** @var array<string, array{0:string,1:string,2:string}> code => [label, from_category, to_category] */
    private const TREASURY_TYPES = [
        'transfer' => ['Transfer antar kas / bank', 'kas_bank', 'kas_bank'],
        'tarik_tunai' => ['Tarik tunai', 'kas_bank', 'kas_bank'],
        'setor_tunai' => ['Setor tunai', 'kas_bank', 'kas_bank'],
        'terima_pinjaman' => ['Terima pinjaman', 'hutang_pinjaman', 'kas_bank'],
        'bayar_hutang' => ['Bayar hutang', 'kas_bank', 'hutang'],
        'setor_pajak' => ['Setor pajak', 'kas_bank', 'hutang_pajak'],
        'tambah_modal' => ['Tambah modal', 'modal', 'kas_bank'],
        'prive' => ['Prive (penarikan pemilik)', 'kas_bank', 'prive'],
        'bayar_uang_muka' => ['Pembayaran uang muka', 'kas_bank', 'uang_muka'],
        'kembali_uang_muka' => ['Pengembalian uang muka', 'uang_muka', 'kas_bank'],
        'terima_bunga' => ['Terima bunga', 'pendapatan_bunga', 'kas_bank'],
    ];

    /** @var array<string, array{0:string, 1:array<int,string>}> category => [label, account codes] */
    private const CATEGORIES = [
        'kas_bank' => ['Kas & Bank', ['1101', '1102', '1111', '1112', '1113']],
        'hutang' => ['Hutang', ['2101', '2102', '2103', '2104', '2501', '2601']],
        'hutang_pinjaman' => ['Hutang pinjaman', ['2501', '2601']],
        'hutang_pajak' => ['Hutang pajak', ['2301', '2302', '2303', '2304']],
        'modal' => ['Modal', ['3101', '3102']],
        'prive' => ['Prive', ['3201']],
        'uang_muka' => ['Uang muka', ['1401', '2401']],
        'pendapatan_bunga' => ['Pendapatan bunga', ['4901']],
    ];

    /** Asset account => [useful life years, accumulated account, expense account]. */
    private const ASSET_CLASSES = [
        '1501' => [0, null, null],
        '1502' => [20, '1552', '5302'],
        '1503' => [8, '1553', '5303'],
        '1504' => [8, '1554', '5304'],
        '1505' => [4, '1555', '5305'],
        '1506' => [4, '1556', '5306'],
    ];

    /** Idempotent. Seeds the chart first because every default points at accounts. */
    public static function seed(Company $company): void
    {
        ReferenceChartOfAccounts::seed($company);

        $accounts = $company->accounts()->pluck('id', 'code');

        foreach (self::ASSET_CLASSES as $code => [$life, $accumulated, $expense]) {
            $company->assetCategories()->firstOrCreate(
                ['asset_account_id' => $accounts[$code]],
                [
                    'useful_life_years' => $life,
                    'accumulated_account_id' => $accumulated ? $accounts[$accumulated] : null,
                    'expense_account_id' => $expense ? $accounts[$expense] : null,
                ],
            );
        }

        $sort = 0;
        $option = function (string $group, string $code, string $label, ?string $accountCode = null, ?array $meta = null) use ($company, $accounts, &$sort) {
            $company->templateOptions()->firstOrCreate(
                ['group' => $group, 'code' => $code],
                [
                    'label' => $label,
                    'account_id' => $accountCode ? $accounts[$accountCode] : null,
                    'meta' => $meta,
                    'sort_order' => $sort++,
                ],
            );
        };

        foreach ([['cash', 'Cash', '1101'], ['bank', 'Bank', '1111'], ['marketplace', 'Saldo Marketplace', '1113']] as [$c, $l, $a]) {
            $option('invoice_payment', $c, $l, $a);
        }
        $option('invoice_payment', 'piutang', 'Belum dibayar (Piutang)', '1201');

        foreach ([['cash', 'Cash', '1101'], ['bank', 'Bank', '1111'], ['utang', 'Utang Usaha', '2101']] as [$c, $l, $a]) {
            $option('outflow_payment', $c, $l, $a);
        }

        foreach (self::TREASURY_TYPES as $code => [$label, $from, $to]) {
            $option('treasury_type', $code, $label, null, ['from_category' => $from, 'to_category' => $to]);
        }

        foreach (self::CATEGORIES as $category => [$label, $codes]) {
            foreach ($codes as $accountCode) {
                $option('treasury_category', "{$category}:{$accountCode}", $label, $accountCode, ['category' => $category]);
            }
        }

        // Opening balance may use balance-sheet accounts that are assets or liabilities.
        foreach ($company->accounts()->where(fn ($q) => $q->where('code', 'like', '1%')->orWhere('code', 'like', '2%'))->orderBy('code')->get() as $account) {
            $option('opening_account', $account->code, $account->name, $account->code);
        }
    }
}
