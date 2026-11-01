# Langkah Penyelarasan (kerjakan berurutan)

Urutan mengikuti "Rekomendasi implementasi" di `reference-catatrapi/09-PARITY-CHECKLIST.md`. Tiap langkah punya **Definition of Done (DoD)**. Centang di [CHECKLIST.md](CHECKLIST.md) hanya setelah DoD terpenuhi. Setiap fitur wajib: test dengan data nyata, jurnal balanced, tanpa float.

Status langkah: `[x]` selesai · `[~]` sebagian · `[ ]` belum.

## [x] Langkah 1: Sidebar 43 menu + Logout
Selesai 2026-11-01. `config/navigation.php`, `PendingPageController`, `NavigationContractTest`.
DoD: test kontrak lulus, route lama tetap hidup.

## [~] Langkah 0: Housekeeping (selesai kecuali commit, cek browser, CI)
- [ ] Commit (menunggu permintaan user).
- [x] Tandai `05-UI-TEMPLATE.md` (bagian Sidebar) dan `10-UI-PAGE-SPEC.md` sebagai SUPERSEDED di bagian atas.
- [x] Perbaiki bug `TreasuryController::reconciliation()` saat `company_id` null.
- [ ] Cek sidebar di browser (desktop/tablet/mobile), catat di HANDOFF-LOG.
- [ ] CI: `npm run build` sebelum test.

## [~] Langkah 2: Fondasi data master (2.1 sampai 2.4 selesai; 2.5 belum)
Spek: `02-SYSTEM.md`, `03-INPUTAN-TRANSAKSI.md` (Saldo Neraca, Modal Awal).
1. [x] **COA seed lengkap**: awalan 1 Aset, 2 Kewajiban, 3 Ekuitas, 4 Pendapatan, 5 Beban; minimal 45 akun termasuk akun pengurang/akumulasi penyusutan, PPN, PPh23, hutang usaha, piutang, persediaan, kas/bank. Seeder idempoten.
2. [x] **Master Akun**: ganti nama/definisi, tambah akun dalam kelompok, larang hapus akun terpakai; kolom Kode | Nama | Modul | Jenis | Definisi.
3. [x] **Template Jurnal** (10 bagian dalam satu halaman): cabang/kota/brand; template tahun; produk dan harga; jenis persediaan; setting akun dan umur aset; metode bayar invoice/pelunasan; metode bayar beban/aset/persediaan; jenis transaksi kas/bank/hutang/modal; kategori akun Dari/Ke; akun yang boleh dipakai di modal awal. Perubahan tampil di dropdown form lain. *(Halaman + CRUD selesai dan dites; pemakaian di form lain menyusul di Langkah 3-5.)*
4. [x] **Input Saldo Neraca** (grid 45 akun, posisi plus/minus, kartu aset/kewajiban/ekuitas/selisih/status) dan **Input Setoran Modal Awal** (daftar harta/hutang, cut-off, aset masuk register) sebagai dua halaman terpisah, **saling eksklusif per cabang**, posting tepat sekali.
5. [ ] Pengaturan & Backup: identitas usaha, default PPN/PPh23 (backup/restore menunggu ADR-006).

DoD: dua jalur saldo awal menghasilkan neraca seimbang; memakai keduanya di cabang yang sama ditolak; test idempotensi.

## [ ] Langkah 3: Vertikal Invoice sampai Dashboard
Spek: `03` (Invoice), `04` (Data Pendapatan), `05` (JU, BB, LR, Neraca, Cashflow), `07-DASHBOARD.md`.
1. **Input Invoice** penuh: maks 15 baris produk, mode harga master/manual, diskon %, maks 3 pendapatan lain, PPN, PPh23 dari DPP, dibayar/sisa piutang, nomor INV otomatis unik, maks 5 lampiran, preview/cetak A4, reset.
2. **Data Pendapatan** (26 kolom persis, cari, filter cabang, bukti, export CSV; koreksi via reversal).
3. **Jurnal Umum** (filter cabang/sumber/bulan/cari, drilldown sumber) dan **Buku Besar** (detail akun + ringkasan semua akun).
4. **LR Konsolidasi** (matriks Jan-Des, hierarki, GM/EBITDA/EBIT/EBT), **LR per Cabang**, **Neraca** (saldo awal vs akhir, growth, cek balance), **Cashflow** metode tidak langsung (kas akhir = kas neraca).
5. **Dashboard**: 6 KPI (Kas = akun kas/bank saja), filter global cabang+periode, 8 kontrol integritas, blok sekunder berisi data nyata.

