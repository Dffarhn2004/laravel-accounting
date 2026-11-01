# PRD — System

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Panduan Penggunaan

**URL referensi:** [https://app.profpreneurs.com/#/panduan](https://app.profpreneurs.com/#/panduan)  
**Tujuan:** Dokumentasi urutan kerja dan ketentuan aplikasi.

**Isi/field dan kontrol:** Daftar isi 13 bagian: Ketentuan Umum; Struktur Menu; Penyimpanan Data; Dashboard & Periode; Setting Awal; Cabang; Saldo Awal/Modal Awal; Transaksi Harian; Data Transaksi & Bukti; Membaca Laporan; Anggaran; Dividen; Tutup Buku.

**Tabel/panel yang ada:** Panduan berbentuk bagian teks bernomor, bukan form input.

**Aksi yang harus diakomodasi:** Link ke konfigurasi dan fitur terkait.

**Aturan & dependensi dari referensi:** Urutan onboarding yang dijelaskan: setting awal → saldo/modal awal per cabang → transaksi → anggaran → laporan. [G]

**Acceptance minimum untuk Laravel (usulan [D]):** Semua tautan dalam panduan menuju menu aslinya; konten ditulis ulang dengan identitas produk sendiri.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 02. Master Akun

**URL referensi:** [https://app.profpreneurs.com/#/master](https://app.profpreneurs.com/#/master)  
**Tujuan:** Kelola Chart of Accounts bawaan serta definisi setiap akun.

**Isi/field dan kontrol:** Kode, nama, modul, jenis, definisi; pengelompokan awalan 1 Aset, 2 Kewajiban, 3 Ekuitas, 4 Pendapatan, 5 Beban.

**Tabel/panel yang ada:** Tabel Kode Akun | Nama | Modul | Jenis | Definisi | aksi.

**Aksi yang harus diakomodasi:** Ganti nama/definisi, tambah akun dalam kelompok, batasi penghapusan akun terpakai.

**Aturan & dependensi dari referensi:** Saldo normal: 1 & 5 debit; 2/3/4 kredit. Nama yang diubah muncul di menu dan laporan. [G]

**Acceptance minimum untuk Laravel (usulan [D]):** Akun bawaan tidak boleh hanya 4 contoh; COA harus cukup menggerakkan semua report dan transaksi.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 03. Template Jurnal

**URL referensi:** [https://app.profpreneurs.com/#/template](https://app.profpreneurs.com/#/template)  
**Tujuan:** Konfigurasi referensi master transaksi, cabang, produk, stok dan penentuan akun.

**Isi/field dan kontrol:** 10 bagian: cabang/kota/brand; template tahun; produk dan harga; jenis persediaan; setting akun & umur aset; metode pembayaran invoice & pelunasan; metode pembayaran beban/aset/persediaan; jenis transaksi kas/bank/hutang/modal; kategori akun Dari/Ke; akun yang boleh dipakai di modal awal.

**Tabel/panel yang ada:** Grid master dapat diedit per bagian; tabel cabang, produk, persediaan, aturan akun.

**Aksi yang harus diakomodasi:** Tambah/ubah baris, mapping akun, kategori transaksi dan masa manfaat; pembaruan berefek pada dropdown formulir lain.

**Aturan & dependensi dari referensi:** Perubahan di template menjadi pilihan pada form terkait; cabang dengan histori transaksi tidak boleh dihapus. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Semua 10 subbagian tetap terlihat dalam satu menu Template Jurnal sebelum dipertimbangkan pengelompokan UX lebih lanjut.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 04. Tutup Buku & Pindah Tahun

**URL referensi:** [https://app.profpreneurs.com/#/tutup](https://app.profpreneurs.com/#/tutup)  
**Tujuan:** Preview dan konfirmasi perpindahan tahun keuangan.

**Isi/field dan kontrol:** Tahun tutup, tabel per cabang aset/kewajiban/ekuitas/laba/status balance; daftar saldo dan dokumen yang dibawa; checklist pratutup.

**Tabel/panel yang ada:** Tabel Cabang | Total Aset | Kewajiban | Ekuitas | Laba | Balance; ringkasan jumlah invoice, hutang, stok, aset.

**Aksi yang harus diakomodasi:** Pratinjau, centang pernyataan, tutup buku setelah seluruh pemeriksaan lolos, unduh arsip JSON.

**Aturan & dependensi dari referensi:** Referensi membawa saldo per cabang, invoice & hutang terbuka, stok, aset berjalan, master, anggaran dan pemegang saham. Referensi mengarsipkan lalu melepas transaksi lama dari browser. Laravel jangan merusak histori posted. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Tidak boleh sekadar status closed; wajib carry-forward dan tes rekonsiliasi.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 05. Aktivasi Lisensi

**URL referensi:** [https://app.profpreneurs.com/#/aktivasi](https://app.profpreneurs.com/#/aktivasi)  
**Tujuan:** Status dan aktivasi versi trial referensi.

**Isi/field dan kontrol:** Status trial 20 transaksi, nama/nama usaha, email pembelian, kode aktivasi; petunjuk pembelian; install aplikasi/PWA.

**Tabel/panel yang ada:** Status lisensi/kuota, form aktivasi, tombol bantuan/pembelian.

**Aksi yang harus diakomodasi:** Isi dan validasi aktivasi, tampilkan status lisensi, akses instalasi aplikasi.

**Aturan & dependensi dari referensi:** Aktivasi memakai email dan kode; aplikasi offline. Model lisensi Laravel harus diputuskan sebelum implementasi backend. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Menu tetap ditampilkan di sidebar selama fase parity, backend dapat bertanda pending business decision.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 06. Pengaturan & Backup

**URL referensi:** [https://app.profpreneurs.com/#/setting](https://app.profpreneurs.com/#/setting)  
**Tujuan:** Identitas perusahaan, default pajak dan portabilitas data.

**Isi/field dan kontrol:** Nama usaha, alamat & kontak, rekening invoice, default PPN, default PPh23, status data lokal, restore, data contoh, reset.

**Tabel/panel yang ada:** Tiga blok: Identitas Usaha; Backup & Restore; Data Contoh & Reset.

**Aksi yang harus diakomodasi:** Simpan identitas/default, backup JSON termasuk lampiran, restore yang mengganti data pada referensi, contoh/reset dengan konfirmasi.

**Aturan & dependensi dari referensi:** Referensi offline browser-local, tidak autosync; online Laravel bisa berbeda secara arsitektur tetapi harus mendokumentasikan deviasi ini. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Fitur backup/restore tidak boleh dianggap sudah ada hanya karena ada tombol UI.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.
