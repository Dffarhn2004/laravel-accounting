# Handoff Log (terbaru di atas)

Format entri: tanggal · agent/orang · apa yang dikerjakan · status test · hal yang menggantung · saran langkah berikut.

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
