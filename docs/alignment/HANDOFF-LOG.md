# Handoff Log (terbaru di atas)

Format entri: tanggal · agent/orang · apa yang dikerjakan · status test · hal yang menggantung · saran langkah berikut.

---
## 2026-11-04 · Claude (Sonnet 5.5) · Langkah 7.1 Anggaran
- Empat menu Anggaran aktif (`ang-data`, `ang-rekap`, `ang-kinerja` `pending` → `partial`; Input sudah partial). Migrasi `2026_11_04_030000_add_quantity_and_rate_to_budgets` (kolom `unit`, `rate`, `quantity`) sudah dijalankan di dev DB.
- `BudgetPlanService` (simpan, daftar, ringkasan cabang, rekap, kinerja, kelas pencapaian) dan `BudgetController` ditulis ulang. Form lama (12 nominal, draf) diganti Qty x Tarif; baris tersimpan berstatus `published` agar `BudgetReportService::compare` lama tetap bekerja. `BudgetService::create` lama dan `BudgetTest` tidak diubah.
- Rekap memakai `ProfitLossService::assemble` (kini `public`) sehingga strukturnya sama dengan Laba Rugi. Kinerja: target 0 = N/A, tanpa pembagian nol. Panel 'Rasio' placeholder di halaman Input dihapus (rasio nyata di 7.2).
- Verifikasi: `BudgetPlanTest` 7/7, seluruh suite 261/261 (2608 assertion), `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Belum dicoba di browser. Belum di-commit.
- Terbuka: ambang kuning 10 poin (konfirmasi user); hapus anggaran tanpa arsip; kartu achievement anggaran di Dashboard masih 'Belum tersedia'.
- Langkah berikut: 7.2 Analisis Rasio (19 rasio, health score) dan Bagi Hasil & Dividen. Tetap disarankan commit pekerjaan Langkah 1-7.1.

---
## 2026-11-04 · Claude (Sonnet 5.5) · Langkah 6.2 Laporan Pajak
- Menu `/laporan/lap-pajak` `pending` → `partial`. `TaxReportService` + `TaxReportController` (index + export CSV) + `accounting/lap-pajak.tsx`. Filter tahun, cabang, jenis pajak, masa, status (semua / belum lunas / lunas / lewat perkiraan jatuh tempo).
- Blok A ringkasan per pajak (PPN 2301, PPh 21 2302, PPh 23 2303, PPh Badan 2304), B status per masa, C buku pembantu (maks 500 baris, tautan ke sumber), D beban pajak akun 55xx per bulan. Rekonsiliasi per pajak ke buku besar.
- Aturan: terutang = kredit akun utang pajak per bulan, setoran = debit, FIFO ke masa terlama, kelebihan menunggu dan melunasi masa berikutnya. Jurnal yang dibalik dan jurnal pembaliknya dikeluarkan berpasangan. 1403/1404 hanya informasi. Jatuh tempo perkiraan, diberi label di UI dan CSV.
- Verifikasi: `TaxReportTest` 5/5, seluruh suite 254/254 (2535 assertion), `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Belum dicoba di browser. Tidak ada migrasi. Belum di-commit.
- Terbuka: tidak ada form setoran pajak khusus (pakai Jurnal Manual / Kas Bank); PPh 21 dan PPh Badan hanya terbaca bila jurnalnya memakai 2302/2304; tarif dan jatuh tempo perlu tinjauan akuntan.
- Langkah berikut: Langkah 7 Anggaran, Rasio, Dividen. Disarankan commit dulu pekerjaan Langkah 1-6.

---
## 2026-11-04 · Grok · Langkah 6.1 Import Penjualan
- Menu `/inputan/impor` `pending` → `partial`. Template CSV dan XLSX. Pratinjau tidak memposting; posting membaca berkas lagi. Satu No Order = satu invoice `INV-…` lunas ke akun penerimaan. Pajak di berkas dikredit ke 2301, ongkir ke akun pendapatan lain, biaya admin jurnal pendamping (debit beban, kredit akun penerimaan). HPP hanya bila SKU cocok, sebesar harga rata-rata cabang, bukan angka di berkas. No Order yang masih hidup dilewati. Batas 2 MB dan 500 order. Nomor batch `IMP-YYYY-00001`.
- Batal batch membalik invoice, jurnal admin, dan pemakaian stok dari yang terakhir. Ditolak bila ada pelunasan susulan atau gerakan stok yang lebih baru. Jurnal dibalik, tidak dihapus. Laporan Pajak tetap `pending` (Langkah 6.2).
- Verifikasi: `ImportSalesTest` 12/12, seluruh suite 249/249 (2484 assertion) sebelum penyesuaian pratinjau; tes pratinjau diulang setelah pratinjau mengembalikan halaman yang sama supaya berkas tetap terpilih. `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Migrasi `2026_11_04_020000_create_import_sales_tables` sudah dijalankan di MySQL. Di browser (akun demo): template CSV terbaca, pratinjau `IMP-DEMO-1` tagihan 28.500, posting `IMP-2026-00001`, Data Pendapatan `INV-2026-00002` (pendapatan 26.000, PPN 2.500, kas 28.500), lalu batch dibatalkan. Belum di-commit.
- Dev DB: customer `Pelanggan Impor` dan invoice `INV-2026-00002` tetap ada sebagai baris dibalik. Batch `IMP-2026-00001` status dibatalkan.
- Langkah berikut: Langkah 6.2 Laporan Pajak (blok A-D, alokasi ke masa tertua; jatuh tempo hanya perkiraan, ADR-008).

---
## 2026-11-04 · Grok · Langkah 5.2 Aset dan D&A
- Input `/transactions/fixed-assets` membaca jenis bayar (bagian 7) dan kelompok aset (bagian 5). Nomor `AST-YYYY-00001`, bukti maks 5, idempoten. Jurnal: debit akun aset, kredit kas/bank/hutang. Utang masuk daftar hutang dan ikut FIFO. Tanah (umur 0) tidak disusutkan. Residu tetap 0.
- Penyusutan garis lurus mulai bulan setelah perolehan. Satu baris posting per aset per bulan (unik `fixed_asset_id` + `period_month`, plus `journal_entry_id`). Posting simulasi mengisi bulan yang terlewat berurutan dan tidak mengulang bulan yang sudah ada. Pembalikan penyusutan dari bulan terakhir; pembelian baru boleh dibalik setelah itu. Jurnal dibalik, tidak dihapus.
- Register `/data/d-asset` 17 kolom, CSV, bukti. Aset cut-off modal awal masuk register (sumber Modal) tanpa jurnal perolehan kedua; penyusutan lanjut setelah bulan cut-off. Membalik setoran ditolak bila penyusutan aset itu masih hidup.
- Simulasi `/data/simulasi` tiga tabel (jadwal, rekap beban, jurnal belum terposting) plus rekonsiliasi. Laporan `/laporan/lap-asset` merekonsiliasi harga dan akumulasi ke buku besar dan menampilkan selisih jurnal manual. Kontrol dashboard aset menaut ke laporan ini. KPI total aset tidak diubah. Menu Data Asset, Simulasi, dan Laporan Daftar Asset `pending` → `partial`.
- Verifikasi: `FixedAssetTest` + `AssetFlowTest` 8/8, seluruh suite 237/237 (2397 assertion), `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Di browser (akun demo): `AST-2026-00001` laptop kas 2.400.000 (1505, 4 tahun), pratinjau 50.000/bulan; posting Februari 50.000 sama dengan buku besar; laporan nilai buku 2.350.000 dan selisih harga serta akumulasi 0; penyusutan lalu pembelian dibalik sehingga aset aktif 0 dan baris tetap tampil Dibalik. Belum di-commit.
- Dev DB: migrasi `2026_11_04_010000_extend_fixed_assets_for_register` sudah dijalankan. `AST-2026-00001` tetap di data demo sebagai baris Dibalik.
- Langkah berikut: Langkah 6 Import Penjualan dan Pajak.

