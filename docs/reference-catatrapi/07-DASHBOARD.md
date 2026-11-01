# PRD — Dashboard

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Dashboard

**URL referensi:** [https://app.profpreneurs.com/#/dashboard](https://app.profpreneurs.com/#/dashboard)  
**Tujuan:** Ringkasan lintas cabang/periode dan validasi integritas.

**Isi/field dan kontrol:** Filter Cabang/Brand semua atau satu, Periode Awal dan Periode Akhir, tahun buku; 6 KPI: Pendapatan, Laba Bersih, Kas & Setara Kas, Total Aset, Piutang Outstanding, Hutang Outstanding.

**Tabel/panel yang ada:** Tren bulanan pendapatan/beban/laba; health score 19 rasio; 8 kontrol integritas; achievement budget; proyeksi dividen; kinerja tiap cabang; tren kas; arus kas per aktivitas; komposisi beban/aset; enam rasio kunci; matriks laba rugi 12 bulan; Top 5 AR dan AP.

**Aksi yang harus diakomodasi:** Filter global dan drilldown cabang, rasio, anggaran, piutang/hutang.

**Aturan & dependensi dari referensi:** Dashboard hanya VALID bila jurnal, neraca, cashflow, laba berjalan, AP/asset GL, cabang dan neraca cabang saling konsisten. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Jangan memalsukan health score dari balance jurnal saja; nilai cash hanya akun cash/bank.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.
