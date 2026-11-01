# PRD — Inputan Transaksi

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Input Invoice

**URL referensi:** [https://app.profpreneurs.com/#/invoice](https://app.profpreneurs.com/#/invoice)  
**Tujuan:** Mencatat penjualan, pendapatan pajak, kas, piutang dan menyiapkan invoice.

**Isi/field dan kontrol:** Cabang; urutan; tanggal; nama customer; nomor INV otomatis; jenis pembayaran; mode harga master/manual; hingga 15 baris produk (produk, kelompok pendapatan, harga, qty, diskon%, total); hingga 3 pendapatan lain; PPN, PPh23; total tagihan; total dibayar; sisa piutang; upload bukti.

**Tabel/panel yang ada:** Grid produk 7 kolom, grid tambahan pendapatan 4 kolom; pratinjau cetak invoice lengkap bank dan identitas.

**Aksi yang harus diakomodasi:** Tambah/hapus baris produk; preview/cetak; submit konfirmasi; reset; navigasi EDIT DATA.

**Aturan & dependensi dari referensi:** Sisa dibayar menjadi AR; PPh23 dan PPN dihitung dari DPP sesuai aturan terlihat; dukungan lampiran maksimal lima file; nomor unik otomatis. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Uji lintas nominal, discount, pajak, paid/partial/unpaid serta jurnal & laporan; jangan memakai floating point pada ledger.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 02. Import Penjualan (Kasir / Marketplace)

**URL referensi:** [https://app.profpreneurs.com/#/impor](https://app.profpreneurs.com/#/impor)  
**Tujuan:** Impor batch penjualan dari export POS, marketplace dan platform pesan antar.

**Isi/field dan kontrol:** Download template Excel; upload .xlsx/.csv; default Cabang, Channel, Akun Penerimaan, Akun Pendapatan, Akun Pendapatan Lain, Akun Biaya Admin.

**Tabel/panel yang ada:** Panel cara posting otomatis; Pratinjau Import; Riwayat Import (Waktu, File, Channel, Transaksi, Total Tagihan, aksi).

**Aksi yang harus diakomodasi:** Mapping/default, pratinjau, validasi, import, lihat riwayat dan batalkan batch.

**Aturan & dependensi dari referensi:** Per No Order -> satu invoice; diskon, pajak, biaya admin, ongkir, HPP stok bila diisi; skip order duplikat. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Tahap validasi dan dedupe server wajib; impor besar mempertimbangkan limit proses cPanel.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 03. Input Beban

**URL referensi:** [https://app.profpreneurs.com/#/beban](https://app.profpreneurs.com/#/beban)  
**Tujuan:** Mencatat pengeluaran operasional dibayar atau masih berutang.

**Isi/field dan kontrol:** Cabang, tanggal, jenis pembayaran Cash/Bank/Utang Usaha, akun beban dari awalan 5, keterangan, total, sampai 5 lampiran.

**Tabel/panel yang ada:** Form satu pengeluaran dengan petunjuk status pembayaran.

**Aksi yang harus diakomodasi:** Submit & reset; link EDIT DATA.

**Aturan & dependensi dari referensi:** Debit beban, kredit kas/bank atau hutang; kategori mengikuti Master Akun. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Hitung jurnal, saldo bank dan utang sesuai status.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 04. Input Persediaan

**URL referensi:** [https://app.profpreneurs.com/#/persediaan](https://app.profpreneurs.com/#/persediaan)  
**Tujuan:** Pembelian/pemakaian stok dengan valuasi moving average per cabang.

**Isi/field dan kontrol:** Cabang, tanggal, jenis transaksi kas/bank/utang/pemakaian, jenis persediaan, keterangan, quantity, harga satuan, ongkir & pajak, total harga, bukti.

**Tabel/panel yang ada:** Form + total kalkulasi; link EDIT DATA dan Laporan Persediaan.

**Aksi yang harus diakomodasi:** Beli atau keluarkan stok, validasi stok tidak minus, submit.

**Aturan & dependensi dari referensi:** Pembelian = qty × harga + ongkir+pajak; pemakaian berdasarkan harga rata-rata stok cabang dan mencatat HPP. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Tetapkan aturan backdate dan rekonsiliasi stock-to-GL secara eksplisit.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 05. Input Pelunasan Piutang

**URL referensi:** [https://app.profpreneurs.com/#/piutang](https://app.profpreneurs.com/#/piutang)  
**Tujuan:** Pelunasan satu invoice yang masih terbuka, termasuk cicilan.

**Isi/field dan kontrol:** Tanggal bayar, nomor invoice, customer otomatis, cabang otomatis, tanggal invoice, sisa piutang, jumlah dibayar, akun cash/bank, keterangan, sisa setelah bayar, bukti.

**Tabel/panel yang ada:** Bagian Invoice belum lunas—customer yang sama; ringkasan customer berpiutang, invoice terbuka, outstanding.

**Aksi yang harus diakomodasi:** Pilih invoice terbuka, bayar sebagian/penuh, submit.

**Aturan & dependensi dari referensi:** Cabang mengikuti invoice dan pembayaran tidak boleh lebih dari outstanding; jurnal debit cash/bank kredit piutang. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Jangan pakai cabang pertama user jika invoice memiliki cabang berbeda.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 06. Input Pembelian Asset

**URL referensi:** [https://app.profpreneurs.com/#/asset](https://app.profpreneurs.com/#/asset)  
**Tujuan:** Pembelian aset tetap dan dasar penyusutannya.

**Isi/field dan kontrol:** Cabang, tanggal, jenis pembayaran Cash/Bank/Utang Usaha, akun aset, keterangan, total harga, bukti.

**Tabel/panel yang ada:** Form input dan shortcut EDIT DATA / SIMULASI ASSET.

**Aksi yang harus diakomodasi:** Submit lalu lihat simulasi & register aset.

**Aturan & dependensi dari referensi:** Umur dan akun akumulasi mengikuti Template Jurnal; penyusutan dimulai bulan berikutnya. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Perolehan dan penyusutan harus masuk laporan aset, laba rugi dan neraca.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 07. Input Pindah Saldo Kas/Bank

**URL referensi:** [https://app.profpreneurs.com/#/kasbank](https://app.profpreneurs.com/#/kasbank)  
**Tujuan:** Mencatat transfer serta transaksi kas, bank, hutang, pajak dan modal.

**Isi/field dan kontrol:** Cabang, tanggal, jenis transaksi, Dari Akun (Kredit), Nilai Dari, Ke Akun (Debit), Nilai Ke, keterangan, saldo akun dari/sisa, bukti.

**Tabel/panel yang ada:** Tabel saldo seluruh kas/bank saat ini dengan total.

**Aksi yang harus diakomodasi:** Pilih 11 kategori jenis transaksi: transfer, tarik/setor tunai, terima pinjaman, bayar hutang, setor pajak, tambah modal, prive, pembayaran & pengembalian uang muka, terima bunga.

**Aturan & dependensi dari referensi:** Akun Dari/Ke dibatasi oleh jenis transaksi; nilai debit sama dengan kredit dan sisa saldo ditampilkan. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Satu layar multi-purpose dengan form dinamis, bukan sekadar transfer bank.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 08. Input Saldo Neraca Akhir Periode

**URL referensi:** [https://app.profpreneurs.com/#/saldo](https://app.profpreneurs.com/#/saldo)  
**Tujuan:** Mengisi saldo awal neraca secara langsung untuk cabang dan tahun buku.

**Isi/field dan kontrol:** Pilih cabang; grid 45 akun dengan posisi plus/minus dan nilai saldo; kartu total aset, total kewajiban, ekuitas, selisih dan status.

**Tabel/panel yang ada:** Grid No | Kode Akun | Nama Akun | Posisi | Saldo Awal | Nilai ke Neraca, dikelompokkan jenis akun.

**Aksi yang harus diakomodasi:** Input positif, autosave menurut UI, tinjau balance per cabang.

**Aturan & dependensi dari referensi:** Alternatif saling eksklusif dengan Setoran Modal Awal pada cabang yang sama; akun pengurang bernilai negatif otomatis. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Pastikan saldo awal dapat di-audit walau UI referensi autosave; tidak double-post.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 09. Input Setoran Modal Awal

**URL referensi:** [https://app.profpreneurs.com/#/modal](https://app.profpreneurs.com/#/modal)  
**Tujuan:** Cut-off inventaris harta dan kewajiban lalu menghitung modal awal.

**Isi/field dan kontrol:** Cabang, tanggal cut-off, bukti, baris akun, keterangan item, posisi, nilai, info penyusutan untuk fixed asset.

**Tabel/panel yang ada:** Grid inventaris harta/hutang; bagian Modal Awal yang Sudah Tercatat.

**Aksi yang harus diakomodasi:** Tambah baris, hitung modal, submit, lihat data sebelumnya.

**Aturan & dependensi dari referensi:** Untuk bisnis berjalan, harta dikurangi hutang; aset cut-off menjadi register aset dan lanjut disusutkan. Jangan pakai bersamaan dengan Saldo Neraca. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Buat per-branch cut-off kontrol dan posting tepat sekali.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 10. Input Jurnal Manual

**URL referensi:** [https://app.profpreneurs.com/#/jm](https://app.profpreneurs.com/#/jm)  
**Tujuan:** Pencatatan jurnal yang tidak ditangani menu transaksi.

**Isi/field dan kontrol:** Cabang, tanggal, bukti, 2–6 baris akun/keterangan/debit/kredit/kode/status.

**Tabel/panel yang ada:** Grid No | Akun | Keterangan | Debit | Kredit | Kode Akun | Status; daftar jurnal manual tercatat.

**Aksi yang harus diakomodasi:** Pilih akun, periksa jumlah Debit/Kredit, submit seimbang.

**Aturan & dependensi dari referensi:** Setiap transaksi manual wajib balance; digunakan untuk koreksi, penyesuaian, dividen. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Nomor jurnal/source reference/period harus dapat ditelusuri.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.
