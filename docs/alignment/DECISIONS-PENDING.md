# Keputusan yang Belum Diputuskan (tanyakan ke user sebelum mengerjakan area terkait)

| ID | Topik | Memengaruhi | Status |
|---|---|---|---|
| ADR-006 | Offline-local vs cloud SaaS vs hybrid (referensi 100% offline) | Backup/Restore, Pengaturan | OPEN |
| ADR-007 | Multi-user & role per company/cabang | Otorisasi semua halaman | OPEN |
| ADR-008 | Aturan pajak Indonesia & standar akuntansi | Invoice (PPN/PPh23), Laporan Pajak | DIPUTUSKAN 2026-11-01: PPN dari DPP menambah piutang dan dikredit ke PPN Keluaran saat invoice. PPh23 hanya menjadi kredit pajak saat pelanggan memotong pembayaran dengan bukti potong: debit kas neto + debit PPh23 Dibayar di Muka, kredit piutang bruto. Tarif tetap konfigurasi usaha dan perlu review profesional sebelum pelaporan. |
| ADR-011 | Alokasi hutang: FIFO per akun vs matching bill | Laporan Daftar Hutang | OPEN |
| ADR-012 | Transfer antar cabang: akun clearing & eliminasi | LR Cabang vs Konsolidasi | OPEN |
| ADR-013 | Model lisensi (trial 20 trx / langganan / tanpa) | Aktivasi Lisensi | OPEN (menu tetap tampil, backend pending) |
| NEW | Menandai `05-UI-TEMPLATE.md` & `10-UI-PAGE-SPEC.md` sebagai superseded | Dokumentasi | Diputuskan arahnya (kontrak CatatRapi menang); edit dokumen belum dilakukan |
| NEW | Aturan backdate untuk persediaan (moving average) | Input/Data/Laporan Persediaan | OPEN |
