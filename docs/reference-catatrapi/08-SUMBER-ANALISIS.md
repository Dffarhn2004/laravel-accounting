# Provenance — Sumber Analisis dan Batas Bukti

## 1. Metode yang benar-benar dilakukan
1. Mengakses **situs publik** https://app.profpreneurs.com/ dan 43 route hash secara independen, tanpa login kredensial pengguna dan tanpa memodifikasi transaksi.
2. Membaca **DOM sidebar HTML** pada lima halaman dengan masing-masing grup terbuka: panduan, invoice, d-pendapatan, lr, ang-input. Di sana diperoleh label, kelompok, urutan dan href nyata [U].
3. Membaca halaman **UI ter-render** pada semua 43 URL untuk heading, control/label, nama kolom tabel, panel, helper text dan daftar pilihan [U].
4. Menggunakan **Panduan Penggunaan** untuk aturan bisnis yang tertulis: input → Data Transaksi → Jurnal → Buku Besar → report; penyimpanan offline; kebijakan status, stok, penyusutan, saldo awal dan akhir tahun [G].
5. Membandingkan dengan file yang benar-benar ada pada branch main repository Dffarhn2004/laravel-accounting melalui konektor GitHub [R]. Tidak ada tes lokal atau uji browser dengan data sampel yang dilakukan pada audit ini.

## 2. Index sumber primer per halaman