---
## 2026-11-03 · Grok · Langkah 5.1 Persediaan
- Backdate persediaan diputuskan: tolak. Gerakan yang tanggalnya lebih awal daripada gerakan hidup terakhir pada cabang dan jenis yang sama ditolak. HPP tidak dihitung ulang.
- Input `/inputan/persediaan` (kas, bank, utang, pemakaian), nomor `PRS-YYYY-00001`, ongkir dan pajak masuk nilai stok, bukti maks 5. Register `/data/d-persediaan` 19 kolom, CSV, pembalikan hanya gerakan terakhir. Laporan `/accounting/inventory` punya ringkasan, kartu stok, stok minimum, dan rekonsiliasi ke akun persediaan. Pembelian utang masuk daftar hutang. Menu Input dan Data Persediaan `pending` → `partial`.
- Verifikasi: `InventoryTest` + `InventoryFlowTest` 5/5, seluruh suite 231/231 (2268 assertion), `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Di browser (akun demo): jenis `BIJI` dibuat di Template Jurnal; pembelian kas `PRS-2026-00001` 100.700; pemakaian 2 pada rata-rata 10.070 masuk HPP; kartu stok qty akhir 8 dan selisih 0; pemakaian dibalik sehingga gerakan aktif 1 dan nilai stok = buku besar 100.700. Belum di-commit.
- Dev DB: migrasi `2026_11_03_160000_add_inventory_movement_document_columns` sudah dijalankan. Jenis `BIJI` dan pembelian `PRS-2026-00001` tetap di data demo; pemakaian `PRS-2026-00002` tampil sebagai Dibalik.
- Langkah berikut: Langkah 5.2 Input Pembelian Asset, Data Asset, Simulasi & Output D&A, dan Laporan Daftar Asset.

---
## 2026-11-03 · Grok · Langkah 4.5 Laporan Piutang + Laporan Daftar Hutang
- Laporan Piutang di `/accounting/aging`: satu baris per invoice, ember ≤30 / 31–60 / >60, filter, tautan invoice, dan rekonsiliasi ke akun bermodul Piutang. Filter status hanya mengubah baris, bukan kartu umur atau rekonsiliasi.
- Laporan Daftar Hutang di `/laporan/lap-hutang` (menu `pending` → `partial`): beban, modal awal, saldo neraca, dan pinjaman. Sisa bayar hutang yang tidak teralokasi mengurangi dokumen non-beban tertua pada akun yang sama. Selisih buku besar jadi baris Tanpa dokumen. PPN tidak masuk. Dashboard menaut kontrol hutang ke halaman ini; top 5 memakai dokumen yang sama. KPI hutang tidak diubah.
- Verifikasi: `ReceivableAgingTest` 3/3, `PayableReportTest` 2/2, seluruh suite 227/227, `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Di browser (akun demo): `INV-2026-00001` sisa 100.000 sama dengan buku besar dan tautannya membuka invoice; filter Lunas mengosongkan tabel tanpa mengubah rekonsiliasi. Laporan hutang dan filter akun 2101 menampilkan dokumen 0, tanpa dokumen 0, buku besar 0. Belum di-commit.
- Langkah berikut: Langkah 5 Persediaan, Aset, dan D&A.

