# PRD — Data Transaksi

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Data Pendapatan

**URL referensi:** [https://app.profpreneurs.com/#/d-pendapatan](https://app.profpreneurs.com/#/d-pendapatan)  
**Tujuan:** Register detail setiap baris produk/pendapatan dari invoice dan impor.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Edit/Hapus dan bukti per baris.

**Tabel/panel yang ada:** 26 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | No INV | Nama Customer | Kode | Jenis Pembayaran | Total Bank/Cash | Kode | PPh 23 | Kode | Beban Discount | Kode | Kelompok Pendapatan | Total Pendapatan | Kode | PPN | Kode | Piutang | Produk | Qty | Harga Satuan | Disc %.

**Aksi yang harus diakomodasi:** Cari/filter, buka bukti, tambah/unduh/hapus lampiran, edit/hapus menurut referensi, export CSV menurut panduan.

**Aturan & dependensi dari referensi:** Kolom rumus akun lawan dihitung otomatis; jumlah dan total. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Untuk Laravel, edit/hapus posted harus melalui reversal/koreksi yang audit-able.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 02. Data Beban

**URL referensi:** [https://app.profpreneurs.com/#/d-beban](https://app.profpreneurs.com/#/d-beban)  
**Tujuan:** Register beban operasional.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 14 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | Jenis Pembayaran | Kode Akun | Akun Beban | Keterangan | Total Transaksi | Kode Lawan | Nama Akun Lawan | Nilai Lawan.

**Aksi yang harus diakomodasi:** Lihat, koreksi, bukti, export CSV.

**Aturan & dependensi dari referensi:** Jurnal ditelusuri ke akun beban dan akun lawan. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Kesesuaian total register ke jurnal.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 03. Data Persediaan

**URL referensi:** [https://app.profpreneurs.com/#/d-persediaan](https://app.profpreneurs.com/#/d-persediaan)  
**Tujuan:** Register arus dan nilai persediaan.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 19 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | Jenis Transaksi | Jenis Persediaan | Kode Akun | Nama Akun | Keterangan | Qty | Harga Satuan | Ongkir | Pajak | Total Harga | Kode Lawan | Nama Akun Lawan | Nilai Lawan.

**Aksi yang harus diakomodasi:** Filter, lihat bukti, koreksi dan ekspor.

**Aturan & dependensi dari referensi:** Mutasi beli/pemakaian berhubungan dengan saldo stok per cabang. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Jaga valuation bila transaksi dibalik atau backdated.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 04. Data Pelunasan Piutang

**URL referensi:** [https://app.profpreneurs.com/#/d-piutang](https://app.profpreneurs.com/#/d-piutang)  
**Tujuan:** Riwayat pembayaran invoice yang pernah dilakukan.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 16 kolom: Aksi | Bukti | No | Bulan | Tanggal Bayar | Cabang | No Invoice | Nama Customer | Keterangan | Kode Akun | Jenis Pembayaran | Jumlah Dibayar | Kode Piutang | Nama Akun Piutang | Total Tagihan Invoice | Sisa Setelah Bayar.

**Aksi yang harus diakomodasi:** Filter, lihat bukti, koreksi dan ekspor.

**Aturan & dependensi dari referensi:** Pembayaran harus menyambung ke invoice dan saldo piutang. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Alokasi piutang harus idempotent.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 05. Data Asset

**URL referensi:** [https://app.profpreneurs.com/#/d-asset](https://app.profpreneurs.com/#/d-asset)  
**Tujuan:** Register transaksi perolehan aset tetap.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 17 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | Jenis Pembayaran | Kode Akun | Akun Asset | Keterangan | Total Transaksi | Kode Lawan | Nama Akun Lawan | Nilai Lawan | Umur (thn) | Penyusutan/Bulan | Tahun Berakhir.

**Aksi yang harus diakomodasi:** Filter, bukti, akses simulasi.

**Aturan & dependensi dari referensi:** Beban penyusutan otomatis terkait register aset. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Aset tidak hilang dari register bila bulan ditutup.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 06. Simulasi & Output D&A

**URL referensi:** [https://app.profpreneurs.com/#/simulasi](https://app.profpreneurs.com/#/simulasi)  
**Tujuan:** Simulasi garis lurus dan output jurnal depresiasi.

**Isi/field dan kontrol:** Tahun fiskal dan as-of posting; rincian per asset, kelompok dan bulan.

**Tabel/panel yang ada:** Tiga tabel: jadwal aset (bulan Jan–Des + biaya + akumulasi + nilai buku), rekap beban per kelompok Jan–Des, daftar jurnal otomatis Debit Beban/Kredit Akumulasi.

**Aksi yang harus diakomodasi:** Tinjau jadwal, status penyusutan, total dan posting dari sumber yang sah.

**Aturan & dependensi dari referensi:** Dimulai bulan setelah perolehan, rekonsiliasi biaya depresiasi dan akumulasi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Tidak boleh sekadar menampilkan kartu total aset.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 07. Data Kas & Bank

**URL referensi:** [https://app.profpreneurs.com/#/d-kasbank](https://app.profpreneurs.com/#/d-kasbank)  
**Tujuan:** Register semua transfer dan mutasi kas/bank/hutang/modal.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 14 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | Jenis Transaksi | Keterangan | Kode Ke (Debit) | Nama Akun Ke | Nilai Debit | Kode Dari (Kredit) | Nama Akun Dari | Nilai Kredit.

**Aksi yang harus diakomodasi:** Filter, bukti, koreksi, export.

**Aturan & dependensi dari referensi:** Debit=Kredit untuk masing-masing transaksi. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Dari dan Ke harus bisa dilacak di GL.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 08. Data Modal Awal

**URL referensi:** [https://app.profpreneurs.com/#/d-modal](https://app.profpreneurs.com/#/d-modal)  
**Tujuan:** Register saldo inventaris cut-off modal awal.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 16 kolom: Aksi | Bukti | No | Bulan | Tanggal Cut-off | Cabang | Akun | Keterangan | Nilai | Posisi | Kode Debit | Nama Debit | Nilai Debit | Kode Kredit | Nama Kredit | Nilai Kredit.

**Aksi yang harus diakomodasi:** Filter, bukti, koreksi, export.

**Aturan & dependensi dari referensi:** Item harta/hutang berkontribusi pada modal awal per cabang. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Saling eksklusif dengan input saldo neraca.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 09. Data Jurnal Manual

**URL referensi:** [https://app.profpreneurs.com/#/d-jm](https://app.profpreneurs.com/#/d-jm)  
**Tujuan:** Register sumber jurnal manual.

**Isi/field dan kontrol:** Cari, Cabang, + Input Baru; Aksi/Bukti.

**Tabel/panel yang ada:** 12 kolom: Aksi | Bukti | No | Bulan | Tanggal | Cabang | Akun | Keterangan | Debit | Kredit | Kode Akun | Cek Balance (per Tanggal).

**Aksi yang harus diakomodasi:** Filter, bukti, koreksi, export.

**Aturan & dependensi dari referensi:** Periksa balance di level transaksi, bukan hanya total tanggal pada target. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Reversal dan audit trail pada Laravel untuk koreksi.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.