DoD: ACC-01..04, 14, 16, 21, 22 lulus; satu invoice mengalir ke semua laporan dan dashboard.

## [ ] Langkah 4: Beban, Kas/Bank, Piutang, Jurnal Manual
Spek: `03`, `04`, `05` (Piutang, Hutang).
Input Beban + Data Beban; Input Pindah Saldo Kas/Bank (11 kategori, Dari/Ke dibatasi jenis) + Data Kas & Bank; Input Pelunasan Piutang (cabang mengikuti invoice, tidak melebihi sisa, idempoten) + Data Pelunasan; Input Jurnal Manual (2-6 baris) + Data Jurnal Manual; Data Modal Awal; Laporan Piutang (aging <=30 / 31-60 / >60); Laporan Daftar Hutang (multi-sumber + rekonsiliasi GL).

DoD: ACC-05, 09; subledger AR/AP cocok dengan GL.

## [ ] Langkah 5: Persediaan, Aset, D&A
Input Persediaan (moving average per cabang, stok tidak boleh minus, aturan backdate: lihat DECISIONS-PENDING) + Data Persediaan + Laporan Persediaan (kartu stok, low-stock). Input Pembelian Asset + Data Asset + Simulasi & Output D&A (3 tabel, tetap di grup Data Transaksi) + Laporan Daftar Asset.

DoD: ACC-06, 07, 08; stok dan aset cocok GL.

## [ ] Langkah 6: Import Penjualan dan Pajak
Import xlsx/csv: template, default mapping, preview, validasi + dedupe di server, per No Order = satu invoice, riwayat dan batalkan batch, aman terhadap limit cPanel. Laporan Pajak (blok A-D, alokasi ke masa tertua; perlu review ADR-008).

DoD: ACC-11, 12.

## [ ] Langkah 7: Anggaran, Rasio, Dividen
Input Anggaran (Qty x Tarif, 12 bulan, akun 4/5), Data, Rekap (struktur sama dengan LR), Kinerja (warna status, target 0 = N/A). Analisis Rasio: 19 rasio + health score + nilai rujukan editable. Bagi Hasil & Dividen satu halaman (total kepemilikan 100%).

DoD: ACC-18, 19, 20.

## [ ] Langkah 8: Tutup Buku, Backup, Lisensi, Panduan
Tutup Buku & Pindah Tahun (preview per cabang, checklist, carry-forward saldo/AR/AP/stok/aset/anggaran/pemegang saham, arsip JSON, histori tidak dirusak). Backup/Restore (menunggu ADR-006). Aktivasi Lisensi (menunggu ADR-013). Panduan Penggunaan (tulis ulang, tautan ke menu asli).

DoD: ACC-13, 17.

## [ ] Langkah 9: Hardening
Gate E tiap halaman: responsif 1440/768/390, aksesibilitas, otorisasi company/cabang (ACC-10), upload aman (ACC-15), cetak A4, deploy cPanel (route:cache, tanpa SSR). Audit ulang 43 halaman satu per satu terhadap bukti UI.

## Cara menutup sebuah halaman (berlaku di semua langkah)
1. Bangun halaman + controller (tanpa closure di route) + Form Request + service posting.
2. Ubah `config/navigation.php`: `path` ke route asli dan `status` menjadi `partial` (atau hapus status pending); `NavigationContractTest` harus tetap lulus.
3. Tulis test (Feature + skenario ACC terkait).
4. Centang kolom B/C/D/E yang terbukti di CHECKLIST, tambah entri HANDOFF-LOG.
