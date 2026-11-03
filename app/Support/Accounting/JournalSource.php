<?php

namespace App\Support\Accounting;

/**
 * Business origin of a journal entry ("Sumber" in Jurnal Umum / Buku Besar).
 * The journal is built automatically from each of these (PRD 05-LAPORAN.md #1).
 */
final class JournalSource
{
    public const LABELS = [
        'invoice' => 'Invoice',
        'receipt' => 'Pelunasan Piutang',
        'expense' => 'Beban',
        'expense_payment' => 'Pelunasan Hutang Beban',
        'inventory' => 'Persediaan',
        'asset' => 'Pembelian Asset',
        'depreciation' => 'Penyusutan',
        'treasury' => 'Kas & Bank',
        'opening' => 'Saldo / Modal Awal',
        'manual' => 'Jurnal Manual',
        'import' => 'Impor Penjualan',
        'reversal' => 'Pembalikan',
    ];

    public static function label(?string $kind): string
    {
        return self::LABELS[$kind] ?? 'Lainnya';
    }
}
