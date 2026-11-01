# Checklist Penyelarasan (sumber kebenaran status)

Cara baca: `[x]` = **terbukti** (test lulus / sudah dicek). `[ ]` = belum. Jangan centang berdasarkan 'filenya ada'.

Gate (dari `reference-catatrapi/09-PARITY-CHECKLIST.md`): **A** navigasi · **B** UI/field/tabel/filter · **C** workflow end-to-end · **D** akuntansi & test · **E** responsif/aksesibel/cPanel.

Kolom *Halaman*: `partial` = ada halaman lama yang menutupi sebagian PRD (parity belum terverifikasi); `pending` = belum dibangun ('Belum tersedia'). Saat sebuah halaman lulus gate, update juga `config/navigation.php`.

Terakhir diperbarui: 2026-11-01 (Langkah 0 + Langkah 2.1 sampai 2.4)

## A. Per halaman (43)

### Dashboard
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Dashboard | `/dashboard` | partial | [x] | [ ] | [ ] | [ ] | [ ] |

### System (6) — spek: `reference-catatrapi/02-SYSTEM.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Panduan Penggunaan | `/system/panduan` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Master Akun | `/master/accounts` | partial | [x] | [x] | [x] | [ ] | [ ] |
| Template Jurnal | `/system/template` | partial | [x] | [x] | [ ] | [ ] | [ ] |
| Tutup Buku & Pindah Tahun | `/accounting/closing` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Aktivasi Lisensi | `/system/aktivasi` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Pengaturan & Backup | `/settings/company` | partial | [x] | [ ] | [ ] | [ ] | [ ] |

### Inputan Transaksi (10) — spek: `reference-catatrapi/03-INPUTAN-TRANSAKSI.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Input Invoice | `/transactions/invoices/create` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Import Penjualan (Kasir / Marketplace) | `/inputan/impor` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Beban | `/transactions/expenses/create` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Persediaan | `/inputan/persediaan` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Pelunasan Piutang | `/transactions/receivables` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Pembelian Asset | `/transactions/fixed-assets` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Pindah Saldo Kas/Bank | `/transactions/cash-bank` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Saldo Neraca Akhir Periode | `/inputan/saldo` | partial | [x] | [x] | [x] | [x] | [ ] |
| Input Setoran Modal Awal | `/inputan/modal` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Jurnal Manual | `/accounting/manual-journal` | partial | [x] | [ ] | [ ] | [ ] | [ ] |

### Data Transaksi (9) — spek: `reference-catatrapi/04-DATA-TRANSAKSI.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Data Pendapatan | `/transactions/invoices` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Beban | `/transactions/expenses` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Persediaan | `/data/d-persediaan` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Pelunasan Piutang | `/data/d-piutang` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Asset | `/data/d-asset` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Simulasi & Output D&A | `/data/simulasi` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Kas & Bank | `/data/d-kasbank` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Modal Awal | `/data/d-modal` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Jurnal Manual | `/data/d-jm` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Laporan (12) — spek: `reference-catatrapi/05-LAPORAN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Jurnal Umum | `/accounting/journals` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Laba Rugi per Cabang | `/laporan/lr-cabang` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Laba Rugi Konsolidasi | `/reports/profit-loss` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Neraca | `/reports/balance-sheet` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Cashflow | `/laporan/cashflow` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Buku Besar | `/accounting/ledger` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Persediaan | `/accounting/inventory` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Piutang | `/accounting/aging` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Daftar Hutang | `/laporan/lap-hutang` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Pajak | `/laporan/lap-pajak` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Daftar Asset | `/laporan/lap-asset` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Analisis Rasio & Kesehatan Bisnis | `/laporan/rasio` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Anggaran (4) — spek: `reference-catatrapi/06-ANGGARAN-DIVIDEN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Input Anggaran | `/accounting/budgets` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Data Anggaran | `/anggaran/ang-data` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Rekap Anggaran | `/anggaran/ang-rekap` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Laporan Kinerja Anggaran | `/anggaran/ang-kinerja` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Bagi Hasil & Dividen — spek: `reference-catatrapi/06-ANGGARAN-DIVIDEN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Bagi Hasil & Dividen | `/dividen` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Logout
- [x] Aksi `POST /logout` (Fortify) di sidebar, dites di `NavigationContractTest`

