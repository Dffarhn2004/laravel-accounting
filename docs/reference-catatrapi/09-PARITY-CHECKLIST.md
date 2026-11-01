# Acceptance Checklist & Implementation Gap

## Prinsip pemeriksaan
Menu sama persis adalah **gate A**, isi halaman sama adalah **gate B**, business rule berjalan end-to-end adalah **gate C**, integritas akuntansi/laporan adalah **gate D**, UX modern dan cPanel compatibility adalah **gate E**. Fitur tidak boleh dianggap Complete jika hanya ada file React atau nama route.

| Gate | Harus dibuktikan |
|---|---|
| A — Navigation | Semua 43 page + Logout, urutan/menu identik, navigasi berfungsi, nested group aktif sesuai halaman |
| B — Visible UI | Header/field/tabel/filter/shortcut/kartu dari sumber ada dan berfungsi sesuai jenis layar |
| C — Workflow | Buat, submit, edit yang aman, lampiran, preview/cetak, import/cancel, upload/backup sesuai page |
| D — Financial | Jurnal debit=kredit, AR/AP/stock/asset ties to GL, profit/BS/cashflow/budget/ratios period-aware |
| E — Operational | Responsive, accessible, aman per company/branch, build static asset + Laravel cPanel deployment |

## Checklist 43 halaman
### Dashboard
- [ ] #/dashboard — Dashboard: UI fields & action [B]; workflow [C]; accounting/tests [D].

### System
- [ ] #/panduan — Panduan Penggunaan: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/master — Master Akun: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/template — Template Jurnal: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/tutup — Tutup Buku & Pindah Tahun: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/aktivasi — Aktivasi Lisensi: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/setting — Pengaturan & Backup: UI fields & action [B]; workflow [C]; accounting/tests [D].

### Inputan Transaksi
- [ ] #/invoice — Input Invoice: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/impor — Import Penjualan (Kasir / Marketplace): UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/beban — Input Beban: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/persediaan — Input Persediaan: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/piutang — Input Pelunasan Piutang: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/asset — Input Pembelian Asset: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/kasbank — Input Pindah Saldo Kas/Bank: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/saldo — Input Saldo Neraca Akhir Periode: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/modal — Input Setoran Modal Awal: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/jm — Input Jurnal Manual: UI fields & action [B]; workflow [C]; accounting/tests [D].

### Data Transaksi
- [ ] #/d-pendapatan — Data Pendapatan: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-beban — Data Beban: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-persediaan — Data Persediaan: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-piutang — Data Pelunasan Piutang: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-asset — Data Asset: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/simulasi — Simulasi & Output D&A: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-kasbank — Data Kas & Bank: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-modal — Data Modal Awal: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/d-jm — Data Jurnal Manual: UI fields & action [B]; workflow [C]; accounting/tests [D].

### Laporan
- [ ] #/ju — Jurnal Umum: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lr-cabang — Laporan Laba Rugi per Cabang: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lr — Laporan Laba Rugi Konsolidasi: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/neraca — Laporan Neraca: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/cashflow — Laporan Cashflow: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/bb — Laporan Buku Besar: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lap-persediaan — Laporan Persediaan: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lap-piutang — Laporan Piutang: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lap-hutang — Laporan Daftar Hutang: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lap-pajak — Laporan Pajak: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/lap-asset — Laporan Daftar Asset: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/rasio — Analisis Rasio & Kesehatan Bisnis: UI fields & action [B]; workflow [C]; accounting/tests [D].

### Anggaran
- [ ] #/ang-input — Input Anggaran: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/ang-data — Data Anggaran: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/ang-rekap — Rekap Anggaran: UI fields & action [B]; workflow [C]; accounting/tests [D].
- [ ] #/ang-kinerja — Laporan Kinerja Anggaran: UI fields & action [B]; workflow [C]; accounting/tests [D].

### Bagi Hasil & Dividen
- [ ] #/dividen — Bagi Hasil & Dividen: UI fields & action [B]; workflow [C]; accounting/tests [D].

## Hasil audit sumber kode Laravel yang perlu ditindaklanjuti (snapshot [R] per 2026-11-01)
| Prioritas | Area/berkas | Gap jelas dibanding referensi |
|---|---|---|
| CRITICAL | FinancialReportService.php | KPI 'Kas & Setara Kas' dan 'Total Aset' menghitung agregat aset yang sama; definisi kas salah |
| CRITICAL | FinancialRatioService.php | Delapan rasio bernama dan placeholder Ratio 9 sampai Ratio 22, bukan 19 formula sah |
| CRITICAL | HealthScoreService.php | Skor sehat dari validnya debit/kredit bukan skor 19 rasio |
| CRITICAL | ClosingController.php | Close hanya status periode, belum rollover saldoneraca, AR/AP, stok, aset, arsip |
| HIGH | TransactionPostingService.php dan InvoiceRequest.php | Invoice hanya qty × unit price; belum diskon, tambahan pendapatan, PPN/PPh23, uang muka pada invoice, cetak, bukti |
| HIGH | FinancialReportService.php + financial-statement.tsx | P&L dan Neraca hanya agregat per kategori, belum hierarki akun dan matriks 12 bulan/cabang |
| HIGH | Dashboard.tsx | Banyak blok berupa empty state, tidak memakai filter periode dan cabang global seperti source |
| HIGH | InventoryController.php + inventory.tsx | InventoryPostingService ada, tetapi UI belum mengekspos input stok masuk/keluar fungsional |
| HIGH | BudgetController.php + budget.tsx | Budget belum full Qty × Tarif per kegiatan per bulan, rekap dan kinerja dipisah |
| MEDIUM | AccountingSeeder.php | Baru empat COA dasar, sedangkan reference memakai struktur COA multi-kategori rinci |
| MEDIUM | app-sidebar.tsx | Menu di repo saat audit lebih ringkas dan banyak grup/halaman digabung, tidak 43 item sama referensi |

**Catatan:** Temuan ini **review statis kode**, bukan test runtime. Beberapa service lain mungkin menyimpan pekerjaan backend yang belum terhubung ke route/UI. Jangan menghapusnya tanpa audit. Perubahan sejak snapshot harus diverifikasi ulang.

## Rekomendasi implementasi tanpa mengubah kontrak menu
1. Bangun sidebar persis dari NAVIGATION-CONTRACT.json, menyediakan empty state jujur hanya selama proses build.
2. Jadikan Master Akun, Template Jurnal dan Saldo/Modal Awal cukup lengkap sebelum posting bisnis.
3. Selesaikan vertikal invoice lengkap → data pendapatan → jurnal → BB → LR/BS/CF → dashboard.
4. Lanjut beban → data beban → AP/GL; kas/bank/piutang; stok; aset+DA; import; pajak.
5. Anggaran → report, rasio, dividen, dan tutup buku setelah rekonsiliasi.
6. Cocokkan 43 halaman satu per satu dengan bukti UI dan tes berisi data nyata.
