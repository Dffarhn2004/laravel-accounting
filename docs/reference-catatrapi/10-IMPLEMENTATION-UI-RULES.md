# UI Implementation Rules — exact menu, modern presentation

## Sidebar: identik, bukan redesign IA
Gunakan **NAVIGATION-CONTRACT.json** sebagai single source of truth untuk label, urutan, grup, jumlah item dan href referensi. Backend Laravel boleh memetakan route berbeda, tetapi menu tetap **Dashboard / System / Inputan Transaksi / Data Transaksi / Laporan / Anggaran / Bagi Hasil & Dividen / Logout** dengan semua anak sama. Jangan mengganti arsitektur sidebar menjadi Master Data / Transaksi / Akuntansi terlebih dahulu.

## Form dan tabel
- Halaman input milik grup Inputan Transaksi harus tetap berupa **halaman input tersendiri** (bukan modal untuk invoice/jurnal/saldo). Form besar mendapat ruang penuh, label jelas, ringkasan sticky jika berguna.
- Halaman **Data Transaksi** harus tetap sembilan halaman sesuai referensi. Tabel data sangat lebar: gunakan TanStack Table + shadcn Table, scroll horizontal, sticky index/action/key fields, multi-column filter, tabular monetary values, export yang bekerja. Boleh ringkas visually dengan column visibility, **tidak boleh membuang data yang tersedia pada referensi**.
- Tambah master sederhana (Master Akun, Template rows): tombol 'Tambah' membuka dialog/sheet sesuai konteks; jangan memaksa form permanen memakan bagian atas list. Namun CRUD master tetap dapat dilakukan di menu asli, bukan submenu baru.
- Input Saldo Neraca = grid 45 akun dengan validation status; Input Modal Awal = daftar item asset/liability; keduanya **tidak sama** dan tidak boleh dipakai bersamaan pada cabang yang sama.
- Simulasi & Output D&A harus tetap di **Data Transaksi** meskipun berisi laporan 3 tabel.
- Laporan finansial menggunakan FinancialReportTable hierarkis: akun dan kelompok, subtotal, gross margin/EBITDA/EBIT/EBT, matriks Jan–Des dan side-by-side cabang. Tabel transaksi standar saja tidak mencukupi.
- Dashboard mengikuti enam KPI plus semua analitik/integrity sekunder yang dijabarkan di 07-DASHBOARD.md. Jangan pakai nilai contoh seolah fakta usaha.

## Konsistensi tindakan
- Reference menggunakan Edit/Hapus pada Data Transaksi; Laravel **tidak** boleh menghapus posted journals tanpa jejak. Berikan operasi pembalikan/koreksi yang menghasilkan hasil bisnis ekuivalen, dengan jejak audit.
- Bukti transaksi reference mendukung maksimal 5 lampiran image/PDF; di Laravel gunakan authorized storage/preview/download.
- Untuk setiap input, tombol submit harus memanggil server validation yang benar, punya feedback sukses/gagal dan link ke data terkait.
- Filter cabang dan periode harus konsisten di semua laporan; jangan simpan hanya di UI sementara report query mengabaikannya.
- Tidak membuat fitur placeholder yang dapat diklik tanpa endpoint bekerja. Jika belum tersedia, pertahankan menu dengan label 'Belum tersedia' jujur untuk fase parity.
- Tetap mendukung deploy Laravel+React/Inertia dengan static Vite build di cPanel; tidak wajib Next.js/SSR/daemon.

## Rollout route
Prioritaskan kontrak nama menu, bukan slug Laravel. Route hash CatatRapi adalah **evidence path**, bukan instruksi menggunakan hash router di Laravel. Buat route name yang stabil dan mapping satu per menu; jangan memecahkan/merename endpoint posting lama sebelum ada tes regresi.

## Definition of done
Satu menu = halaman benar-benar ada, semua field penting, tabel/filter lengkap, tindakan aman, integrasi accounting benar dan tests lulus. Setelah parity functional sign-off, baru pertimbangkan perubahan navigasi untuk efisiensi UX dengan persetujuan user.
