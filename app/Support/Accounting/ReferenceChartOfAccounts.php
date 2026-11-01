<?php

namespace App\Support\Accounting;

use App\Models\Company;

/**
 * Default Chart of Accounts (PRD docs/reference-catatrapi/02-SYSTEM.md, "Master Akun").
 *
 * Prefix rule: 1 Aset, 2 Kewajiban, 3 Ekuitas, 4 Pendapatan, 5 Beban.
 * Normal balance: 1 & 5 debit; 2, 3 & 4 credit. `contra` marks accounts that
 * reduce their group (shown as a negative position in Saldo Neraca).
 *
 * The 45 balance-sheet accounts (prefix 1-3) match the 45-row Saldo Neraca grid.
 * Original wording; only the functional structure follows the reference.
 */
final class ReferenceChartOfAccounts
{
    public const TYPE_BY_PREFIX = [
        '1' => 'asset',
        '2' => 'liability',
        '3' => 'equity',
        '4' => 'income',
        '5' => 'expense',
    ];

    /** @return array<int, array{code:string,name:string,module:string,definition:string,contra?:bool}> */
    public static function all(): array
    {
        $rows = [
            // 1 — ASET
            ['1101', 'Kas', 'Kas & Bank', 'Uang tunai di tangan usaha.'],
            ['1102', 'Kas Kecil', 'Kas & Bank', 'Dana tunai kecil untuk pengeluaran operasional harian.'],
            ['1111', 'Bank Utama', 'Kas & Bank', 'Rekening bank utama usaha.'],
            ['1112', 'Bank Operasional', 'Kas & Bank', 'Rekening bank untuk transaksi operasional.'],
            ['1113', 'Saldo Marketplace / E-Wallet', 'Kas & Bank', 'Dana yang tertahan di marketplace atau dompet digital.'],
            ['1201', 'Piutang Usaha', 'Piutang', 'Tagihan kepada pelanggan atas penjualan yang belum dibayar.'],
            ['1202', 'Piutang Karyawan', 'Piutang', 'Pinjaman atau kasbon karyawan yang belum dilunasi.'],
            ['1203', 'Piutang Lain-lain', 'Piutang', 'Tagihan di luar penjualan usaha.'],
            ['1301', 'Persediaan Bahan Baku', 'Persediaan', 'Bahan yang akan diproses menjadi barang jual.'],
            ['1302', 'Persediaan Barang Dagang', 'Persediaan', 'Barang siap jual.'],
            ['1303', 'Persediaan Perlengkapan & Kemasan', 'Persediaan', 'Kemasan dan perlengkapan habis pakai.'],
            ['1401', 'Uang Muka Pembelian', 'Dibayar di Muka', 'Uang muka yang dibayar kepada pemasok.'],
            ['1402', 'Biaya Dibayar di Muka', 'Dibayar di Muka', 'Biaya yang dibayar sebelum manfaatnya terpakai (sewa, asuransi).'],
            ['1403', 'PPN Masukan', 'Pajak', 'PPN atas pembelian yang dapat dikreditkan.'],
            ['1404', 'PPh 23 Dibayar di Muka', 'Pajak', 'PPh 23 yang dipotong pihak lain dan menjadi kredit pajak.'],
            ['1501', 'Tanah', 'Aset Tetap', 'Tanah milik usaha; tidak disusutkan.'],
            ['1502', 'Bangunan', 'Aset Tetap', 'Bangunan milik usaha.'],
            ['1503', 'Kendaraan', 'Aset Tetap', 'Kendaraan operasional.'],
            ['1504', 'Mesin & Peralatan Produksi', 'Aset Tetap', 'Mesin dan alat produksi.'],
            ['1505', 'Peralatan Kantor', 'Aset Tetap', 'Komputer, furnitur kantor, dan sejenisnya.'],
            ['1506', 'Inventaris Lainnya', 'Aset Tetap', 'Aset tetap lain di luar kelompok di atas.'],
            ['1552', 'Akumulasi Penyusutan Bangunan', 'Aset Tetap', 'Penyusutan Bangunan yang sudah dibebankan (pengurang aset).', true],
            ['1553', 'Akumulasi Penyusutan Kendaraan', 'Aset Tetap', 'Penyusutan Kendaraan yang sudah dibebankan (pengurang aset).', true],
            ['1554', 'Akumulasi Penyusutan Mesin & Peralatan', 'Aset Tetap', 'Penyusutan Mesin & Peralatan yang sudah dibebankan (pengurang aset).', true],
            ['1555', 'Akumulasi Penyusutan Peralatan Kantor', 'Aset Tetap', 'Penyusutan Peralatan Kantor yang sudah dibebankan (pengurang aset).', true],
            ['1556', 'Akumulasi Penyusutan Inventaris', 'Aset Tetap', 'Penyusutan Inventaris yang sudah dibebankan (pengurang aset).', true],

            // 2 — KEWAJIBAN
            ['2101', 'Hutang Usaha', 'Hutang', 'Kewajiban atas beban atau pembelian yang belum dibayar.'],
            ['2102', 'Hutang Pembelian Aset', 'Hutang', 'Kewajiban atas pembelian aset tetap yang belum dibayar.'],
            ['2103', 'Hutang Persediaan', 'Hutang', 'Kewajiban atas pembelian persediaan yang belum dibayar.'],
            ['2104', 'Hutang Lain-lain', 'Hutang', 'Kewajiban lain jangka pendek.'],
            ['2201', 'Hutang Gaji', 'Hutang', 'Gaji dan tunjangan yang sudah menjadi beban tetapi belum dibayar.'],
            ['2301', 'PPN Keluaran', 'Pajak', 'PPN atas penjualan yang harus disetor.'],
            ['2302', 'Hutang PPh 21', 'Pajak', 'PPh 21 yang dipotong dari karyawan dan belum disetor.'],
            ['2303', 'Hutang PPh 23', 'Pajak', 'PPh 23 yang dipotong atas pembayaran jasa dan belum disetor.'],
            ['2304', 'Hutang PPh Badan', 'Pajak', 'Pajak penghasilan badan/usaha yang belum disetor.'],
            ['2401', 'Uang Muka Pelanggan', 'Hutang', 'Uang muka dari pelanggan atas pesanan yang belum diserahkan.'],
            ['2501', 'Pinjaman Jangka Pendek', 'Pinjaman', 'Pinjaman jatuh tempo dalam satu tahun.'],
            ['2601', 'Pinjaman Jangka Panjang', 'Pinjaman', 'Pinjaman jatuh tempo lebih dari satu tahun.'],
            ['2701', 'Hutang Dividen', 'Dividen', 'Dividen yang sudah dideklarasikan tetapi belum dibayar.'],

            // 3 — EKUITAS
            ['3101', 'Modal Disetor', 'Ekuitas', 'Modal yang disetor pemilik atau pemegang saham.'],
            ['3102', 'Modal Awal Pemilik', 'Ekuitas', 'Modal hasil cut-off harta dikurangi hutang saat usaha mulai dicatat.'],
            ['3201', 'Prive / Penarikan Pemilik', 'Ekuitas', 'Pengambilan dana usaha oleh pemilik (pengurang ekuitas).', true],
            ['3301', 'Laba Ditahan', 'Ekuitas', 'Akumulasi laba tahun-tahun sebelumnya yang tidak dibagikan.'],
            ['3302', 'Laba Tahun Berjalan', 'Ekuitas', 'Laba atau rugi periode berjalan sebelum ditutup.'],
            ['3401', 'Dividen Dideklarasikan', 'Ekuitas', 'Dividen yang diumumkan selama tahun berjalan (pengurang ekuitas).', true],

            // 4 — PENDAPATAN
            ['4101', 'Penjualan Produk', 'Pendapatan', 'Pendapatan dari penjualan barang.'],
            ['4102', 'Pendapatan Jasa', 'Pendapatan', 'Pendapatan dari penyediaan jasa.'],
            ['4103', 'Penjualan Marketplace', 'Pendapatan', 'Pendapatan dari penjualan lewat marketplace atau platform pesan antar.'],
            ['4104', 'Pendapatan Lain Operasional', 'Pendapatan', 'Pendapatan tambahan dari penjualan, misalnya ongkos kirim yang ditagihkan.'],
            ['4901', 'Pendapatan Bunga', 'Non-Operasional', 'Bunga yang diterima dari bank atau pihak lain.'],
            ['4902', 'Pendapatan Non-Operasional Lain', 'Non-Operasional', 'Pendapatan di luar kegiatan utama usaha.'],

            // 5 — BEBAN
            ['5101', 'Harga Pokok Penjualan (HPP)', 'Beban Pokok', 'Biaya persediaan yang terjual atau terpakai.'],
            ['5102', 'Beban Pengiriman Penjualan', 'Beban Pokok', 'Biaya kirim barang ke pelanggan.'],
            ['5103', 'Beban Discount Penjualan', 'Beban Pokok', 'Potongan harga yang diberikan kepada pelanggan.'],
            ['5201', 'Beban Gaji & Tunjangan', 'Beban Operasional', 'Gaji, upah, dan tunjangan karyawan.'],
            ['5202', 'Beban Sewa', 'Beban Operasional', 'Sewa tempat atau peralatan.'],
            ['5203', 'Beban Listrik, Air & Internet', 'Beban Operasional', 'Tagihan utilitas bulanan.'],
            ['5204', 'Beban Pemasaran & Iklan', 'Beban Operasional', 'Biaya promosi dan iklan.'],
            ['5205', 'Beban Administrasi Platform', 'Beban Operasional', 'Biaya admin marketplace atau platform penjualan.'],
            ['5206', 'Beban Perlengkapan & ATK', 'Beban Operasional', 'Perlengkapan kantor dan alat tulis.'],
            ['5207', 'Beban Transportasi', 'Beban Operasional', 'Biaya perjalanan dan angkut operasional.'],
            ['5208', 'Beban Perawatan & Perbaikan', 'Beban Operasional', 'Pemeliharaan aset dan peralatan.'],
            ['5209', 'Beban Operasional Lain', 'Beban Operasional', 'Beban operasional lain yang tidak masuk kelompok di atas.'],
            ['5302', 'Beban Penyusutan Bangunan', 'Penyusutan', 'Beban penyusutan bulanan Bangunan.'],
            ['5303', 'Beban Penyusutan Kendaraan', 'Penyusutan', 'Beban penyusutan bulanan Kendaraan.'],
            ['5304', 'Beban Penyusutan Mesin & Peralatan', 'Penyusutan', 'Beban penyusutan bulanan Mesin & Peralatan.'],
            ['5305', 'Beban Penyusutan Peralatan Kantor', 'Penyusutan', 'Beban penyusutan bulanan Peralatan Kantor.'],
            ['5306', 'Beban Penyusutan Inventaris', 'Penyusutan', 'Beban penyusutan bulanan Inventaris.'],
            ['5401', 'Beban Bunga', 'Non-Operasional', 'Bunga pinjaman yang dibayar.'],
            ['5402', 'Beban Administrasi Bank', 'Non-Operasional', 'Biaya administrasi dan transfer bank.'],
            ['5403', 'Beban Non-Operasional Lain', 'Non-Operasional', 'Beban di luar kegiatan utama usaha.'],
            ['5501', 'Beban Pajak Penghasilan', 'Pajak', 'Beban pajak penghasilan badan/usaha.'],
            ['5502', 'Beban Pajak Lainnya', 'Pajak', 'Beban pajak selain pajak penghasilan.'],
        ];

        return array_map(fn (array $r) => [
            'code' => $r[0],
            'name' => $r[1],
            'module' => $r[2],
            'definition' => $r[3],
            'contra' => $r[4] ?? false,
        ], $rows);
    }

    public static function typeForCode(string $code): ?string
    {
        return self::TYPE_BY_PREFIX[$code[0] ?? ''] ?? null;
    }

    /**
     * Seed the reference chart for a company. Idempotent: existing codes are
     * left untouched (users may have renamed them). Returns the number created.
     */
    public static function seed(Company $company): int
    {
        $created = 0;

        foreach (self::all() as $row) {
            $account = $company->accounts()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => self::typeForCode($row['code']),
                    'module' => $row['module'],
                    'definition' => $row['definition'],
                    'is_contra' => $row['contra'],
                ],
            );

            $created += $account->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
