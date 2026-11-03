# Checklist Penyelarasan (sumber kebenaran status)

Cara baca: `[x]` = **terbukti** (test lulus / sudah dicek). `[ ]` = belum. Jangan centang berdasarkan 'filenya ada'.

Gate (dari `reference-catatrapi/09-PARITY-CHECKLIST.md`): **A** navigasi · **B** UI/field/tabel/filter · **C** workflow end-to-end · **D** akuntansi & test · **E** responsif/aksesibel/cPanel.

Kolom *Halaman*: `partial` = ada halaman lama yang menutupi sebagian PRD (parity belum terverifikasi); `pending` = belum dibangun ('Belum tersedia'). Saat sebuah halaman lulus gate, update juga `config/navigation.php`.

Terakhir diperbarui: 2026-11-04 (Langkah 7.1 Anggaran selesai di server dan UI, dites; belum dicoba di browser. Sebelumnya 6.2 Laporan Pajak)

## A. Per halaman (43)

### Dashboard
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Dashboard | `/dashboard` | partial | [x] | [~] | [~] | [x] | [ ] |

### System (6) — spek: `reference-catatrapi/02-SYSTEM.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Panduan Penggunaan | `/system/panduan` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Master Akun | `/master/accounts` | partial | [x] | [x] | [x] | [ ] | [ ] |
| Template Jurnal | `/system/template` | partial | [x] | [x] | [ ] | [ ] | [ ] |
| Tutup Buku & Pindah Tahun | `/accounting/closing` | partial | [x] | [ ] | [ ] | [ ] | [ ] |
| Aktivasi Lisensi | `/system/aktivasi` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Pengaturan & Backup | `/settings/company` | partial | [x] | [x] | [ ] | [ ] | [ ] |

### Inputan Transaksi (10) — spek: `reference-catatrapi/03-INPUTAN-TRANSAKSI.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Input Invoice | `/transactions/invoices/create` | partial | [x] | [x] | [~] | [~] | [ ] |
| Import Penjualan (Kasir / Marketplace) | `/inputan/impor` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Beban | `/transactions/expenses/create` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Persediaan | `/inputan/persediaan` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Pelunasan Piutang | `/transactions/receivables` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Pembelian Asset | `/transactions/fixed-assets` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Pindah Saldo Kas/Bank | `/transactions/cash-bank` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Saldo Neraca Akhir Periode | `/inputan/saldo` | partial | [x] | [x] | [x] | [x] | [ ] |
| Input Setoran Modal Awal | `/inputan/modal` | partial | [x] | [x] | [~] | [x] | [ ] |
| Input Jurnal Manual | `/accounting/manual-journal` | partial | [x] | [x] | [~] | [x] | [ ] |

### Data Transaksi (9) — spek: `reference-catatrapi/04-DATA-TRANSAKSI.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Data Pendapatan | `/transactions/invoices` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Beban | `/transactions/expenses` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Persediaan | `/data/d-persediaan` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Pelunasan Piutang | `/data/d-piutang` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Asset | `/data/d-asset` | partial | [x] | [x] | [~] | [x] | [ ] |
| Simulasi & Output D&A | `/data/simulasi` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Kas & Bank | `/data/d-kasbank` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Modal Awal | `/data/d-modal` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Jurnal Manual | `/data/d-jm` | partial | [x] | [x] | [~] | [x] | [ ] |

