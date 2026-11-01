# Alignment Workspace — menyelaraskan implementasi dengan PRD CatatRapi

Folder ini adalah **titik masuk tunggal** untuk siapa pun (manusia atau agent AI) yang melanjutkan pekerjaan menyelaraskan Laravel Accounting dengan `docs/reference-catatrapi/`. Dibuat supaya pergantian agent tidak kehilangan konteks.

## Baca berurutan
1. [00-RULES.md](00-RULES.md) — aturan keras & hierarki dokumen (jangan dilanggar).
2. [CHECKLIST.md](CHECKLIST.md) — **status nyata**: mana yang sudah, mana yang belum. Centang di sini.
3. [STEPS.md](STEPS.md) — langkah kerja berurutan beserta Definition of Done tiap langkah.
4. [DECISIONS-PENDING.md](DECISIONS-PENDING.md) — keputusan bisnis/teknis yang belum diputuskan user.
5. [HANDOFF-LOG.md](HANDOFF-LOG.md) — log serah-terima; **tambah entri baru di atas setiap selesai sesi**.

## Alur kerja satu sesi
1. Baca README ini → CHECKLIST → entri terbaru HANDOFF-LOG.
2. Ambil langkah belum selesai paling awal di STEPS.md (jangan loncat kecuali user minta).
3. Kerjakan, jalankan test (lihat perintah di `/AGENTS.md`).
4. Update CHECKLIST (centang hanya yang **terbukti**: test lulus / dicek), lalu tambah entri HANDOFF-LOG.
5. Jangan commit kecuali user meminta.

## Peta sumber
| Kebutuhan | Berkas |
|---|---|
| Kontrak menu (wajib) | `docs/reference-catatrapi/01-MENU-PERSIS.md`, `NAVIGATION-CONTRACT.json` |
| Spek per halaman | `docs/reference-catatrapi/02…08-*.md` |
| Gate A–E & gap kode | `docs/reference-catatrapi/09-PARITY-CHECKLIST.md` |
| Aturan UI | `docs/reference-catatrapi/10-IMPLEMENTATION-UI-RULES.md` |
| Aturan akuntansi | `docs/03-ACCOUNTING-RULES.md` |
| Skenario uji | `docs/07-ACCEPTANCE-TESTS.md` |
| Arsitektur / cPanel | `docs/04-ARCHITECTURE.md`, `docs/09-DEPLOY-CPANEL.md` |
| Sidebar (kode) | `config/navigation.php` |
