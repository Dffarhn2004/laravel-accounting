<?php

/*
|--------------------------------------------------------------------------
| Sidebar navigation contract (parity phase)
|--------------------------------------------------------------------------
|
| Single source of truth for the sidebar. Labels, order, grouping and item
| count MUST match docs/reference-catatrapi/NAVIGATION-CONTRACT.json exactly;
| tests/Feature/NavigationContractTest.php enforces this. Changing it needs
| an ADR (see docs/reference-catatrapi/01-MENU-PERSIS.md).
|
| Each item has exactly ONE real destination (`path`):
|   status "partial" => points at an existing page that covers part of the
|                       PRD. Parity is NOT verified (see 09-PARITY-CHECKLIST).
|   status "pending" => page not built yet. `path` is served by
|                       PendingPageController with an honest "Belum tersedia".
|
| Flip an item from pending to partial/ready by pointing `path` at the real
| route and removing it from the pending routes (they are derived from here).
|
*/

return [
    'sections' => [
        [
            'type' => 'link',
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'path' => '/dashboard',
            'status' => 'partial',
        ],
        [
            'type' => 'group',
            'id' => 'system',
            'label' => 'System',
            'spec' => 'docs/reference-catatrapi/02-SYSTEM.md',
            'items' => [
                ['id' => 'panduan', 'label' => 'Panduan Penggunaan', 'path' => '/system/panduan', 'status' => 'pending'],
                ['id' => 'master', 'label' => 'Master Akun', 'path' => '/master/accounts', 'status' => 'partial'],
                ['id' => 'template', 'label' => 'Template Jurnal', 'path' => '/system/template', 'status' => 'partial'],
                ['id' => 'tutup', 'label' => 'Tutup Buku & Pindah Tahun', 'path' => '/accounting/closing', 'status' => 'partial'],
                ['id' => 'aktivasi', 'label' => 'Aktivasi Lisensi', 'path' => '/system/aktivasi', 'status' => 'pending'],
                ['id' => 'setting', 'label' => 'Pengaturan & Backup', 'path' => '/settings/company', 'status' => 'partial'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'inputan',
            'label' => 'Inputan Transaksi',
            'spec' => 'docs/reference-catatrapi/03-INPUTAN-TRANSAKSI.md',
            'items' => [
                ['id' => 'invoice', 'label' => 'Input Invoice', 'path' => '/transactions/invoices/create', 'status' => 'partial'],
                ['id' => 'impor', 'label' => 'Import Penjualan (Kasir / Marketplace)', 'path' => '/inputan/impor', 'status' => 'partial'],
                ['id' => 'beban', 'label' => 'Input Beban', 'path' => '/transactions/expenses/create', 'status' => 'partial'],
                ['id' => 'persediaan', 'label' => 'Input Persediaan', 'path' => '/inputan/persediaan', 'status' => 'partial'],
                ['id' => 'piutang', 'label' => 'Input Pelunasan Piutang', 'path' => '/transactions/receivables', 'status' => 'partial'],
                ['id' => 'asset', 'label' => 'Input Pembelian Asset', 'path' => '/transactions/fixed-assets', 'status' => 'partial'],
                ['id' => 'kasbank', 'label' => 'Input Pindah Saldo Kas/Bank', 'path' => '/transactions/cash-bank', 'status' => 'partial'],
                ['id' => 'saldo', 'label' => 'Input Saldo Neraca Akhir Periode', 'path' => '/inputan/saldo', 'status' => 'partial'],
                ['id' => 'modal', 'label' => 'Input Setoran Modal Awal', 'path' => '/inputan/modal', 'status' => 'partial'],
                ['id' => 'jm', 'label' => 'Input Jurnal Manual', 'path' => '/accounting/manual-journal', 'status' => 'partial'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'data',
            'label' => 'Data Transaksi',
            'spec' => 'docs/reference-catatrapi/04-DATA-TRANSAKSI.md',
            'items' => [
                ['id' => 'd-pendapatan', 'label' => 'Data Pendapatan', 'path' => '/transactions/invoices', 'status' => 'partial'],
                ['id' => 'd-beban', 'label' => 'Data Beban', 'path' => '/transactions/expenses', 'status' => 'partial'],
                ['id' => 'd-persediaan', 'label' => 'Data Persediaan', 'path' => '/data/d-persediaan', 'status' => 'partial'],
                ['id' => 'd-piutang', 'label' => 'Data Pelunasan Piutang', 'path' => '/data/d-piutang', 'status' => 'partial'],
                ['id' => 'd-asset', 'label' => 'Data Asset', 'path' => '/data/d-asset', 'status' => 'partial'],
                ['id' => 'simulasi', 'label' => 'Simulasi & Output D&A', 'path' => '/data/simulasi', 'status' => 'partial'],
                ['id' => 'd-kasbank', 'label' => 'Data Kas & Bank', 'path' => '/data/d-kasbank', 'status' => 'partial'],
                ['id' => 'd-modal', 'label' => 'Data Modal Awal', 'path' => '/data/d-modal', 'status' => 'partial'],
                ['id' => 'd-jm', 'label' => 'Data Jurnal Manual', 'path' => '/data/d-jm', 'status' => 'partial'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'laporan',
            'label' => 'Laporan',
            'spec' => 'docs/reference-catatrapi/05-LAPORAN.md',
            'items' => [
                ['id' => 'ju', 'label' => 'Jurnal Umum', 'path' => '/accounting/journals', 'status' => 'partial'],
                ['id' => 'lr-cabang', 'label' => 'Laporan Laba Rugi per Cabang', 'path' => '/reports/branch-profit-loss', 'status' => 'partial'],
                ['id' => 'lr', 'label' => 'Laporan Laba Rugi Konsolidasi', 'path' => '/reports/profit-loss', 'status' => 'partial'],
                ['id' => 'neraca', 'label' => 'Laporan Neraca', 'path' => '/reports/balance-sheet', 'status' => 'partial'],
                ['id' => 'cashflow', 'label' => 'Laporan Cashflow', 'path' => '/reports/cash-flow', 'status' => 'partial'],
                ['id' => 'bb', 'label' => 'Laporan Buku Besar', 'path' => '/accounting/ledger', 'status' => 'partial'],
                ['id' => 'lap-persediaan', 'label' => 'Laporan Persediaan', 'path' => '/accounting/inventory', 'status' => 'partial'],
                ['id' => 'lap-piutang', 'label' => 'Laporan Piutang', 'path' => '/accounting/aging', 'status' => 'partial'],
                ['id' => 'lap-hutang', 'label' => 'Laporan Daftar Hutang', 'path' => '/laporan/lap-hutang', 'status' => 'partial'],
                ['id' => 'lap-pajak', 'label' => 'Laporan Pajak', 'path' => '/laporan/lap-pajak', 'status' => 'partial'],
                ['id' => 'lap-asset', 'label' => 'Laporan Daftar Asset', 'path' => '/laporan/lap-asset', 'status' => 'partial'],
                ['id' => 'rasio', 'label' => 'Analisis Rasio & Kesehatan Bisnis', 'path' => '/laporan/rasio', 'status' => 'pending'],
            ],
        ],
        [
            'type' => 'group',
            'id' => 'anggaran',
            'label' => 'Anggaran',
            'spec' => 'docs/reference-catatrapi/06-ANGGARAN-DIVIDEN.md',
            'items' => [
                ['id' => 'ang-input', 'label' => 'Input Anggaran', 'path' => '/accounting/budgets', 'status' => 'partial'],
                ['id' => 'ang-data', 'label' => 'Data Anggaran', 'path' => '/anggaran/ang-data', 'status' => 'partial'],
                ['id' => 'ang-rekap', 'label' => 'Rekap Anggaran', 'path' => '/anggaran/ang-rekap', 'status' => 'partial'],
                ['id' => 'ang-kinerja', 'label' => 'Laporan Kinerja Anggaran', 'path' => '/anggaran/ang-kinerja', 'status' => 'partial'],
            ],
        ],
        [
            'type' => 'link',
            'id' => 'dividen',
            'label' => 'Bagi Hasil & Dividen',
            'path' => '/dividen',
            'spec' => 'docs/reference-catatrapi/06-ANGGARAN-DIVIDEN.md',
            'status' => 'pending',
        ],
        [
            // Real Fortify POST /logout, never a cosmetic link.
            'type' => 'action',
            'id' => 'front',
            'label' => 'Logout',
        ],
    ],
];
