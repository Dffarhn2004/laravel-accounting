# PRD — Anggaran

**Evidence:** [U] tampilan halaman ter-render; [G] Panduan Penggunaan publik. Inspeksi 2026-11-01, data transaksi demo belum diisi. Setiap link halaman adalah sumber primer tampilan; aturan lanjutan mengacu https://app.profpreneurs.com/#/panduan.

## 01. Input Anggaran

**URL referensi:** [https://app.profpreneurs.com/#/ang-input](https://app.profpreneurs.com/#/ang-input)  
**Tujuan:** Masukan budget berdasarkan kegiatan dan volume bulanan.

**Isi/field dan kontrol:** Tahun, cabang, akun Laba Rugi, rincian kegiatan, satuan, tarif, Isi Cepat Qty, 12 Qty per bulan.

**Tabel/panel yang ada:** Matriks Jan–Des Qty dan Rp serta TOTAL; tabel ringkasan jumlah baris, pendapatan, beban, laba per cabang.

**Aksi yang harus diakomodasi:** Simpan, buka Data Anggaran atau Rekap Anggaran.

**Aturan & dependensi dari referensi:** Anggaran nilai bulan = Qty × Tarif; hanya akun kode 4/5, tidak dihitung kuota trial. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Wajib simpan qty dan tarif, bukan hanya 12 total nominal.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 02. Data Anggaran

**URL referensi:** [https://app.profpreneurs.com/#/ang-data](https://app.profpreneurs.com/#/ang-data)  
**Tujuan:** Register kegiatan anggaran dan nominal per bulan.

**Isi/field dan kontrol:** Filter tahun, cabang, Cari, + Input Anggaran.

**Tabel/panel yang ada:** Aksi | Cabang | Kode Akun | Nama Akun | Rincian Kegiatan | Satuan | Tarif | pasangan Qty/Rp Jan–Des | TOTAL.

**Aksi yang harus diakomodasi:** Edit/Hapus, export CSV (panduan), kembali ke form input.

**Aturan & dependensi dari referensi:** Setiap baris = cabang + akun + kegiatan. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Edit menjaga konsistensi target dan aktual.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 03. Rekap Anggaran

**URL referensi:** [https://app.profpreneurs.com/#/ang-rekap](https://app.profpreneurs.com/#/ang-rekap)  
**Tujuan:** Matriks laba rugi anggaran sepanjang tahun.

**Isi/field dan kontrol:** Cabang/all, tahun.

**Tabel/panel yang ada:** KPI pendapatan, beban, target laba, net margin; Kode/Nama akun, Jan–Des, TOTAL, % Rasio.

**Aksi yang harus diakomodasi:** Filter per cabang atau konsolidasi, export.

**Aturan & dependensi dari referensi:** Struktur sama dengan Laporan Laba Rugi aktual. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Gunakan classification report bersama antara budget dan P&L.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.

---

## 04. Laporan Kinerja Anggaran

**URL referensi:** [https://app.profpreneurs.com/#/ang-kinerja](https://app.profpreneurs.com/#/ang-kinerja)  
**Tujuan:** Perbandingan anggaran vs aktual jurnal untuk periode dashboard.

**Isi/field dan kontrol:** Cabang/all dan periode awal/akhir.

**Tabel/panel yang ada:** KPI pencapaian pendapatan, penyerapan beban, laba; tabel No Akun | Nama Akun | ANGGARAN | AKTUAL | ACHIEVEMENT (%).

**Aksi yang harus diakomodasi:** Filter periode, lihat selisih/warna status.

**Aturan & dependensi dari referensi:** Hijau pendapatan & laba >=100%, beban <=100%; kuning dekat target; merah jauh. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Jika target 0, rasio persen harus N/A dan dijelaskan.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.


---

# PRD — Bagi Hasil & Dividen (menu mandiri, tanpa submenu)

## 01. Bagi Hasil & Dividen

**URL referensi:** [https://app.profpreneurs.com/#/dividen](https://app.profpreneurs.com/#/dividen)  
**Tujuan:** Simulasi pembagian profit ke pemilik/pemegang saham.

**Isi/field dan kontrol:** Net income otomatis dari LR konsolidasi, Dividend % yang disepakati, baris nama pemilik, persentase kepemilikan.

**Tabel/panel yang ada:** Ringkasan net income/dividen dibagikan/laba ditahan; tabel pemilik % dan estimasi dividen, total 100%.

**Aksi yang harus diakomodasi:** Ubah pembagian, tampilkan proyeksi, petunjuk jurnal deklarasi dan pembayaran.

**Aturan & dependensi dari referensi:** Porsi pemilik harus total 100%; contoh referensi pembagian 30–50% adalah saran, bukan batas hukum. [U][G]

**Acceptance minimum untuk Laravel (usulan [D]):** Bagi hasil tetap satu menu seperti referensi, bukan dua submenu berbeda.

**Status parity Laravel:** BELUM DIVERIFIKASI END-TO-END; jangan menilai dari adanya route/file saja.