---
## 2026-11-03 · Grok · Langkah 4.4 Input Jurnal Manual + Data Jurnal Manual + Data Modal Awal
- Form `/accounting/manual-journal` diganti: 2–6 baris, tahun buku dari tanggal, nomor `JM-YYYY-00001`, bukti, idempoten. Register `/data/d-jm` 12 kolom, CSV, bukti, dan pembalikan seluruh jurnal. Menu Data Jurnal Manual `pending` → `partial`.
- Register `/data/d-modal` hanya setoran modal awal, 16 kolom. Baris plug modal pemilik tidak digandakan; akumulasi aset jadi baris kredit sendiri. Pembalikan melepas kunci idempoten sehingga cabang boleh menyetor ulang. Saldo Neraca tidak muncul dan tidak bisa dibalik dari menu ini. Menu Data Modal Awal `pending` → `partial`.
- Verifikasi: `ManualJournalTest` 5/5, `OpeningCapitalRegisterTest` 3/3, seluruh suite 222/222, `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Di browser (akun demo): jurnal `JM-2026-00001` 25.000 masuk register (debit = kredit), lalu dibalik sehingga jurnal aktif 0. Setoran kas 100.000 cabang Main Branch masuk Data Modal Awal, lalu dibalik sehingga setoran aktif 0. Kedua baris cek tetap tampil sebagai Dibalik. Belum di-commit.
- Dev DB: migrasi `2026_11_03_140000_add_opening_reversal_columns` sudah dijalankan.
- Langkah berikut: 4.5 Laporan Piutang (aging) dan Laporan Daftar Hutang.

---
## 2026-11-03 · Grok · Langkah 4.3 Input Pelunasan Piutang + Data Pelunasan Piutang
- Form `/transactions/receivables` memilih satu invoice terbuka. Cabang dan akun piutang diambil dari invoice, bukan dari browser. Jurnal biasa: debit kas/bank, kredit piutang. PPh 23 opsional (ADR-008): debit kas neto + debit 1404, kredit piutang bruto. Nomor `PLT-YYYY-00001`. Pembalikan mengembalikan status invoice sehingga invoice boleh dibalik. Route lama `POST /accounting/receipts` tetap.
- Register `/data/d-piutang` 16 kolom, filter, CSV, bukti, total tanpa baris dibalik. Menu Data Pelunasan Piutang `pending` → `partial`. Laporan Piutang (aging) belum dikerjakan.
- Verifikasi: `ReceivableSettlementTest` 6/6, seluruh suite 214/214, `tsc` bersih, build OK, `route:cache` lalu `route:clear` OK. Di browser (akun demo): cicilan 40.000 atas `INV-2026-00001` masuk register sebagai `PLT-2026-00001` (jumlah diterima 40.000), lalu dibalik sehingga pelunasan aktif 0. PPh 23 tiga baris jurnal dibuktikan test, belum diulang di browser. Invoice demo itu tetap terbuka (unpaid) dan baris pelunasan tetap tampil sebagai Dibalik. Belum di-commit.
- Dev DB: migrasi `2026_11_03_130000_add_receivable_settlement_columns` sudah dijalankan.
- Langkah berikut: 4.4 Input Jurnal Manual + Data Jurnal Manual, plus Data Modal Awal.

---
## 2026-11-03 · Grok · Langkah 4.2 Input Pindah Saldo Kas/Bank + Data Kas & Bank
- ADR-011 diputuskan FIFO per akun (user). Bayar hutang tidak memilih tagihan: hutang beban tertua pada akun yang sama dilunasi dulu, satu jurnal. Sisa tanpa dokumen (saldo awal/pinjaman) mengurangi buku besar selama tidak melebihi saldo akun itu.
- `TreasuryPostingService::move()` / `reverse()`, register `/data/d-kasbank`, form `/transactions/cash-bank` (11 jenis dari Template Jurnal). Route lama `/accounting/treasury` tetap. Menu Data Kas & Bank `pending` → `partial`.
- Verifikasi: `CashBankTest` 12/12, seluruh suite 208/208, `tsc` bersih, build OK, `route:cache` OK. Di browser (akun demo): form 11 jenis, akun Ke menyembunyikan akun Dari yang sama, pratinjau sisa, posting transfer `KAS-2026-00001` masuk register (debit = kredit 1.000), lalu dibalik sehingga total aktif 0. FIFO hutang dibuktikan test, belum diulang di browser. Transaksi cek itu tetap di data demo sebagai baris Dibalik (jurnal bersih nol). Belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 4.3 Input Pelunasan Piutang + Data Pelunasan (cabang mengikuti invoice, tidak melebihi sisa).

---
## 2026-11-03 · Grok · Tinjauan status (tanpa perubahan kode)
- User meminta cek apa yang masih kurang. Tidak ada perubahan fitur, test, atau centang checklist.
- Posisi: Langkah 1–2 selesai; Langkah 3 (Invoice sampai Dashboard) dan Langkah 4.1 (Beban) selesai di server+UI, belum dicoba di browser, belum di-commit. Langkah 4.2–9 belum.
- 19 menu masih `pending` ("Belum tersedia"). Gate E kosong di semua halaman. Kontrol palsu di header masih terbuka.
- Saran langkah berikut: cek browser halaman Langkah 1–4.1, lalu putuskan ADR-011 sebelum Langkah 4.2 (bayar hutang).

---
## 2026-11-01 (sesi 19) · Claude (Sonnet 5.5) · Template Jurnal: tab + tombol Tambah (dialog)
- Masukan user (dengan tangkapan layar): 10 bagian Template Jurnal tampil sekaligus dalam satu halaman panjang; seharusnya per tab, dan tiap tab punya tombol Tambah, bukan baris input di dasar tabel (melanggar `10-IMPLEMENTATION-UI-RULES.md` baris 9).
- `journal-template.tsx` ditulis ulang: 10 tab (`role=tablist`, panah kiri/kanan, tab aktif di URL lewat `history.replaceState` agar tidak memicu request atau skeleton), hanya isi tab aktif yang dirender; tombol **Tambah** per tab membuka dialog (`AddDialog`) berisi field tambah; baris tambah inline dihapus; edit baris yang ada tetap inline dengan tombol Simpan; keadaan kosong menjelaskan langkah berikutnya. Backend dan route tidak berubah.
- Verifikasi: `tsc` bersih, build OK, `JournalTemplateTest` 12/12 lulus. Belum dicoba di browser, belum di-commit.
- Masih terbuka (belum dikerjakan, menunggu keputusan user): kontrol palsu di header aplikasi (dropdown cabang/periode, badge periode, "+ Buat transaksi"); ADR-011 (alokasi pembayaran hutang) sebelum Langkah 4.2.

---
## 2026-11-01 (sesi 17) · Claude (Sonnet 5.5) · Skeleton belum tampil di menu yang sudah dibangun
- Laporan user (dengan tangkapan layar sidebar): skeleton benar untuk menu berjam (halaman pending) tetapi tidak muncul untuk menu yang sudah dibangun.
- Akar masalah: `nav-main.tsx` memberi `prefetch` pada menu yang sudah dibangun dan tidak pada menu pending. Klik pada menu ber-prefetch dilayani dari cache; `navigation-loading.tsx` sendiri mengecualikan kunjungan `cached` (kesalahan saya di sesi 14-15), dan event `start` tidak selalu terpicu untuk kunjungan cache.
- Perbaikan: skeleton dipicu dari event `before` (selalu terpicu, termasuk dari cache), pengecualian `cached` dihapus (permintaan prefetch latar belakang tetap dikecualikan lewat `visit.prefetch`); disembunyikan oleh `finish`/`cancel`/`navigate`/`error`/`networkError`/`httpException` dengan tampil minimal 250 ms; pengaman 20 detik bila kunjungan berakhir tanpa event.
- Verifikasi: `tsc` bersih, build OK. Belum dicoba di browser, belum di-commit.

---
## 2026-11-01 (sesi 16) · Claude (Sonnet 5.5) · Langkah 4.1 Input Beban + Data Beban
- Migrasi `2026_11_01_080000`: `expenses.description`, `payment_method`, kolom pembalikan (`reversal_journal_entry_id`, `reversal_reason`, `reversed_at`).
- `TransactionPostingService::expense()` ditulis ulang: akun dicek company dan tipe (beban = expense, kas/bank = asset, hutang = liability), tahun buku harus terbuka, referensi otomatis `BBN-YYYY-00001` (jadi "No Trx" di Jurnal Umum), journal_entry_id tersimpan, whitelist kolom. `reverseExpense()` ditulis ulang: alasan wajib, tanggal >= tanggal beban, tahun buku terbuka, ditolak bila sudah ada pelunasan hutang atau sudah dibalik.
- `ExpenseRequest`: validasi company-scoped; metode bayar harus ada di Template Jurnal bagian 7; **akun lawan ditentukan server** dari pemetaan template (asset -> lunas, liability -> hutang terbuka), tidak pernah dari browser. `TransactionController::storeExpense` mencari tahun buku dari tanggal, mengikat cabang untuk user terikat, posting + lampiran dalam satu transaksi DB.
- Data Beban: `ExpenseRegisterService` + `ExpenseRegisterController` (route `transactions.expenses.index/export/reverse/attachments.store`), 14 kolom PRD, filter cari/cabang/tanggal, total dari seluruh hasil filter tanpa beban dibalik, CSV 14 kolom + Status (anti formula injection), pembalikan, bukti. Total register = jumlah debit jurnal beban (diuji).
- UI: `expense-form.tsx` (halaman sendiri, status lunas/hutang, bukti, reset, EDIT DATA), `data-beban.tsx`, `expense-detail.tsx` diperkaya. Komponen bersama baru `register-table.tsx` (TanStack, kolom lengket, visibilitas kolom) dan `register-dialogs.tsx` (ReverseDialog, EvidenceDialog) untuk 7 halaman Data berikutnya. `expenses.tsx` lama dihapus.
- Test: `ExpenseTest` 19 test; total 196/196 lulus, `tsc` bersih, build OK, `route:cache` OK. Belum dicoba di browser, belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 4.2 Input Pindah Saldo Kas/Bank (11 jenis dari Template Jurnal bagian 8-9, termasuk bayar hutang beban -> perlu keputusan ADR-011) + Data Kas & Bank.

---
## 2026-11-01 (sesi 15) · Claude (Sonnet 5.5) · Skeleton di SETIAP ganti halaman (koreksi sesi 14)
- Aturan dari user (jawaban eksplisit): setiap ganti halaman harus ada loading state, dan loading state-nya adalah skeleton, di semua halaman. Sesi 14 terlalu membatasi (mengecualikan filter/pagination dan memakai jeda 150 ms) sehingga banyak perpindahan tanpa skeleton; itu dikoreksi.
- Kini: skeleton muncul seketika pada setiap kunjungan GET yang mengganti isi halaman (pindah menu, pagination, submit filter), tahan minimal 250 ms agar tidak berkedip, scroll ke atas dulu. Dikecualikan: POST/PATCH/DELETE (tombol punya status sibuk sendiri; kalau tidak, form yang sedang diisi ikut kosong), kunjungan prefetch/cache (instan), partial reload, dan request latar belakang.
- Cakupan layout: `NavigationLoading` kini juga dipasang di layout auth (Login, Register, lupa/atur ulang sandi, verifikasi) dengan skeleton kartu tengah, selain layout utama (semua halaman aplikasi, termasuk Pengaturan). Halaman Welcome tidak memakai layout apa pun dan tidak dicakup.
- Bentuk skeleton: auth, dashboard, form, pengaturan, tabel (dipilih dari path tujuan).
- Verifikasi: `tsc` dan build (lihat hasil sesi). Belum dicoba di browser, belum di-commit.

---
## 2026-11-01 (sesi 14) · Claude (Sonnet 5.5) · Perbaikan skeleton perpindahan menu
- Laporan user: penempatan skeleton saat pindah menu terasa salah. Audit `navigation-loading.tsx` menemukan: (1) tampil untuk SETIAP kunjungan Inertia (event `before`): submit filter, pagination, POST/PATCH (posting invoice, simpan baris Template Jurnal, nonaktifkan akun) ikut mengosongkan halaman yang sedang dikerjakan; (2) tanpa jeda sehingga berkedip pada navigasi cepat; (3) overlay `absolute inset-0` dengan skeleton di puncak dan latar 95% transparan: pada halaman panjang yang sudah di-scroll, skeleton berada di luar layar dan konten lama tembus di bawahnya; (4) satu bentuk skeleton tabel untuk semua tujuan dengan lebar 1440px, padahal tujuan bisa dashboard/form/pengaturan dengan lebar berbeda sehingga halaman asli "melompat" saat muncul.
- Perbaikan: skeleton hanya untuk perpindahan halaman nyata (GET ke path lain; bukan filter/pagination/simpan/prefetch/partial reload/polling) memakai event `start` bukan `before`; tampil setelah 150 ms dan bertahan minimal 250 ms; window di-scroll ke atas sebelum tampil; latar opaque; empat bentuk skeleton sesuai tujuan (dashboard, form, pengaturan, tabel) dengan lebar mendekati halaman asli; `role=status`, `aria-busy`, hormati `prefers-reduced-motion`.
- Verifikasi: `tsc` bersih, build OK. Tidak ada perubahan PHP. Belum dicoba di browser, belum di-commit.

---
## 2026-11-01 (sesi 13) · Claude (Sonnet 5.5) · Langkah 3.5 Dashboard; Langkah 3 selesai
- `DashboardService` + `DashboardController` ditulis ulang. Enam KPI dari buku besar: Pendapatan dan Laba Bersih (periode), Kas (hanya akun kas/bank), Total Aset, Piutang, Hutang (saldo akhir periode). Filter cabang + periode awal/akhir diterapkan server side ke semua angka; user terikat cabang tidak bisa memperlebar cakupan.
- Blok: tren pendapatan/beban/laba 12 bulan, tren kas akhir bulan, arus kas per aktivitas, komposisi beban dan aset, kinerja tiap cabang, matriks laba rugi 12 bulan, top 5 piutang (per akhir periode) dan hutang.
- 8 kontrol integritas (ok/warn/fail) membandingkan sumber independen; status VALID / PERHATIAN / TIDAK VALID / BELUM ADA DATA. Perusahaan kosong tidak pernah dinyatakan VALID.
- Yang belum ada tidak dipalsukan: health score 19 rasio, rasio kunci, achievement anggaran, proyeksi dividen tampil sebagai kartu 'Belum tersedia' menuju menu terkait. `HealthScoreService` lama (skor 100 dari jurnal seimbang) tidak lagi dipakai.
- UI: `dashboard.tsx` baru, `components/charts.tsx` (SVG tanpa dependensi + tabel sr-only), setiap KPI menaut ke laporan sumbernya.
- Test: `DashboardReportingTest` 11 test (skenario berangka: kas 10.100.000 vs aset 10.266.400, filter periode/cabang, kontrol, buku rusak -> TIDAK VALID, saldo awal piutang -> PERHATIAN, perusahaan kosong). Total 177/177 lulus, `tsc` bersih, build OK, `route:cache` OK. Belum dicoba di browser. Belum di-commit.
- Langkah 3 (Invoice -> Data Pendapatan -> Jurnal Umum/Buku Besar -> LR/Neraca/Cashflow -> Dashboard) selesai di server+UI. ACC-01..04, 14, 16, 22 terbukti test; ACC-21 (cetak A4) baru pratinjau.
- Langkah berikut: sebelum lanjut, SANGAT disarankan cek browser semua halaman Langkah 1-3 dan commit. Lalu Langkah 4 (Beban, Kas/Bank, Piutang, Jurnal Manual + Data masing-masing, Laporan Piutang/Hutang).

---
## 2026-11-01 (sesi 12) · Claude (Sonnet 5.5) · Langkah 3.4 Laba Rugi, Neraca, Cashflow
- `ReportStructure`: klasifikasi akun ke seksi Laba Rugi berdasarkan awalan kode; bucket cashflow berdasarkan modul akun.
- `ProfitLossService`: satu bentuk laporan, dua set kolom (12 bulan atau per cabang). Pendapatan Operasional -> Direct Cost -> Gross Margin -> Overhead -> EBITDA -> Depreciation -> EBIT -> Non-Operasional -> EBT -> Pajak -> Laba Bersih; % Rasio terhadap pendapatan operasional (N/A bila 0). Akun tanpa mutasi disembunyikan kecuali "tampilkan semua".
- `BalanceSheetService`: posisi awal (sebelum periode + jurnal saldo awal yang jatuh di periode) dan posisi akhir, dikelompokkan per modul, growth (N/A bila awal 0). Karena belum ada jurnal penutup, ditambah baris terhitung "Laba Ditahan s.d. akhir tahun lalu" dan "Laba Tahun Berjalan" (tahun acuan kolom awal mengikuti Periode Awal) sehingga Aktiva = Pasiva. Bug ditangkap test: laba tahun lalu sempat tampil sebagai laba berjalan di kolom awal.
- `CashFlowService` (tidak langsung): efek kas tiap akun neraca non-kas = -(perubahan saldo debit-kredit); Delta kas = laba bersih + semua efek, identitas yang pasti benar karena setiap jurnal seimbang. Kas = modul "Kas & Bank" dan akun CashAccount. Jurnal saldo awal pada periode bukan arus kas. Rekonsiliasi kas akhir vs kas Neraca ditampilkan.
- Halaman: `profit-loss`, `branch-profit-loss`, `balance-sheet`, `cash-flow` memakai `ReportMatrix` (hierarkis, kolom Kode/Uraian lengket, negatif dalam kurung, klik akun -> Buku Besar dengan periode/cabang terbawa) dan `ReportShell` (filter, cetak). Route baru `reports/branch-profit-loss` dan `reports/cash-flow`; `config/navigation.php` LR per Cabang dan Cashflow pending -> partial. `financial-statement.tsx` lama dihapus.
- Test `FinancialStatementsTest` (15): satu skenario dengan angka dihitung tangan (dua cabang, saldo awal, laba 2025 yang belum ditutup, penyusutan, pajak, pinjaman, prive, beli aset, bayar hutang) membuktikan: Laba Bersih 202.000; Aktiva = Pasiva di posisi awal (26jt) dan akhir (30.754.800), per cabang juga; kolom cabang = konsolidasi di setiap baris; Cashflow operasi -828.000, investasi -4jt, pendanaan 5,5jt, kenaikan kas 672.000 = kas Neraca; rekonsiliasi benar untuk beberapa periode dan cabang.
- Test: 166/166 lulus, `tsc` bersih, build OK, `route:cache` OK. Belum dicoba di browser. Belum di-commit.
- Langkah berikut: 3.5 Dashboard (6 KPI dengan Kas = akun kas/bank saja, filter global cabang+periode, 8 kontrol integritas, blok sekunder dari data nyata), mengganti `FinancialReportService` lama.

---
## 2026-11-01 (sesi 11) · Claude (Sonnet 5.5) · Langkah 3.3 Jurnal Umum + Buku Besar
- Migrasi `2026_11_01_070000`: `journal_entries.source_kind` + `reference` (+ backfill untuk sumber yang tak ambigu). Semua titik posting kini mengisi jenis sumber: invoice, receipt, expense, expense_payment, inventory, asset, depreciation, treasury, opening, manual, reversal (`JournalSource::LABELS`). Sebelumnya semua posting transaksi berbagi `source_type` yang sama sehingga tak bisa dibedakan.
- `LedgerService` (read model, satu-satunya tempat query laporan buku besar): `lines()`, `totals()`, `netBefore()` (basis saldo berjalan untuk halaman terpaginasi), `summary()` (saldo awal/debit/kredit/saldo akhir seluruh akun, satu query agregat), `counterAccounts()`, `sourceUrls()`. Saldo pada sisi normal akun: aset dan beban debit; kewajiban, ekuitas, pendapatan kredit; akun `is_contra` membalik.
- Jurnal Umum (`/accounting/journals`): 12 kolom PRD, filter cabang/sumber/tahun/bulan/cari (server side, divalidasi), total debit/kredit/selisih + badge Seimbang, drilldown ke dokumen sumber, export CSV (saldo berjalan ikut, anti formula injection), user terikat cabang hanya melihat cabangnya.
- Buku Besar (`/accounting/ledger`): dua bagian di satu halaman sesuai PRD (detail akun: Tanggal | No Trx | Sumber | Keterangan | Akun Lawan | Debit | Kredit | Saldo, 100 baris/halaman dengan saldo yang benar di halaman ke-2 dst; ringkasan seluruh akun), periode awal/akhir, export CSV ringkasan dan detail.
- Bug yang ditangkap test: SQLite menyimpan `posted_on` sebagai datetime string sehingga basis saldo ikut menghitung baris itu sendiri; diperbaiki dengan membandingkan tanggal saja.
- `App\Support\Csv` (netralisasi formula) dipakai bersama oleh export Data Pendapatan, Jurnal Umum, Buku Besar.
- Test: 151/151 lulus (`GeneralLedgerReportsTest` 16 test baru), `tsc` bersih, build OK, `route:cache` OK. Belum dicoba di browser. Belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 3.4 Laporan Laba Rugi (konsolidasi matriks Jan-Des + per cabang), Neraca, Cashflow; memakai `LedgerService`/query agregat, hierarki akun dan subtotal GM/EBITDA/EBIT/EBT.

---
## 2026-11-01 (sesi 10) · Claude (Sonnet 5.5) · Langkah 3.2 Data Pendapatan
- `RevenueRegisterService`: satu baris per produk dan per pendapatan lain, 26 kolom sesuai urutan PRD (`COLUMNS`). Nilai tingkat invoice (Bank/Cash, PPh 23, PPN, Piutang) hanya di baris pertama agar penjumlahan tidak ganda; Total Pendapatan = bruto per baris, Beban Discount = diskon baris (kode 5103). Total halaman dari agregat DB atas seluruh hasil filter, tidak termasuk invoice dibalik.
- `RevenueRegisterController` (route `transactions.invoices.index` kini ke sini): filter Cari (no INV, customer/kode, produk, pendapatan lain), Cabang, Dari/Sampai; validasi filter; user terikat cabang hanya melihat cabangnya; nomor baris berlanjut antar halaman (25 invoice/halaman).
- Export CSV (`/transactions/invoices/export`): memakai filter yang sama (bukan hanya halaman tampil), BOM UTF-8, 26 kolom + Status, sel teks diawali `= + - @ tab CR` dinetralkan dengan `'` (CSV formula injection).
- Koreksi = pembalikan: `TransactionPostingService::reverseInvoice()` (alasan wajib, tanggal >= tanggal invoice, harus di tahun buku terbuka, kunci idempoten `invoice-reversal-{id}`, jurnal asli tak dihapus, status `reversed`, kas pembayaran awal ikut dibalik; ditolak bila ada pelunasan susulan atau sudah dibalik). Migrasi `2026_11_01_060000`. `AgingReportService` kini mengabaikan invoice `reversed`.
- Bukti: tambah (`POST transactions/invoices/{id}/attachments`, maks 5 total) dan hapus (`DELETE attachments/{id}`), semua dicek company (dan cabang untuk user terikat).
- UI `data-pendapatan.tsx`: TanStack Table 26 kolom, kolom Aksi/Bukti/No sticky, scroll horizontal, visibilitas kolom, dialog pembalikan dan bukti, kartu total, pagination. Halaman lama `invoices.tsx` dan `TransactionController::invoices()` dihapus (digantikan).
- Test: 135/135 lulus (`RevenueRegisterTest` 13 test), `tsc` bersih, build OK, `route:cache` OK. Belum dicoba di browser. Belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 3.3 Jurnal Umum (filter cabang/sumber/bulan/cari, drilldown sumber) dan Buku Besar (detail akun + ringkasan semua akun).