## B. Gap kritis dari audit (09-PARITY-CHECKLIST), harus ditutup
- [ ] CRITICAL: KPI 'Kas & Setara Kas' hanya akun kas/bank (bukan agregat aset), `FinancialReportService`
- [ ] CRITICAL: 19 rasio nyata, hapus placeholder 'Ratio 9-22', `FinancialRatioService`
- [ ] CRITICAL: Health score dari 19 rasio, bukan balance jurnal, `HealthScoreService`
- [ ] CRITICAL: Tutup buku carry-forward saldo/AR/AP/stok/aset + arsip JSON, `ClosingController`
- [ ] HIGH: Invoice lengkap: diskon, pendapatan lain (max 3), PPN, PPh23, uang muka, cetak, bukti (max 5), max 15 baris produk
- [ ] HIGH: LR & Neraca hierarkis per akun, matriks Jan-Des, kolom per cabang
- [ ] HIGH: Dashboard filter global cabang+periode, semua blok terisi data nyata (bukan empty state)
- [ ] HIGH: UI input persediaan (masuk/keluar) di atas `InventoryPostingService`
- [ ] HIGH: Anggaran Qty x Tarif per kegiatan per bulan; Input/Data/Rekap/Kinerja terpisah
- [x] MEDIUM: COA seed lengkap (73 akun, 45 akun neraca) - `ReferenceChartOfAccounts`, dites di `MasterAccountTest`
- [x] MEDIUM: Sidebar 43 item persis kontrak (Langkah 1)

## C. Utang teknis / dokumentasi
- [ ] Commit perubahan Langkah 1 (belum di-commit)
- [x] Tandai `docs/05-UI-TEMPLATE.md` (bagian Sidebar) & `docs/10-UI-PAGE-SPEC.md` sebagai SUPERSEDED
- [x] `TreasuryController` kini 403 (bukan 500) bila `company_id` null, dites
- [ ] Cek tampilan sidebar di browser (desktop/mobile), belum pernah dicek manual
- [ ] CI menjalankan `npm run build` sebelum `php artisan test` (manifest Vite)
- [ ] Redirect/alias route lama ke menu baru (setelah tes regresi ada)
- [ ] Template Jurnal: efek ke dropdown formulir lain belum terbukti (form Invoice/Beban/Kas-Bank/Persediaan/Aset belum membaca `products`, `journal_template_options`, `asset_categories`). Gate C ditunda sampai form itu memakainya.
- [ ] Keamanan lama: `MasterDataController::storeInventoryItem` memvalidasi `exists:accounts,id` tanpa batas company (akun company lain bisa direferensikan). Perbaiki seperti `TemplateController::accountRule`.
- [ ] Menu lama `/master/products` (InventoryItem) dan `/master/branches` tumpang tindih dengan Template Jurnal; putuskan kapan dialihkan.
- [ ] Saldo Neraca: draf/autosave seperti referensi tidak ada. Deviasi sadar: posting hanya bila seimbang (selisih 0), supaya ledger tidak pernah tidak seimbang. Putuskan apakah draf perlu.
- [ ] Setoran Modal Awal (gate C `[~]`): aset cut-off belum masuk register aset (`fixed_assets`); datanya (tanggal perolehan, umur, akumulasi) sudah disimpan di `opening_balance_lines.meta`. Selesaikan di Langkah 5.
- [ ] Halaman lama `/accounting/opening-balances` masih ada (jurnal generik) tapi sudah tunduk pada eksklusivitas; tidak lagi ada di sidebar. Putuskan kapan dihapus/dialihkan.
- [ ] Koreksi saldo awal (reversal + posting ulang) belum ada UI; saat ini hanya terkunci dengan pesan.
