# Aturan Keras

## Hierarki dokumen (jika bertentangan, yang atas menang)
1. `docs/reference-catatrapi/*` — kontrak parity (menu, field, kolom, aturan bisnis).
2. `docs/03-ACCOUNTING-RULES.md`, `docs/07-ACCEPTANCE-TESTS.md`, `docs/04-ARCHITECTURE.md`.
3. `docs/01-PRD.md`, `docs/02-FEATURE-MATRIX.md`, `docs/06-IMPLEMENTATION-PLAN.md`.
4. **SUPERSEDED (jangan diikuti untuk IA/menu):** `docs/05-UI-TEMPLATE.md` (bagian Sidebar) dan `docs/10-UI-PAGE-SPEC.md` (41 halaman, grup Master/Transaksi/Akuntansi). Boleh dipakai hanya untuk pola komponen & state UI.

## Navigasi
- Sidebar = **43 halaman + Logout**, label/urutan/grup persis `NAVIGATION-CONTRACT.json` (termasuk ejaan "Asset", "System", "Inputan Transaksi", "Cashflow", "&").
- Satu-satunya sumber di kode: `config/navigation.php`. Test `tests/Feature/NavigationContractTest.php` mengunci ini. Mengubahnya butuh persetujuan user (ADR).
- Setiap menu **satu** tujuan nyata. Tidak boleh dua menu ke halaman yang sama. Tidak ada tombol/menu placeholder yang pura-pura jadi.
- Menu belum jadi → `status: pending` (otomatis dilayani `PendingPageController`). Saat halaman jadi: ubah `path` ke route asli & `status` ke `partial`/hapus status pending.
- Jangan tambah grup top-level (Master Data, Akuntansi, Pengaturan).
- Route/endpoint lama (`/master/*`, `/transactions/*`, `/accounting/*`) **dipertahankan**; jangan rename/hapus endpoint posting sebelum ada tes regresi.

## Akuntansi
- Semua uang: decimal, **tanpa floating point** di ledger. Jurnal harus balanced, atomik (DB::transaction), idempoten.
- Posted journal **tidak dihapus/diedit diam-diam**: koreksi = reversal + repost dengan audit trail.
- Ledger = sumber kebenaran; subledger (AR/AP/stok/aset) harus rekonsiliasi ke GL.
- Pajak/rate harus dicek profesional; jangan klaim sah secara hukum.
- Saldo Neraca (`#/saldo`) dan Setoran Modal Awal (`#/modal`) **saling eksklusif** per cabang dan **berbeda**.
- Rasio dengan penyebut nol = **N/A**, bukan sehat/NaN. Health score dari 19 rasio, bukan dari balance jurnal.

## UI
- Halaman Input = halaman sendiri (bukan modal) untuk invoice/jurnal/saldo.
- 9 halaman Data Transaksi tetap terpisah, tabel lebar: TanStack Table, scroll horizontal, sticky kolom aksi/key, export CSV yang bekerja, tidak membuang kolom referensi.
- Simulasi & Output D&A tetap di **Data Transaksi**.
- Laporan keuangan: tabel hierarkis (akun & kelompok, subtotal, GL/EBITDA/EBIT/EBT, matriks Jan–Des, kolom per cabang).
- Dashboard: 6 KPI + analitik sekunder; tidak boleh ada angka contoh seolah fakta; "Kas" hanya akun kas/bank.
- Filter cabang & periode harus benar-benar memengaruhi query server (bukan hanya UI).
- Deploy tetap cPanel: tanpa SSR/daemon, **tanpa closure di route** (agar `route:cache` jalan), asset dibuild lokal.

## Hak cipta
Hanya spesifikasi fungsional & nama menu pendek. Jangan salin source JS/CSS, logo, merek, teks panjang, atau gambar Prof.Preneur.

## Kejujuran status
Jangan centang "selesai" karena file/route ada. Selesai = Definition of Done di STEPS.md terpenuhi **dan** test lulus. Bedakan: tested vs hanya terlihat.