---
## 2026-11-01 (sesi 9) · Claude (Sonnet 5.5) · Langkah 3.1 Input Invoice selesai
- Membaca HANDOFF sesi 5-8 (Codex) dan melanjutkan dari fondasi pajak. Kondisi awal diverifikasi: 109 test, build, tsc hijau.
- Migrasi `2026_11_01_050000`: `invoices.payment_method/paid_amount/payment_account_id/discount_account_id/other_income_total`, `invoice_lines.product_id/revenue_account_id`, tabel `invoice_other_incomes`, tabel `attachments` (generik).
- `TransactionPostingService::invoice()` ditulis ulang (bcmath): kelompok pendapatan per baris, maks 15 baris, maks 3 pendapatan lain, DPP setelah diskon + pendapatan lain, PPN di atas DPP (ADR-008), pembayaran awal (Dr kas/bank, sisa Dr piutang), diskon sebagai Dr 5103 Beban Discount dengan pendapatan bruto (kolom referensi), validasi tipe akun dan `company_id`. Pembayaran awal membuat `Receipt` + alokasi pada jurnal yang sama sehingga subledger AR = GL. Nomor otomatis `INV-YYYY-00001`. Kompatibel dengan test lama (tanpa akun diskon = pendapatan netto).
- `AttachmentService` + `AttachmentController` (`/attachments/{id}`): maks 5 file, 5 MB, JPG/PNG/WebP/PDF, tipe disniff dari isi (executable bertopeng .jpg ditolak), disimpan di disk privat dengan nama acak, hanya dibuka user company pemilik, header nosniff + CSP. Posting dan lampiran satu transaksi DB.
- UI: `invoice-form.tsx` (halaman input sendiri, grid 15 baris, pendapatan lain, PPN/PPh23, ringkasan sticky, Lunas, pratinjau, reset, EDIT DATA), `invoice-document.tsx` (A4 bersama), `invoice-print.tsx` (`/transactions/invoices/{id}/print`), `invoice-detail.tsx` diperkaya (diskon, pendapatan lain, bukti, cetak).
- Kesalahan bisnis service -> pesan validasi `errors.invoice` (bukan 500).
- Test: 122/122 lulus (`InvoiceInputTest` 13 test baru), `npm run build`, `tsc`, `route:cache` OK. Form belum dicoba di browser.
- Menggantung: lihat CHECKLIST bagian C (customer cepat, terjemahan pesan, pembalikan, urutan, lampiran untuk transaksi lain). Belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 3.2 Data Pendapatan (26 kolom persis, cari, filter cabang, bukti, export CSV), lalu 3.3 Jurnal Umum + Buku Besar.

