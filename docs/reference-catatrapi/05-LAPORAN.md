# PRD — Laporan

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Jurnal Umum

**URL referensi:** [https://app.profpreneurs.com/#/ju](https://app.profpreneurs.com/#/ju)  
**Tujuan:** Ledger semua posting per sumber dan periode.

**Isi/field dan kontrol:** Filter cabang, sumber, bulan, cari.

**Tabel/panel yang ada:** Sumber | No Trx | Cabang | Tahun | Bulan | Tanggal | No Akun | Nama Akun | Keterangan | Debit | Kredit | Saldo.

**Aksi yang harus diakomodasi:** Lihat baris, jumlah debit dan kredit, drilldown source.

**Aturan & dependensi dari referensi:** Dibentuk otomatis dari invoice/beban/stok/piutang/aset/penyusutan/treasury/jurnal/modal. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Total debit = kredit; cross-check dengan jurnal bisnis.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 02. Laporan Laba Rugi per Cabang

**URL referensi:** [https://app.profpreneurs.com/#/lr-cabang](https://app.profpreneurs.com/#/lr-cabang)  
**Tujuan:** Perbandingan akun pendapatan/beban dengan tiap cabang sejajar.

**Isi/field dan kontrol:** Periode awal dan akhir global; klik nama cabang/detail.

**Tabel/panel yang ada:** Kode Akun | Nama Akun | kolom tiap Cabang | TOTAL; baris gross margin, EBITDA, EBIT, EBT, laba bersih.

**Aksi yang harus diakomodasi:** Filter periode dan perbandingan.

**Aturan & dependensi dari referensi:** Konsolidasi merupakan total cabang; hasil cocok dengan Laba Rugi Konsolidasi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Akun dan kelompok laporan detail, bukan 3 baris agregat.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 03. Laporan Laba Rugi Konsolidasi

**URL referensi:** [https://app.profpreneurs.com/#/lr](https://app.profpreneurs.com/#/lr)  
**Tujuan:** Matriks 12 bulan laba rugi dan rasio profitabilitas.

**Isi/field dan kontrol:** Pilih semua cabang/satu cabang, tahun; kaitkan periode akhir ke posisi neraca.

**Tabel/panel yang ada:** Kode | Nama | Jan–Des | TOTAL | % Rasio; kelompok: pendapatan operasional, direct cost, gross margin, overhead, EBITDA, depreciation, EBIT, nonoperasional, EBT, pajak, laba bersih.

**Aksi yang harus diakomodasi:** Cetak/ekspor, bandingkan bulan dan telusuri akun.

**Aturan & dependensi dari referensi:** Ditampilkan Jan–Des penuh walaupun Periode Akhir dashboard memengaruhi laba berjalan Neraca. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Matriks 12 bulan wajib tampil dan dirujuk oleh budget/dividen.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 04. Laporan Neraca

**URL referensi:** [https://app.profpreneurs.com/#/neraca](https://app.profpreneurs.com/#/neraca)  
**Tujuan:** Posisi keuangan dan pertumbuhan per akun.

**Isi/field dan kontrol:** Filter cabang, periode awal/akhir.

**Tabel/panel yang ada:** Tabel bertingkat Aktiva dan Pasiva: Kode | Nama | saldo awal tahun lalu | posisi akhir tahun ini | growth; tabel rasio pendukung.

**Aksi yang harus diakomodasi:** Lihat posisi periode, cek balance.

**Aturan & dependensi dari referensi:** Saldo awal + mutasi sampai akhir periode; balance setiap cabang dan konsolidasi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Jangan klasifikasikan semua aset menjadi kas.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 05. Laporan Cashflow

**URL referensi:** [https://app.profpreneurs.com/#/cashflow](https://app.profpreneurs.com/#/cashflow)  
**Tujuan:** Laporan arus kas metode tidak langsung.

**Isi/field dan kontrol:** Periode awal dan akhir.

**Tabel/panel yang ada:** Uraian | Rincian (Rp) | Jumlah (Rp); Operasi (laba, nonkas, modal kerja), Investasi, Pendanaan, Kenaikan Bersih, Kas Awal/Akhir, Rekonsiliasi.

**Aksi yang harus diakomodasi:** Lihat dan cetak laporan.

**Aturan & dependensi dari referensi:** Kas akhir harus sama dengan Kas & Setara Kas Neraca. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Dilarang menyederhanakan menjadi saldo debit-kredit seluruh aset.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 06. Laporan Buku Besar

**URL referensi:** [https://app.profpreneurs.com/#/bb](https://app.profpreneurs.com/#/bb)  
**Tujuan:** Detail akun terpilih dan ringkasan seluruh akun.

**Isi/field dan kontrol:** Pilih akun dan periode awal/akhir.

**Tabel/panel yang ada:** Detail: Tanggal | No Trx | Sumber | Keterangan | Akun Lawan | Debit | Kredit | Saldo; Ringkasan: Kode | Nama | Saldo Awal | Debit | Kredit | Saldo Akhir.

**Aksi yang harus diakomodasi:** Klik akun, telusuri jurnal, cetak/ekspor.

**Aturan & dependensi dari referensi:** Saldo berjalan sesuai saldo normal akun dan periode. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Dua tabel/section utama tetap ada di halaman ini.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 07. Laporan Persediaan

**URL referensi:** [https://app.profpreneurs.com/#/lap-persediaan](https://app.profpreneurs.com/#/lap-persediaan)  
**Tujuan:** Posisi stok dan kartu stok per item dan cabang.

**Isi/field dan kontrol:** Filter cabang, bulan, tahun; pilih barang untuk kartu stok.

**Tabel/panel yang ada:** Ringkasan Qty/Nilai saldo awal, masuk, keluar, akhir, harga rata-rata, min, status; kartu stok Tanggal/Keterangan/Jenis/Qty Masuk/Keluar/Saldo Qty/Nilai Masuk/Keluar/Saldo Nilai.

**Aksi yang harus diakomodasi:** Lihat pergerakan item, low-stock.

**Aturan & dependensi dari referensi:** Moving average branch-aware; pemakaian menjadi HPP. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Stock register terhubung ke inventory GL.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 08. Laporan Piutang

**URL referensi:** [https://app.profpreneurs.com/#/lap-piutang](https://app.profpreneurs.com/#/lap-piutang)  
**Tujuan:** Outstanding invoice dan aging pelanggan.

**Isi/field dan kontrol:** Tanggal posisi; filter customer, status.

**Tabel/panel yang ada:** Cabang | Customer | Invoice | Tanggal | Total Tagihan | Dibayar | Sisa | Status | Umur Hari | Bayar Terakhir.

**Aksi yang harus diakomodasi:** Filter status lunas/belum, telusuri invoice dan pembayaran.

**Aturan & dependensi dari referensi:** Aging ≤30, 31–60, >60 hari sesuai referensi; invoice partial/paid. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Subledger AR cocok piutang GL.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 09. Laporan Daftar Hutang

**URL referensi:** [https://app.profpreneurs.com/#/lap-hutang](https://app.profpreneurs.com/#/lap-hutang)  
**Tujuan:** Outstanding hutang multi-sumber + kontrol GL.

**Isi/field dan kontrol:** Tanggal posisi; filter akun hutang, status.

**Tabel/panel yang ada:** Cabang | Keterangan | Akun Hutang | Tanggal | Nilai | Dibayar | Sisa | Status | Umur | Bayar Terakhir | Sumber; blok rekonsiliasi.

**Aksi yang harus diakomodasi:** Lihat aging, dokumen sumber dan selisih.

**Aturan & dependensi dari referensi:** Hutang dari beban, aset, stok, pinjaman, modal awal; alokasi pembayaran FIFO per akun pada referensi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Hutang tidak boleh hanya berasal dari expense; rekonsiliasi wajib.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 10. Laporan Pajak

**URL referensi:** [https://app.profpreneurs.com/#/lap-pajak](https://app.profpreneurs.com/#/lap-pajak)  
**Tujuan:** Kewajiban dan pembayaran pajak lintas masa.

**Isi/field dan kontrol:** Filter cabang/periode, jenis pajak, masa/status.

**Tabel/panel yang ada:** Empat blok: A ringkasan PPN/PPh Badan/PPh21/PPh23; B status masa pajak dan due estimate; C buku pembantu; D beban pajak Laba Rugi.

**Aksi yang harus diakomodasi:** Filter status, telusuri setoran dan utang pajak.

**Aturan & dependensi dari referensi:** Setoran dialokasi ke masa pajak tertua; jatuh tempo pada UI berupa perkiraan dan perlu review regulasi sebelum diterapkan. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Perhitungan pajak bukan cuma daftar tax code.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 11. Laporan Daftar Asset

**URL referensi:** [https://app.profpreneurs.com/#/lap-asset](https://app.profpreneurs.com/#/lap-asset)  
**Tujuan:** Register nilai buku aset dan rekonsiliasi.

**Isi/field dan kontrol:** Filter tanggal, akun, sumber beli/modal, status.

**Tabel/panel yang ada:** Per asset: cabang, tanggal, sumber, kode, akun, harga, umur, susut/bulan, mulai/akhir, bulan tersusut, akum, nilai buku, % status; rekap kelompok; kontrol Buku Besar.

**Aksi yang harus diakomodasi:** Telusuri aset dan jadwal penyusutan.

**Aturan & dependensi dari referensi:** Penyusutan mengikuti waktu perolehan dan saldo akun akumulasi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Terhubung ke Simulasi & Output D&A.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 12. Analisis Rasio & Kesehatan Bisnis

**URL referensi:** [https://app.profpreneurs.com/#/rasio](https://app.profpreneurs.com/#/rasio)  
**Tujuan:** 19 rasio dan skor kesehatan bisnis dari P&L, BS, cashflow.

**Isi/field dan kontrol:** Periode awal/akhir; editable arah/min/max nilai rujukan per rasio.

**Tabel/panel yang ada:** 19 baris: nama, rumus, aktual, nilai rujukan, status, analisis/rekomendasi, arah/min/max; blok Data Sumber Perhitungan.

**Aksi yang harus diakomodasi:** Ubah referensi, baca status/n/a/rekomendasi.

**Aturan & dependensi dari referensi:** Skor = (Sehat + 0.5 Perhatian) / jumlah rasio dinilai; 5 kategori (likuiditas 3, solvabilitas 3, profitabilitas 6, aktivitas 5, cash flow 2). [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Tidak boleh memberi skor sehat hanya karena journal balance; placeholder Ratio 9 dst harus dihapus.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.


---

# Catatan struktur 19 rasio (dari halaman #/rasio)

Tabel sumber menyajikan 19 rasio, masing-masing dengan kolom Rumus, Aktual, Nilai Rujukan, Status, Analisis & Rekomendasi, Arah, Min dan Max. [U] https://app.profpreneurs.com/#/rasio

| Kelompok | Rasio |
|---|---|
| Likuiditas | Current Ratio; Quick Ratio; Cash Ratio |
| Solvabilitas | DER; DAR; Equity Ratio |
| Profitabilitas | GPM; OPM; NPM; ROA; ROE; ROI |
| Aktivitas | Inventory Turnover; DIO; Receivable Turnover; DSO; TATO |
| Arus Kas | Operating Cash Flow Margin; Operating Cash Flow Ratio |

**Health score referensi:** (jumlah Sehat + 0.5 × jumlah Perhatian) ÷ jumlah rasio yang terdefinisi. Rasio dengan denominator nol harus N/A, bukan sehat 100%. Threshold dapat dikustom per sektor. **Tidak cukup** mengecek jurnal balance saja.