### Laporan (12) — spek: `reference-catatrapi/05-LAPORAN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Jurnal Umum | `/accounting/journals` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Laba Rugi per Cabang | `/reports/branch-profit-loss` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Laba Rugi Konsolidasi | `/reports/profit-loss` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Neraca | `/reports/balance-sheet` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Cashflow | `/reports/cash-flow` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Buku Besar | `/accounting/ledger` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Persediaan | `/accounting/inventory` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Piutang | `/accounting/aging` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Daftar Hutang | `/laporan/lap-hutang` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Pajak | `/laporan/lap-pajak` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Daftar Asset | `/laporan/lap-asset` | partial | [x] | [x] | [~] | [x] | [ ] |
| Analisis Rasio & Kesehatan Bisnis | `/laporan/rasio` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Anggaran (4) — spek: `reference-catatrapi/06-ANGGARAN-DIVIDEN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Input Anggaran | `/accounting/budgets` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Anggaran | `/anggaran/ang-data` | partial | [x] | [x] | [~] | [x] | [ ] |
| Rekap Anggaran | `/anggaran/ang-rekap` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Kinerja Anggaran | `/anggaran/ang-kinerja` | partial | [x] | [x] | [~] | [x] | [ ] |

### Bagi Hasil & Dividen — spek: `reference-catatrapi/06-ANGGARAN-DIVIDEN.md`
| Menu | Route sekarang | Halaman | A | B | C | D | E |
|---|---|---|---|---|---|---|---|
| Bagi Hasil & Dividen | `/dividen` | pending | [x] | [ ] | [ ] | [ ] | [ ] |

### Logout
- [x] Aksi `POST /logout` (Fortify) di sidebar, dites di `NavigationContractTest`

## B. Gap kritis dari audit (09-PARITY-CHECKLIST), harus ditutup
- [x] CRITICAL: KPI 'Kas & Setara Kas' hanya akun kas/bank, bukan agregat aset (`DashboardService`, `DashboardReportingTest`)
- [ ] CRITICAL: 19 rasio nyata, hapus placeholder 'Ratio 9-22', `FinancialRatioService`
- [ ] CRITICAL: Health score dari 19 rasio, bukan balance jurnal, `HealthScoreService`
- [ ] CRITICAL: Tutup buku carry-forward saldo/AR/AP/stok/aset + arsip JSON, `ClosingController`
- [ ] HIGH: Invoice lengkap: diskon, pendapatan lain (max 3), PPN, PPh23, uang muka, cetak, bukti (max 5), max 15 baris produk
- [x] HIGH: LR & Neraca hierarkis per akun, matriks Jan-Des, kolom per cabang (Langkah 3.4, `FinancialStatementsTest`)
- [x] HIGH: Dashboard filter global cabang+periode; blok terisi data nyata (blok yang bergantung Langkah 7 ditandai 'Belum tersedia', bukan angka palsu)
- [x] HIGH: UI input persediaan (masuk/keluar) di atas `InventoryPostingService` (Langkah 5.1, `InventoryFlowTest`)
- [x] HIGH: Anggaran Qty x Tarif per kegiatan per bulan; Input/Data/Rekap/Kinerja terpisah (Langkah 7.1, `BudgetPlanTest`)
- [x] MEDIUM: COA seed lengkap (73 akun, 45 akun neraca) - `ReferenceChartOfAccounts`, dites di `MasterAccountTest`
- [x] MEDIUM: Sidebar 43 item persis kontrak (Langkah 1)

## C. Utang teknis / dokumentasi
- [ ] Commit perubahan Langkah 1 (belum di-commit)
- [x] Tandai `docs/05-UI-TEMPLATE.md` (bagian Sidebar) & `docs/10-UI-PAGE-SPEC.md` sebagai SUPERSEDED
- [x] `TreasuryController` kini 403 (bukan 500) bila `company_id` null, dites
- [ ] Cek tampilan sidebar di browser (desktop/mobile), belum pernah dicek manual
- [ ] CI menjalankan `npm run build` sebelum `php artisan test` (manifest Vite)
- [ ] Redirect/alias route lama ke menu baru (setelah tes regresi ada)
- [ ] Template Jurnal: efek ke dropdown formulir lain belum terbukti untuk Invoice. Beban, Kas/Bank, Persediaan, dan Pembelian Aset sudah membaca `journal_template_options`; Persediaan membaca jenis dari bagian 4; Aset membaca kelompok dari bagian 5. Gate C ditunda sampai form yang tersisa memakainya.
- [ ] Keamanan lama: `MasterDataController::storeInventoryItem` memvalidasi `exists:accounts,id` tanpa batas company (akun company lain bisa direferensikan). Perbaiki seperti `TemplateController::accountRule`.
- [ ] Menu lama `/master/products` (InventoryItem) dan `/master/branches` tumpang tindih dengan Template Jurnal; putuskan kapan dialihkan.
- [ ] Saldo Neraca: draf/autosave seperti referensi tidak ada. Deviasi sadar: posting hanya bila seimbang (selisih 0), supaya ledger tidak pernah tidak seimbang. Putuskan apakah draf perlu.
- [x] Setoran Modal Awal: aset cut-off masuk register (`fixed_assets`, sumber Modal) tanpa jurnal perolehan kedua. Akumulasi cut-off tetap di jurnal setoran. Pembalikan setoran ditolak bila penyusutan aset itu masih hidup (`AssetFlowTest`).
- [ ] Halaman lama `/accounting/opening-balances` masih ada (jurnal generik) tapi sudah tunduk pada eksklusivitas; tidak lagi ada di sidebar. Putuskan kapan dihapus/dialihkan.
- [x] Koreksi Setoran Modal Awal ada di Data Modal Awal: pembalikan membebaskan cabang untuk setoran ulang (`OpeningCapitalRegisterTest`). Saldo Neraca tetap tidak dibalik dari menu ini.
- [ ] Pengaturan & Backup: identitas usaha, rekening invoice, dan default PPN/PPh23 tersimpan dan dites. Backup/restore, data contoh, dan reset tetap menunggu ADR-006; tarif hanya konfigurasi usaha dan belum dipakai Input Invoice (ADR-008).
- [ ] Input Invoice gate C `[~]`/D `[~]`: server terbukti lewat `InvoiceInputTest` (13 test), tetapi form belum dicoba di browser dan alur ke laporan (JU/BB/LR/Neraca/Dashboard) belum ada; centang penuh setelah Langkah 3.3-3.5.
- [ ] Invoice: customer harus sudah ada di master (belum ada tambah customer cepat dari form).
- [ ] Invoice: pesan kesalahan bisnis dari `TransactionPostingService` masih berbahasa Inggris; terjemahkan.
- [ ] Invoice: belum ada koreksi/pembalikan dari UI (reversal + posting ulang); edit/hapus posted harus lewat pembalikan (ADR-010).
- [ ] Invoice: field "urutan" di PRD belum dipahami (tidak ada di UI); konfirmasi dengan user.
- [ ] Lampiran (`AttachmentService`) sudah dipasang di Beban, Kas/Bank, Pelunasan Piutang, Modal Awal, Jurnal Manual, Persediaan, dan Pembelian Aset (maks 5). Data Pendapatan masih perlu tambah/hapus lampiran.
- [x] PPh23 (ADR-008): estimasi tetap di invoice. Saat pelanggan memotong, pelunasan membukukan debit kas neto + debit 1404, kredit piutang bruto (`ReceivableSettlementTest`). Belum dicoba di browser.
- [ ] Diskon dibukukan ke akun 5103 Beban Discount (sesuai kolom referensi Data Pendapatan). Mode lama (tanpa akun diskon, pendapatan netto) masih didukung service untuk kompatibilitas.
- [ ] Data Pendapatan gate C `[~]`: server dan UI dites (`RevenueRegisterTest` 13 test). Invoice dari Import Penjualan masuk register yang sama (`ImportSalesTest`). Di browser, `INV-2026-00002` tampil lalu dibatalkan bersama batch-nya.
- [ ] Data Pendapatan: PPh 23 ditampilkan sebagai estimasi (redup, tooltip) dan kode 1404 karena belum dibukukan saat terbit (ADR-008). Konfirmasi tampilan ini dengan user.
- [ ] Data Pendapatan: tombol Edit pada referensi diganti "Koreksi: buat invoice baru" setelah membalik; belum ada edit-sekali-jalan (pembalikan + salin ke form baru).
- [x] Pembalikan invoice menolak bila ada pelunasan susulan yang masih hidup. Membalik pelunasan mengembalikan status invoice, lalu invoice boleh dibalik (`ReceivableSettlementTest`). Di browser: cicilan 40.000 dibalik, total aktif register kembali 0.
- [ ] Laporan lain yang menghitung piutang (`FinancialReportService`, `ReportController`) mengandalkan status `unpaid/partial`; periksa kembali saat laporan dirombak di Langkah 3.3-3.5 agar invoice `reversed` tak ikut.
- [ ] Jurnal Umum/Buku Besar gate C `[~]`: server dan UI dites (`GeneralLedgerReportsTest` 16 test), belum dicoba di browser. Drilldown sumber aktif untuk invoice, beban, pelunasan piutang, pelunasan hutang beban, jurnal manual (`/data/d-jm`), dan saldo/modal awal (`/data/d-modal`).
- [ ] Jurnal Umum: kolom Saldo = saldo berjalan akun pada sisi normal sejak awal pembukuan (tidak di-reset filter bulan/cabang bila tidak memilih cabang). Arti "Saldo" di referensi tidak terverifikasi; konfirmasi dengan user.
- [ ] Jurnal lama (sebelum kolom `source_kind`) dengan `TransactionPostingService` tampil "Lainnya" karena jenisnya tidak dapat disimpulkan; jurnal baru selalu bertanda jelas.
- [ ] Buku Besar: klik akun pada ringkasan memuat ulang halaman (bukan panel inline); cetak memakai print browser/CSV, belum ada print stylesheet khusus.
- [ ] `FinancialReportService`/LR/Neraca/Cashflow masih agregat lama; Buku Besar kini sumber saldo per akun yang benar, gunakan `LedgerService::summary()` untuk Langkah 3.4.
- [ ] Laporan keuangan gate C `[~]`: 4 laporan dites dengan skenario hitung-tangan (`FinancialStatementsTest`, 15 test) tetapi belum dicoba di browser.
- [ ] Laba Rugi: klasifikasi seksi memakai awalan kode akun (`ReportStructure`): 49 non-operasional, 51 direct cost, 52 dan lainnya overhead, 53 depreciation, 54 beban non-operasional, 55 pajak. Konfirmasi dengan akuntan; akun buatan user mengikuti awalan.
- [ ] Laba Rugi Konsolidasi: referensi mengaitkan Periode Akhir dashboard ke laba berjalan Neraca dan menyediakan bandingkan-bulan; belum dibuat (filter global dashboard di Langkah 3.5).
- [ ] Laba Rugi/Neraca/Cashflow: belum ada export (CSV) dan print stylesheet masih sederhana (cetak browser).
- [ ] Neraca: "tabel rasio pendukung" belum dibuat (menunggu Langkah 7 Analisis Rasio). Kolom "saldo awal tahun lalu" ditafsirkan sebagai posisi pada Periode Awal (jurnal saldo awal pada periode dihitung posisi awal); konfirmasi dengan user.
- [ ] Neraca memakai Laba Ditahan + Laba Tahun Berjalan terhitung karena belum ada jurnal penutup. Saat Tutup Buku (Langkah 8) memposting penutupan, verifikasi ulang Neraca agar tidak ganda.
- [ ] Cashflow: pembelian aset dengan utang tampil sebagai investasi + kenaikan hutang (tanpa pemisahan investasi non-kas). Transfer antar cabang dan eliminasi (ADR-012) belum ditangani.
- [ ] `FinancialReportService` lama masih dipakai Dashboard dan Ringkasan Laporan (angka KPI lama, Kas = total aset). Ganti ke `LedgerService`/`BalanceSheetService` di Langkah 3.5.
- [ ] Dashboard gate B `[~]`: belum ada health score 19 rasio, enam rasio kunci, achievement anggaran, proyeksi dividen (kartu 'Belum tersedia' menuju menu terkait); dikerjakan di Langkah 7. Gate C `[~]`: belum dicoba di browser.
- [ ] `HealthScoreService` (skor 100 bila jurnal seimbang) tidak lagi dipakai Dashboard tetapi file masih ada; hapus/ganti saat Langkah 7. `FinancialReportService` masih dipakai halaman Ringkasan Laporan lama (angka lama, Kas = total aset); alihkan atau hapus.
- [ ] Dashboard: 8 kontrol integritas = jurnal seimbang, neraca seimbang, neraca tiap cabang, kas cashflow = kas neraca, laba rugi = laba berjalan neraca, piutang invoice vs buku besar, hutang dokumen vs buku besar, register aset vs buku besar. Tiga terakhir berstatus PERHATIAN (bukan gagal) bila selisih berasal dari saldo awal tanpa rincian dokumen. Konfirmasi daftar 8 kontrol dengan user (PRD tidak merincinya).
- [x] Laporan Piutang (`/accounting/aging`, gate C `[~]`): 10 kolom, ember ≤30 / 31–60 / >60, filter tanggal posisi, customer, cabang, dan status. Sisa = total − alokasi hidup sampai tanggal posisi. Rekonsiliasi membandingkan sisa invoice terbuka dengan saldo akun bermodul Piutang (`ReceivableAgingTest`). Di browser, `INV-2026-00001` sisa 100.000 sama dengan buku besar; filter Lunas menyembunyikan baris itu tanpa mengubah rekonsiliasi. Tautan invoice membuka halaman invoice.
- [x] Laporan Daftar Hutang (`/laporan/lap-hutang`, menu `pending` → `partial`, gate C `[~]`): beban, modal awal, saldo neraca, pinjaman, pembelian persediaan, dan pembelian aset secara utang. Sisa bayar hutang yang tidak menempel ke beban mengurangi dokumen non-beban tertua pada akun yang sama, termasuk pembelian persediaan dan aset. Selisih buku besar menjadi baris Tanpa dokumen. Identitas: sisa dokumen + tanpa dokumen = saldo akun Hutang dan Pinjaman (`PayableReportTest`, `InventoryFlowTest`). Di browser akun demo, ketiga angka rekonsiliasi 0. PPN tidak masuk laporan ini.
- [x] Input/Data/Laporan Persediaan (Langkah 5.1, gate C `[~]`): harga rata-rata per cabang, ongkir dan pajak masuk nilai stok, nomor `PRS-YYYY-00001`, bukti maks 5. Backdate ditolak bila masih ada gerakan hidup yang lebih baru pada cabang dan jenis yang sama. Hanya gerakan terakhir yang bisa dibalik; jurnal dibalik, tidak dihapus. Kartu stok dan rekonsiliasi ke akun bermodul Persediaan (`InventoryFlowTest`, `InventoryTest`). Di browser: pembelian kas `PRS-2026-00001` 10 × 10.000 + ongkir 500 + pajak 200 = 100.700; pemakaian 2 pada harga rata-rata 10.070 = 20.140 ke HPP, lalu dibalik sehingga gerakan aktif kembali 1 dan nilai stok = buku besar 100.700. Jenis persediaan demo `BIJI` (akun 1301 / HPP 5101, minimum 5) tetap ada.
- [x] Import Penjualan (Langkah 6.1, gate C `[~]`): template CSV/XLSX, pratinjau tanpa posting, satu invoice lunas per No Order, dedupe No Order yang masih hidup, pajak ke 2301, ongkir ke pendapatan lain, biaya admin jurnal pendamping, HPP harga rata-rata bila SKU cocok. Stok kurang menolak seluruh batch. Batal membalik invoice, admin, dan stok dari yang terakhir; ditolak bila ada pelunasan susulan atau gerakan stok lebih baru. Batas 2 MB dan 500 order. Nomor `IMP-YYYY-00001` (`ImportSalesTest`, ACC-11, ACC-12). Di browser: `IMP-2026-00001` tagihan 28.500, tampil di Data Pendapatan sebagai `INV-2026-00002`, lalu dibatalkan. Laporan Pajak dikerjakan di 6.2.
- [x] Input/Data/Simulasi/Laporan Aset (Langkah 5.2, gate C `[~]`): pembelian dari Template Jurnal (kas, bank, utang; kelompok bagian 5), nomor `AST-YYYY-00001`, bukti maks 5, residu 0. Penyusutan garis lurus mulai bulan setelah perolehan; sisa pembulatan jatuh di bulan terakhir. Tanah (umur 0) tidak disusutkan. Register 17 kolom. Penyusutan dibalik dari bulan terakhir, baru pembelian boleh dibalik. Aset modal awal masuk register tanpa jurnal kedua dan disusutkan setelah cut-off. Simulasi tiga tabel memposting bulan yang terlewat berurutan dan idempoten. Laporan merekonsiliasi harga dan akumulasi ke buku besar; selisih jurnal manual ditampilkan. Kontrol dashboard aset menaut ke `/laporan/lap-asset`. KPI total aset tidak diubah (`AssetFlowTest`, `FixedAssetTest`). Di browser: `AST-2026-00001` laptop 2.400.000 umur 4 tahun, pratinjau 50.000/bulan; posting Februari 50.000 selisih 0; laporan nilai buku 2.350.000; penyusutan lalu pembelian dibalik sehingga aset aktif 0 dan baris tetap tampil Dibalik.
- [x] Dashboard: KPI hutang tetap saldo akun bermodul Hutang. Top 5 memakai dokumen Laporan Daftar Hutang (beban, modal awal, saldo neraca, pinjaman), bukan baris tanpa dokumen. Kontrol integritas membandingkan sisa dokumen dengan saldo akun Hutang + Pinjaman dan menaut ke `/laporan/lap-hutang`.
- [ ] Dashboard: grafik SVG sederhana tanpa pustaka (tanpa Node di hosting). Drilldown rasio/anggaran/piutang per pelanggan menyusul.
- [ ] Skeleton perpindahan halaman (sesi 14-15) belum dicoba di browser. Aturan dari user: SETIAP ganti halaman harus menampilkan skeleton, di semua halaman (termasuk filter/pagination dan halaman Login). Cek: pindah menu dari halaman yang sudah di-scroll, submit filter, pagination, pindah antar halaman Login/Register, dan pastikan simpan/posting (POST/PATCH) TIDAK mengosongkan halaman. Belum ada test JS (tidak ada test runner front-end); logika murni: `replacesPage()` dan `variantFor()` di `navigation-loading.tsx`.
- [ ] Input/Data Beban gate C `[~]`: server dan UI dites (`ExpenseTest` 19 test), belum dicoba di browser. Pelunasan hutang beban ada di Input Pindah Saldo Kas/Bank jenis "Bayar hutang" (FIFO per akun, ADR-011, `CashBankTest`). `expensePayment()` lama tetap ada untuk kompatibilitas dan memposting jurnal sendiri; jalur Kas/Bank tidak memakainya.
- [ ] Input/Data Kas & Bank gate C `[~]`: server dites (`CashBankTest` 12 test, 11 jenis, FIFO, pembalikan, CSV). Di browser, transfer kas ke bank terposting lalu dibalik (total aktif kembali 0). FIFO hutang belum diulang di browser. Pembayaran di atas saldo buku besar ditolak; sisa di atas tagihan tetapi masih di dalam saldo buku besar (saldo awal/pinjaman) mengurangi GL tanpa alokasi dokumen.
- [ ] Beban: field vendor (ada di tabel) tidak muncul di form karena tidak ada di PRD; hutang per pemasok menunggu keputusan ADR-011.
- [ ] Komponen `RegisterTable` + `register-dialogs` dibuat di Data Beban; Data Pendapatan masih memakai tabel buatan sendiri. Pindahkan ke komponen bersama (hindari duplikasi) sebelum 7 halaman Data lain dibuat.
- [ ] Akun lawan beban ditentukan server dari Template Jurnal bagian 7 (`outflow_payment`), bukan dari browser; Invoice masih menerima akun dari form (`invoice_payment`): samakan perilakunya.
- [ ] Skeleton untuk menu yang sudah dibangun (sesi 17): sebelumnya hanya menu berjam yang menampilkannya. Cek di browser: klik menu yang sudah jadi (Data Pendapatan, Data Beban, Jurnal Umum, dst.) setelah kursor melintas di atasnya; skeleton harus tampil.
- [x] Template Jurnal per tab dengan tombol Tambah + dialog (sesi 19, masukan user). 10 tab dalam satu menu, tab aktif disimpan di URL (`?tab=`) tanpa request baru, baris tambah inline dihapus. Perlu dicek di browser. Periksa halaman lain yang masih punya form tambah inline sesuai `10-IMPLEMENTATION-UI-RULES.md` baris 9.
- [ ] **Header aplikasi berisi kontrol palsu**: dropdown "Semua cabang" dan "Periode berjalan", badge "Periode terbuka", dan tombol "+ Buat transaksi" di `app-sidebar-header.tsx` hanya hiasan (satu opsi, tidak memfilter apa pun, badge selalu hijau). Melanggar aturan "tidak ada kontrol yang pura-pura berfungsi". Pilihan: hapus, atau jadikan filter global sungguhan (cabang + periode terbaca server di semua laporan) dan badge periode dari data tahun buku.
- [x] Laporan Pajak (Langkah 6.2, gate C `[~]`): empat pajak (PPN 2301, PPh 21 2302, PPh 23 2303, PPh Badan 2304) per masa bulanan. Terutang = kredit akun utang pajak, setoran = debit, dialokasikan ke masa terlama; setoran tanpa masa terbuka menjadi kelebihan dan melunasi masa berikutnya. Jurnal yang sudah dibalik dan jurnal pembaliknya dikeluarkan berpasangan. Kredit pajak (1403 PPN Masukan, 1404 PPh 23 di muka) hanya informasi; jurnal kompensasi yang mendebit akun utang dihitung sebagai setoran. Rekonsiliasi ke buku besar per pajak (`TaxReportTest`). Jatuh tempo perkiraan: PPN akhir bulan berikutnya, PPh 21/23 tanggal 10, PPh Badan tanggal 15 (ADR-008, tinjau ke akuntan/regulasi). Belum ada form setoran pajak khusus: setoran dicatat lewat Input Jurnal Manual atau Pindah Saldo Kas/Bank. Belum dicoba di browser.
- [ ] Laporan Pajak: PPh 21 dan PPh Badan hanya terbaca bila jurnalnya memakai 2302/2304 (belum ada fitur penggajian atau hitung pajak badan). Konfirmasi dengan user apakah perlu form setoran/penyetoran pajak sendiri.
- [x] Anggaran (Langkah 7.1, gate C `[~]`): satu baris = cabang + akun 4/5 + kegiatan, tarif dan 12 Qty disimpan, nilai bulan = Qty x Tarif (`budget_monthly_lines.quantity`, `budgets.rate/unit`). Input, Data (ubah, hapus, CSV), Rekap (memakai `ProfitLossService::assemble`, struktur sama dengan Laba Rugi), Kinerja (anggaran vs jurnal per bulan, target 0 = N/A). Ambang warna: pendapatan/laba hijau >= 100%, kuning dalam 10 poin di bawah; beban hijau <= 100%, kuning sampai 110%; selebihnya merah. Konfirmasi ambang 10 poin dengan user (PRD hanya menyebut dekat/jauh). ACC-18 terbukti (anggaran 100 juta, aktual 80 juta = 80,00%, merah). Belum dicoba di browser.
- [ ] Anggaran: hapus baris anggaran langsung tanpa arsip (tidak memengaruhi jurnal). Tidak ada revisi/versi dan tidak ada kunci setelah tutup buku; putuskan di Langkah 8. Baris lama dari form draf (activity kosong, tarif 0) tetap terbaca, Qty 0.
- [ ] Anggaran: Rekap tanpa export dan tanpa drilldown akun; Kinerja belum menampilkan Qty/tarif per kegiatan. Kontrol dashboard dan kartu achievement anggaran masih 'Belum tersedia' (Langkah 7.2).