---
## 2026-11-01 (sesi 11) · Codex · Toggle tema global
- Menambahkan tombol toggle Light/Dark yang memakai state tema global pada landing page, semua layout autentikasi, dan sidebar aplikasi. Preferensi disimpan di localStorage/cookie dan mengubah kelas `.dark` pada root, sehingga seluruh komponen yang memakai token tema ikut berubah.
- Default tetap light untuk pengguna baru; pengguna dapat mengubahnya kapan saja tanpa mengubah pengaturan lain.
- Verifikasi: build, type-check, 196/196 test, serta route cache/clear berhasil. Tidak ada commit.

---
## 2026-11-01 (sesi 10) · Codex · Landing page terang
- Mendesain ulang halaman landing (`/`) dengan visual light-first: hero, CTA, preview dashboard ilustratif yang diberi konteks, ringkasan manfaat, dan penutup CTA. Tidak memakai angka contoh sebagai data dashboard aplikasi.
- Default tampilan untuk pengguna baru kini `light` di hook dan middleware; pilihan tema yang telah tersimpan pengguna tetap dipertahankan.
- Verifikasi: `npm run build`, `npm run types:check`, `php artisan test --compact` → 196/196 lulus, `php artisan route:cache && php artisan route:clear` berhasil. Belum ada cek visual manual di browser dan tidak ada commit.

