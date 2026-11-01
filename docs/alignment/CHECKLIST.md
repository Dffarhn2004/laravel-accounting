# Checklist Penyelarasan (sumber kebenaran status)

Cara baca: `[x]` = **terbukti** (test lulus / sudah dicek). `[ ]` = belum. Jangan centang berdasarkan 'filenya ada'.

Gate (dari `reference-catatrapi/09-PARITY-CHECKLIST.md`): **A** navigasi · **B** UI/field/tabel/filter · **C** workflow end-to-end · **D** akuntansi & test · **E** responsif/aksesibel/cPanel.

Kolom *Halaman*: `partial` = ada halaman lama yang menutupi sebagian PRD (parity belum terverifikasi); `pending` = belum dibangun ('Belum tersedia'). Saat sebuah halaman lulus gate, update juga `config/navigation.php`.

Terakhir diperbarui: 2026-11-01 (Langkah 0 + Langkah 2 selesai; hardening awal Invoice)

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
| Import Penjualan (Kasir / Marketplace) | `/inputan/impor` | pending | [x] | [ ] | [ ] | [ ] | [ ] |
| Input Beban | `/transactions/expenses/create` | partial | [x] | [x] | [~] | [x] | [ ] |
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
| Data Pendapatan | `/transactions/invoices` | partial | [x] | [x] | [~] | [x] | [ ] |
| Data Beban | `/transactions/expenses` | partial | [x] | [x] | [~] | [x] | [ ] |
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
| Jurnal Umum | `/accounting/journals` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Laba Rugi per Cabang | `/reports/branch-profit-loss` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Laba Rugi Konsolidasi | `/reports/profit-loss` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Neraca | `/reports/balance-sheet` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Cashflow | `/reports/cash-flow` | partial | [x] | [x] | [~] | [x] | [ ] |
| Laporan Buku Besar | `/accounting/ledger` | partial | [x] | [x] | [~] | [x] | [ ] |
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
- [x] CRITICAL: KPI 'Kas & Setara Kas' hanya akun kas/bank, bukan agregat aset (`DashboardService`, `DashboardReportingTest`)
- [ ] CRITICAL: 19 rasio nyata, hapus placeholder 'Ratio 9-22', `FinancialRatioService`
- [ ] CRITICAL: Health score dari 19 rasio, bukan balance jurnal, `HealthScoreService`
- [ ] CRITICAL: Tutup buku carry-forward saldo/AR/AP/stok/aset + arsip JSON, `ClosingController`
- [ ] HIGH: Invoice lengkap: diskon, pendapatan lain (max 3), PPN, PPh23, uang muka, cetak, bukti (max 5), max 15 baris produk
- [x] HIGH: LR & Neraca hierarkis per akun, matriks Jan-Des, kolom per cabang (Langkah 3.4, `FinancialStatementsTest`)
- [x] HIGH: Dashboard filter global cabang+periode; blok terisi data nyata (blok yang bergantung Langkah 7 ditandai 'Belum tersedia', bukan angka palsu)
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
- [ ] Pengaturan & Backup: identitas usaha, rekening invoice, dan default PPN/PPh23 tersimpan dan dites. Backup/restore, data contoh, dan reset tetap menunggu ADR-006; tarif hanya konfigurasi usaha dan belum dipakai Input Invoice (ADR-008).
- [ ] Input Invoice gate C `[~]`/D `[~]`: server terbukti lewat `InvoiceInputTest` (13 test), tetapi form belum dicoba di browser dan alur ke laporan (JU/BB/LR/Neraca/Dashboard) belum ada; centang penuh setelah Langkah 3.3-3.5.
- [ ] Invoice: customer harus sudah ada di master (belum ada tambah customer cepat dari form).
- [ ] Invoice: pesan kesalahan bisnis dari `TransactionPostingService` masih berbahasa Inggris; terjemahkan.
- [ ] Invoice: belum ada koreksi/pembalikan dari UI (reversal + posting ulang); edit/hapus posted harus lewat pembalikan (ADR-010).
- [ ] Invoice: field "urutan" di PRD belum dipahami (tidak ada di UI); konfirmasi dengan user.
- [ ] Lampiran (`AttachmentService`, tabel `attachments`) siap dipakai Beban, Persediaan, Piutang, Aset, Kas/Bank, Modal, Jurnal Manual; belum dipasang di sana. Perlu juga hapus/tambah lampiran dari Data Pendapatan.
- [ ] PPh23: baru estimasi di invoice. Pencatatan saat pelanggan memotong (debit kas neto + 1404 PPh23 Dibayar di Muka, kredit piutang bruto) dikerjakan di Input Pelunasan Piutang (Langkah 4).
- [ ] Diskon dibukukan ke akun 5103 Beban Discount (sesuai kolom referensi Data Pendapatan). Mode lama (tanpa akun diskon, pendapatan netto) masih didukung service untuk kompatibilitas.
- [ ] Data Pendapatan gate C `[~]`: server dan UI dites (`RevenueRegisterTest` 13 test) tetapi belum dicoba di browser. Menurut PRD juga mencakup baris dari Import Penjualan; menunggu Langkah 6.
- [ ] Data Pendapatan: PPh 23 ditampilkan sebagai estimasi (redup, tooltip) dan kode 1404 karena belum dibukukan saat terbit (ADR-008). Konfirmasi tampilan ini dengan user.
- [ ] Data Pendapatan: tombol Edit pada referensi diganti "Koreksi: buat invoice baru" setelah membalik; belum ada edit-sekali-jalan (pembalikan + salin ke form baru).
- [ ] Pembalikan invoice menolak bila ada pelunasan susulan; pembatalan pelunasan dikerjakan di Langkah 4 (Input Pelunasan Piutang).
- [ ] Laporan lain yang menghitung piutang (`FinancialReportService`, `ReportController`) mengandalkan status `unpaid/partial`; periksa kembali saat laporan dirombak di Langkah 3.3-3.5 agar invoice `reversed` tak ikut.
- [ ] Jurnal Umum/Buku Besar gate C `[~]`: server dan UI dites (`GeneralLedgerReportsTest` 16 test), belum dicoba di browser. "Lihat baris, drilldown source" hanya aktif untuk invoice, beban, pelunasan piutang, dan pelunasan hutang beban (halaman sumber lain belum ada).
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
- [ ] Dashboard: hutang outstanding = saldo akun bermodul 'Hutang' di buku besar; top 5 hutang hanya dari Beban (hutang aset/persediaan/pinjaman menyusul Langkah 4-5).
- [ ] Dashboard: grafik SVG sederhana tanpa pustaka (tanpa Node di hosting). Drilldown rasio/anggaran/piutang per pelanggan menyusul.
- [ ] Skeleton perpindahan halaman (sesi 14-15) belum dicoba di browser. Aturan dari user: SETIAP ganti halaman harus menampilkan skeleton, di semua halaman (termasuk filter/pagination dan halaman Login). Cek: pindah menu dari halaman yang sudah di-scroll, submit filter, pagination, pindah antar halaman Login/Register, dan pastikan simpan/posting (POST/PATCH) TIDAK mengosongkan halaman. Belum ada test JS (tidak ada test runner front-end); logika murni: `replacesPage()` dan `variantFor()` di `navigation-loading.tsx`.
- [ ] Input/Data Beban gate C `[~]`: server dan UI dites (`ExpenseTest` 19 test), belum dicoba di browser. Beban dibayar-nanti (Utang Usaha) sudah tercatat sebagai hutang terbuka, tetapi **pelunasannya belum punya UI**: dikerjakan di Input Pindah Saldo Kas/Bank, jenis "Bayar hutang" (Langkah 4.2) dengan keputusan ADR-011 (FIFO per akun vs pencocokan bill). `TransactionPostingService::expensePayment()` sudah ada.
- [ ] Beban: field vendor (ada di tabel) tidak muncul di form karena tidak ada di PRD; hutang per pemasok menunggu keputusan ADR-011.
- [ ] Komponen `RegisterTable` + `register-dialogs` dibuat di Data Beban; Data Pendapatan masih memakai tabel buatan sendiri. Pindahkan ke komponen bersama (hindari duplikasi) sebelum 7 halaman Data lain dibuat.
- [ ] Akun lawan beban ditentukan server dari Template Jurnal bagian 7 (`outflow_payment`), bukan dari browser; Invoice masih menerima akun dari form (`invoice_payment`): samakan perilakunya.
- [ ] Skeleton untuk menu yang sudah dibangun (sesi 17): sebelumnya hanya menu berjam yang menampilkannya. Cek di browser: klik menu yang sudah jadi (Data Pendapatan, Data Beban, Jurnal Umum, dst.) setelah kursor melintas di atasnya; skeleton harus tampil.
- [x] Template Jurnal per tab dengan tombol Tambah + dialog (sesi 19, masukan user). 10 tab dalam satu menu, tab aktif disimpan di URL (`?tab=`) tanpa request baru, baris tambah inline dihapus. Perlu dicek di browser. Periksa halaman lain yang masih punya form tambah inline sesuai `10-IMPLEMENTATION-UI-RULES.md` baris 9.
- [ ] **Header aplikasi berisi kontrol palsu**: dropdown "Semua cabang" dan "Periode berjalan", badge "Periode terbuka", dan tombol "+ Buat transaksi" di `app-sidebar-header.tsx` hanya hiasan (satu opsi, tidak memfilter apa pun, badge selalu hijau). Melanggar aturan "tidak ada kontrol yang pura-pura berfungsi". Pilihan: hapus, atau jadikan filter global sungguhan (cabang + periode terbaca server di semua laporan) dan badge periode dari data tahun buku.