| Grup | Menu | URL referensi | Bukti |
|---|---|---|---|
| Dashboard | Dashboard | [#/dashboard](https://app.profpreneurs.com/#/dashboard) | U (UI/kolom); G (aturan jika disebut) |
| System | Panduan Penggunaan | [#/panduan](https://app.profpreneurs.com/#/panduan) | U (UI/kolom); G (aturan jika disebut) |
| System | Master Akun | [#/master](https://app.profpreneurs.com/#/master) | U (UI/kolom); G (aturan jika disebut) |
| System | Template Jurnal | [#/template](https://app.profpreneurs.com/#/template) | U (UI/kolom); G (aturan jika disebut) |
| System | Tutup Buku & Pindah Tahun | [#/tutup](https://app.profpreneurs.com/#/tutup) | U (UI/kolom); G (aturan jika disebut) |
| System | Aktivasi Lisensi | [#/aktivasi](https://app.profpreneurs.com/#/aktivasi) | U (UI/kolom); G (aturan jika disebut) |
| System | Pengaturan & Backup | [#/setting](https://app.profpreneurs.com/#/setting) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Invoice | [#/invoice](https://app.profpreneurs.com/#/invoice) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Import Penjualan (Kasir / Marketplace) | [#/impor](https://app.profpreneurs.com/#/impor) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Beban | [#/beban](https://app.profpreneurs.com/#/beban) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Persediaan | [#/persediaan](https://app.profpreneurs.com/#/persediaan) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Pelunasan Piutang | [#/piutang](https://app.profpreneurs.com/#/piutang) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Pembelian Asset | [#/asset](https://app.profpreneurs.com/#/asset) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Pindah Saldo Kas/Bank | [#/kasbank](https://app.profpreneurs.com/#/kasbank) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Saldo Neraca Akhir Periode | [#/saldo](https://app.profpreneurs.com/#/saldo) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Setoran Modal Awal | [#/modal](https://app.profpreneurs.com/#/modal) | U (UI/kolom); G (aturan jika disebut) |
| Inputan Transaksi | Input Jurnal Manual | [#/jm](https://app.profpreneurs.com/#/jm) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Pendapatan | [#/d-pendapatan](https://app.profpreneurs.com/#/d-pendapatan) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Beban | [#/d-beban](https://app.profpreneurs.com/#/d-beban) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Persediaan | [#/d-persediaan](https://app.profpreneurs.com/#/d-persediaan) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Pelunasan Piutang | [#/d-piutang](https://app.profpreneurs.com/#/d-piutang) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Asset | [#/d-asset](https://app.profpreneurs.com/#/d-asset) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Simulasi & Output D&A | [#/simulasi](https://app.profpreneurs.com/#/simulasi) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Kas & Bank | [#/d-kasbank](https://app.profpreneurs.com/#/d-kasbank) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Modal Awal | [#/d-modal](https://app.profpreneurs.com/#/d-modal) | U (UI/kolom); G (aturan jika disebut) |
| Data Transaksi | Data Jurnal Manual | [#/d-jm](https://app.profpreneurs.com/#/d-jm) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Jurnal Umum | [#/ju](https://app.profpreneurs.com/#/ju) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Laba Rugi per Cabang | [#/lr-cabang](https://app.profpreneurs.com/#/lr-cabang) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Laba Rugi Konsolidasi | [#/lr](https://app.profpreneurs.com/#/lr) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Neraca | [#/neraca](https://app.profpreneurs.com/#/neraca) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Cashflow | [#/cashflow](https://app.profpreneurs.com/#/cashflow) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Buku Besar | [#/bb](https://app.profpreneurs.com/#/bb) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Persediaan | [#/lap-persediaan](https://app.profpreneurs.com/#/lap-persediaan) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Piutang | [#/lap-piutang](https://app.profpreneurs.com/#/lap-piutang) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Daftar Hutang | [#/lap-hutang](https://app.profpreneurs.com/#/lap-hutang) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Pajak | [#/lap-pajak](https://app.profpreneurs.com/#/lap-pajak) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Laporan Daftar Asset | [#/lap-asset](https://app.profpreneurs.com/#/lap-asset) | U (UI/kolom); G (aturan jika disebut) |
| Laporan | Analisis Rasio & Kesehatan Bisnis | [#/rasio](https://app.profpreneurs.com/#/rasio) | U (UI/kolom); G (aturan jika disebut) |
| Anggaran | Input Anggaran | [#/ang-input](https://app.profpreneurs.com/#/ang-input) | U (UI/kolom); G (aturan jika disebut) |
| Anggaran | Data Anggaran | [#/ang-data](https://app.profpreneurs.com/#/ang-data) | U (UI/kolom); G (aturan jika disebut) |
| Anggaran | Rekap Anggaran | [#/ang-rekap](https://app.profpreneurs.com/#/ang-rekap) | U (UI/kolom); G (aturan jika disebut) |
| Anggaran | Laporan Kinerja Anggaran | [#/ang-kinerja](https://app.profpreneurs.com/#/ang-kinerja) | U (UI/kolom); G (aturan jika disebut) |
| Bagi Hasil & Dividen | Bagi Hasil & Dividen | [#/dividen](https://app.profpreneurs.com/#/dividen) | U (UI/kolom); G (aturan jika disebut) |

**Referensi tambahan:** [Panduan Penggunaan](https://app.profpreneurs.com/#/panduan); [Repository Laravel Accounting](https://github.com/Dffarhn2004/laravel-accounting); [feature matrix awal](../02-FEATURE-MATRIX.md).

## 3. Confidence dan batasan
- **Tinggi:** sidebar 43 item, urutan/menu dan route; headings halaman; nama field & tabel yang terlihat; struktur invoice, L/R, neraca, rasio, anggaran, pajak.
- **Sedang:** rumus, perilaku edit/hapus dan posting/rollover yang baru dijelaskan teks panduan, karena tidak dibuat transaksi sungguhan.
- **Belum dibuktikan:** keberhasilan submit semua jenis transaksi, rekonsiliasi dengan data nyata, unduh file ekspor/backup, file import valid dan rollback, aktivasi lisensi, penutupan tahun aktual, keberhasilan offline multi-device. Skenario-skenario ini wajib pengujian lanjutan.
- Situs demo dapat berubah. Simpan tanggal audit dan gunakan referensi versi ini sebelum mengubah baseline.
- Kewajiban pajak dan kebijakan pelaporan bukan nasihat hukum atau sertifikasi akuntansi; verifikasi independen oleh akuntan/profesional pajak.

## 4. Pemisahan fakta dan desain
Semua info yang ditulis sebagai [D] (misalnya TanStack Table, reversal immutable, form dialog, multiuser RBAC, cPanel) adalah pilihan pengembangan Laravel; **bukan pernyataan bahwa source memakai teknologi tersebut**.