---
## 2026-11-01 (sesi 9) · Codex · Login email/password biasa
- Halaman `/login` kini hanya menampilkan form email, kata sandi, ingat saya, reset kata sandi, dan pendaftaran; tombol/pemisah passkey dihapus dari halaman login. Teks UI diterjemahkan ke bahasa Indonesia.
- Dukungan passkey internal tidak dihapus secara global karena masih dipakai halaman Keamanan dan Konfirmasi Password; percobaan menonaktifkannya membuat Wayfinder tidak dapat membangun action yang masih diimpor komponen tersebut. Login biasa tetap tidak menawarkan passkey.
- Verifikasi: `npm run build`, `php artisan test --compact` → 177/177 lulus, `npm run types:check`, `php artisan route:cache && php artisan route:clear`, dan `git diff --check` berhasil. Tidak ada commit.

---
## 2026-11-01 (sesi 8) · Codex · Langkah 3.1 Invoice: fondasi pajak server
- Migrasi `2026_11_01_040000` menambah snapshot `dpp`, diskon, tarif/nominal PPN dan PPh23, akun PPN Keluaran, serta diskon dan total tiap baris invoice.
- `TransactionPostingService::invoice()` kini menghitung DPP sesudah diskon dan pajak dengan bcmath; PPN menambah piutang dan dikredit ke akun PPN Keluaran. PPh23 tersimpan sebagai estimasi dari DPP untuk dipakai saat penerimaan/bukti potong pada Langkah 4.
- Validasi membatasi maksimum 15 baris dan diskon/tarif 0–100. Test baru membuktikan DPP Rp180.000 setelah diskon, PPN Rp19.800, total piutang Rp199.800, estimasi PPh23 Rp3.600, serta jurnal balance.
- Verifikasi fokus: `TransactionPostingTest` 9/9 lulus, type-check bersih, route cache berhasil. Migrasi sudah diterapkan. UI Invoice belum diperluas dan full suite/build belum dijalankan setelah perubahan ini.
- Lanjutan: perluas form dengan pilihan akun/rate PPN, diskon dan total server; kemudian pendapatan lain, pembayaran awal, bukti, preview/cetak dan test end-to-end.

