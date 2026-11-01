# CatatRapi — Verified Feature-Parity PRD

**Tanggal audit:** 2026-11-01. **Ruang lingkup:** 43 halaman fungsional dan aksi Logout. **Sumber utama:** https://app.profpreneurs.com/#/panduan.

## Tujuan
Folder ini menjadi **baseline referensi produk** untuk Laravel Accounting sebelum mendesain ulang informasi arsitektur/menu. Permintaan saat ini adalah **menu, kelompok, urutan, dan nama sama persis terlebih dahulu** dengan sidebar sumber. UI visual boleh dimodernisasi tanpa mengubah alur, jumlah, struktur atau fitur, dan tanpa menyalin identitas/merek/desain artistik pihak lain.

## Aturan pembacaan bukti
- **[U]** = fakta terlihat pada DOM halaman publik ter-render melalui inspeksi halaman di URL yang tertera (menu, label, tabel, nilai kosong, field). Tidak membuktikan operasi setelah submit berhasil.
- **[G]** = panduan resmi yang tersedia di menu Panduan Penggunaan: aturan bisnis, alur data, perhitungan dan penyimpanan.
- **[R]** = hasil audit kode current GitHub Laravel Accounting; terpisah dari referensi dan mungkin berubah dengan commit lain.
- **[D]** = keputusan desain/usulan implementasi untuk Laravel (bukan fitur terverifikasi referensi).

Jika ada perbedaan antara label di halaman dan ringkasan panduan, **DOM sidebar untuk label menu** dan **halaman masing-masing untuk nama kolom/field** menjadi acuan; panduan untuk behavior yang belum dapat diuji. Transaksi demo saat diinspeksi kosong; perilaku posting, edit/hapus, import, lisensi dan rollover tidak dijalankan end-to-end. Jangan memarkirnya sebagai *tested*.

## Urutan baca
1. [01-MENU-PERSIS.md](01-MENU-PERSIS.md) — **kontrak sidebar final fase parity**; file JSON pendamping [NAVIGATION-CONTRACT.json](NAVIGATION-CONTRACT.json).
2. [02-SYSTEM.md](02-SYSTEM.md) — enam submenu System.
3. [03-INPUTAN-TRANSAKSI.md](03-INPUTAN-TRANSAKSI.md) — sepuluh input.
4. [04-DATA-TRANSAKSI.md](04-DATA-TRANSAKSI.md) — sembilan tabel data + simulasi D&A.
5. [05-LAPORAN.md](05-LAPORAN.md) — dua belas laporan.
6. [06-ANGGARAN-DIVIDEN.md](06-ANGGARAN-DIVIDEN.md) — empat anggaran dan satu dividen.
7. [07-DASHBOARD.md](07-DASHBOARD.md) — dashboard dan KPI/integrity.
8. [08-SUMBER-ANALISIS.md](08-SUMBER-ANALISIS.md) — matriks URL, jenis bukti dan keterbatasan.
9. [09-PARITY-CHECKLIST.md](09-PARITY-CHECKLIST.md) — acceptance gates dan beda dengan repo.
10. [10-IMPLEMENTATION-UI-RULES.md](10-IMPLEMENTATION-UI-RULES.md) — UI modern tetapi struktur/menu persis.

## Kesepakatan fase
- **43 menu halaman:** Dashboard 1, System 6, Inputan Transaksi 10, Data Transaksi 9, Laporan 12, Anggaran 4, Bagi Hasil & Dividen 1. Logout adalah satu aksi terpisah.
- **Tidak** digabungkan: Inputan Transaksi vs Data Transaksi; Saldo Neraca vs Setoran Modal Awal; Simulasi & Output D&A tetap dalam Data Transaksi; Laba Rugi Cabang vs Konsolidasi; Anggaran Input/Data/Rekap/Kinerja.
- File ini adalah **requirement**, bukan klaim bahwa Laravel Accounting sudah memiliki seluruh fungsi tersebut. Source code, routes, tests dan deployment tidak disentuh oleh commit dokumen ini.
- Reference bekerja **100% offline** dan menyimpan data di browser. Rencana Laravel server/database/cPanel merupakan perubahan teknologi yang harus eksplisit tercatat; belum berarti offline parity.

## Hak cipta
Hanya spesifikasi fungsional, nama-nama menu pendek dan perilaku bisnis yang dideskripsikan ulang. Jangan mengambil source JS/CSS, logo, identitas Prof.Preneur, teks panjang, atau gambar proprietary ke produk.
