import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Construction } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const modules: Record<string, { title: string; description: string; features: string[] }> = {
    master: { title: 'Master & Sistem', description: 'Konfigurasi untuk memulai pembukuan.', features: ['Profil usaha dan cabang', 'Chart of Accounts', 'Produk dan daftar harga', 'Template jurnal dan tahun buku'] },
    transaksi: { title: 'Input Transaksi', description: 'Satu kali input, posting jurnal akan berjalan otomatis.', features: ['Invoice dan penjualan', 'Beban dan pengeluaran', 'Kas dan bank', 'Persediaan, aset, saldo awal', 'Pelunasan piutang dan jurnal manual'] },
    data: { title: 'Data Transaksi', description: 'Daftar transaksi dan bukti pendukung dengan audit trail.', features: ['Pendapatan dan beban', 'Persediaan dan piutang', 'Kas/bank, modal, aset', 'Filter, ekspor, edit dengan pembalikan jurnal'] },
    laporan: { title: 'Laporan Keuangan', description: 'Angka bersumber dari satu buku besar yang konsisten.', features: ['Jurnal Umum dan Buku Besar', 'Laba Rugi dan Neraca', 'Arus Kas', 'Piutang, hutang, persediaan, pajak', '19 rasio kesehatan bisnis'] },
    anggaran: { title: 'Anggaran', description: 'Rencanakan budget dan ukur realisasi bulanan.', features: ['Input budget', 'Daftar dan rekap', 'Pencapaian budget vs aktual'] },
    dividen: { title: 'Bagi Hasil & Dividen', description: 'Estimasi pembagian laba pemegang saham.', features: ['Komposisi kepemilikan', 'Persentase laba dibagikan', 'Dividen per pemegang saham'] },
};

export default function ModuleShow({ section }: { section: string }) {
    const module = modules[section];

    return (
        <>
            <Head title={module?.title ?? 'Modul'} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
                <div>
                    <Link href="/dashboard" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" /> Kembali ke dashboard
                    </Link>
                    <div className="mt-5 flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight">{module?.title}</h1>
                        <Badge variant="outline">Belum diimplementasikan</Badge>
                    </div>
                    <p className="mt-2 text-muted-foreground">{module?.description}</p>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2"><Construction className="size-5" /> Rencana fitur</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {module?.features.map((feature) => (
                                <li key={feature} className="rounded-lg border bg-muted/20 p-4 text-sm font-medium">{feature}</li>
                            ))}
                        </ul>
                        <p className="mt-6 text-sm text-muted-foreground">
                            Ini adalah kerangka navigasi sesuai PRD. Belum ada transaksi atau pencatatan keuangan aktif.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