---
## 2026-11-01 (sesi 7) · Codex · ADR-008 diputuskan pengguna
- Pengguna menyetujui rekomendasi: PPN dari DPP menambah total piutang dan dikredit ke PPN Keluaran saat invoice; PPh23 tidak mengurangi invoice/pendapatan saat penerbitan dan menjadi PPh23 Dibayar di Muka ketika pelanggan memotong pembayaran dengan bukti potong.
- Tarif berasal dari pengaturan usaha; tetap perlu review profesional sebelum dipakai untuk pelaporan pajak.
- Langkah berikut: implementasikan 3.1 end-to-end dengan snapshot tarif/akun pada invoice, kalkulasi decimal, dan alokasi penerimaan bersih + kredit PPh23.

---
## 2026-11-01 (sesi 6) · Codex · Hardening awal Langkah 3.1 Invoice
- Audit menemukan `InvoiceRequest` sebelumnya memakai validasi `exists` global dan `TransactionPostingService::invoice()` hanya memverifikasi cabang/periode. Kini customer, cabang, periode, piutang, dan akun pendapatan seluruhnya dibatasi ke `company_id` pengguna; service juga menolak account/customer lintas company bila dipanggil selain dari HTTP.
- Menambahkan test regresi untuk akun lintas company; test Invoice terkait sekarang 8/8 lulus.
- Belum mengubah status/gate Invoice: form dan posting penuh (produk, diskon, pendapatan lain, pajak, uang muka, lampiran, preview/cetak) belum dibangun.
- Blocker keputusan bisnis: ADR-008 perlu menetapkan akun PPN/PPh23 serta apakah PPh23 mengurangi kas diterima, piutang, atau dicatat sebagai kredit pajak. Jangan lanjut posting pajak sebelum diputuskan.
- Verifikasi: `npm run build`, `npm run types:check`, `php artisan test --compact` → 108/108 lulus, `php artisan route:cache && php artisan route:clear` berhasil, `git diff --check` bersih. Tidak ada commit.
- Langkah berikut: setelah ADR-008, lanjutkan 3.1 dengan migrasi field invoice, kalkulasi decimal, jurnal pajak, dan test kasus paid/partial/unpaid.

---
## 2026-11-01 (sesi 5) · Codex · Langkah 2.5 Pengaturan & Backup
- Selesai ruang lingkup yang diizinkan: `/settings/company` sekarang menyimpan identitas usaha (alamat, telepon, email), rekening invoice, serta default PPN/PPh23 sebagai `decimal(5,2)` yang dapat dikonfigurasi (0–100). Tidak ada tarif hukum yang dihardcode; UI menyatakan perlunya review profesional.
- Backup/restore, data contoh, dan reset sengaja belum dibangun karena ADR-006 masih OPEN. Default pajak belum dikonsumsi Invoice dan perlu dikerjakan pada Langkah 3 dengan review ADR-008.
- Menambahkan migrasi `2026_11_01_030000`, `CompanyUpdateRequest`, dan `CompanyUpdateTest` (simpan data nyata, validasi tarif, larangan user tanpa company): 3 test baru lulus.
- Memindahkan dua endpoint closure lama (`/modules/{section}` dan `/.well-known/passkey-endpoints`) ke controller bernama; test regresi menjaga respons keduanya dan `php artisan route:cache` berhasil.
- Verifikasi: `npm install`, `composer install`, `php artisan migrate`, `npm run build`, `php artisan test --compact` → 107/107 lulus, `npm run types:check` bersih, `php artisan route:cache && php artisan route:clear` berhasil, dan `git diff --check` bersih.
- Menggantung: cek browser responsif sidebar/pengaturan; backup ADR-006; kebijakan dan pemakaian PPN/PPh23 ADR-008; utang teknis lain di CHECKLIST C. Perubahan belum di-commit.
- Langkah berikut: Langkah 3.1 — Input Invoice penuh, mulai dengan menetapkan keputusan pajak ADR-008 sebelum mengimplementasikan perhitungan dan posting pajak.

---
## 2026-11-01 (sesi 4) · Claude (Sonnet 5.5) · Langkah 2.4 Saldo Neraca + Setoran Modal Awal
- `OpeningPositionService`: dua metode saldo awal, eksklusif per cabang + tahun buku, diposting sekali, memakai bcmath (tanpa float). `ensureFree()` juga dipasang di jalur lama `TreasuryPostingService::openingBalance`.
- Saldo Neraca (`/inputan/saldo`): grid semua akun neraca (45 di COA bawaan), posisi plus/minus dari `accounts.is_contra`, kartu Aset/Kewajiban/Ekuitas/Selisih/Status; hanya bisa diposting bila seimbang.
- Setoran Modal Awal (`/inputan/modal`): daftar harta/hutang dibatasi bagian 10 Template Jurnal; modal awal pemilik (akun 3102) = harta neto - hutang (bisa negatif -> debit); baris aset tetap wajib tanggal perolehan, umur default dari kelompok aset, akumulasi penyusutan dijurnal ke akun akumulasi.
- Migrasi `2026_11_01_020000`: `opening_balances.method` + tabel `opening_balance_lines` (jejak audit isian + meta aset).
- `config/navigation.php`: saldo -> `/inputan/saldo`, modal -> `/inputan/modal` (keduanya partial).
- Test: 102/102 lulus (`OpeningPositionTest`, 16 test baru), `tsc` bersih, build OK, `route:cache` OK. Tampilan browser belum dicek.
- Menggantung: lihat CHECKLIST bagian C (draf, register aset cut-off, koreksi saldo awal, halaman lama). Belum di-commit.
- Dev DB: `php artisan migrate`.
- Langkah berikut: 2.5 Pengaturan & Backup (identitas usaha + default PPN/PPh23; backup/restore menunggu ADR-006), lalu Langkah 3 (vertikal Invoice).

---
## 2026-11-01 (sesi 3) · Claude (Sonnet 5.5) · Langkah 2.3 Template Jurnal
- Halaman `/system/template` (`TemplateController`, `pages/accounting/journal-template.tsx`): 10 bagian dalam satu halaman.
  1 Cabang (kolom baru `city`, `brand`) · 2 Tahun (tabel `fiscal_periods`, idempoten) · 3 Produk & harga (tabel baru `products`) · 4 Jenis persediaan (`inventory_items`) · 5 Akun & umur aset (tabel baru `asset_categories`) · 6-10 daftar pilihan di tabel generik `journal_template_options` (kolom `group`, `code`, `label`, `account_id`, `meta`).
- `ReferenceTemplateDefaults::seed()` mengisi default (juga memanggil seed COA); tombol "Muat template bawaan" memanggilnya. Umur aset bawaan (20/8/8/4/4 tahun) hanya titik awal, perlu review akuntan (ADR-008).
- Semua referensi akun divalidasi satu company (`accountRule`) dan tipe akun (pendapatan/aset/beban). Cabang tidak dihapus; tidak bisa dinonaktifkan selagi ditetapkan ke pengguna.
- Bug yang ketahuan lewat test: `is_active` belum ada di `$fillable` Branch (nonaktifkan cabang tidak tersimpan) sudah diperbaiki.
- `config/navigation.php`: Template Jurnal pending -> partial.
- Test: 86/86 lulus (`JournalTemplateTest` baru, 12 test), `tsc` bersih, `route:cache` OK. Tampilan browser belum dicek.
- Menggantung: form lain belum membaca template ini (jadi Gate C belum dicentang); `storeInventoryItem` lama masih rawan referensi akun lintas company (lihat CHECKLIST C); belum di-commit.
- Dev DB: jalankan `php artisan migrate` lalu `db:seed --class=AccountingSeeder`.
- Langkah berikut: 2.4 Input Saldo Neraca (grid 45 akun) dan Input Setoran Modal Awal, saling eksklusif per cabang.

---
## 2026-11-01 (sesi 2) · Claude (Sonnet 5.5) · Langkah 0 + Langkah 2.1/2.2
- Dokumen `05` dan `10` ditandai SUPERSEDED. `TreasuryController` kini 403 bila `company_id` null (sebelumnya 500).
- Migrasi `2026_11_01_000000`: kolom `module`, `definition`, `is_contra` di `accounts`.
- `App\Support\Accounting\ReferenceChartOfAccounts`: 73 akun (45 akun neraca = ukuran grid Saldo Neraca), `seed()` idempoten dan tidak menimpa nama yang diubah user. `AccountingSeeder` memakainya.
- Master Akun: tambah (kode unik per company, awalan harus cocok jenis), ubah nama/modul/definisi (`PATCH accounting/master-data/accounts/{account}`; kode dan jenis tidak bisa diubah), nonaktifkan ditolak bila akun dipakai jurnal/kas-bank/persediaan. UI: kolom Kode | Nama | Modul | Jenis | Definisi.
- Test: `php artisan test` 74/74 lulus (`MasterAccountTest` baru, 7 test); `tsc` bersih; `route:cache` OK.
- Catatan: akun `is_contra` hanya ditandai; belum dipakai laporan (dipakai di Saldo Neraca nanti). Database dev yang sudah ada perlu `php artisan migrate` lalu `db:seed --class=AccountingSeeder` agar mendapat COA baru (kode lama 1100/3100/4100/5100 tetap ada).
- Belum dikerjakan: commit, cek browser, kolom D/E Master Akun (COA belum dipakai laporan).
- Langkah berikut: Langkah 2.3 Template Jurnal (10 bagian), lalu 2.4 Saldo Neraca + Modal Awal.

---
## 2026-11-01 · Claude (Sonnet 5.5) · Langkah 1 selesai: sidebar 43 menu
- Baca seluruh `docs/` (22 berkas). Menetapkan `reference-catatrapi/` sebagai kontrak yang menang atas `10-UI-PAGE-SPEC.md`.
- Dibuat: `config/navigation.php`, `PendingPageController`, `pages/pending.tsx`, sidebar & `nav-main.tsx` baru, `NavigationContractTest`.
- User memilih: route lama dipertahankan + alias; cakupan langkah 1 saja.
- Test: `php artisan test` → 67/67 lulus; `npm run types:check` bersih; `route:cache` OK. Tampilan browser belum dicek manual.
- Menggantung: perubahan **belum di-commit**; dokumen 05/10 belum ditandai superseded.
- Bug lama (belum diperbaiki): `TreasuryController::reconciliation()` TypeError jika `user.company_id` null → 500 di `/transactions/cash-bank`.
- Catatan: `public/build` di-gitignore; test butuh `npm run build` agar manifest Vite memuat halaman baru.
- Langkah berikut: STEPS.md → Langkah 0 (housekeeping) lalu Langkah 2.
