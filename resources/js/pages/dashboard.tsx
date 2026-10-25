import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    BookCheck,
    ChartNoAxesCombined,
    CircleDollarSign,
    HandCoins,
    Landmark,
    Layers3,
    Plus,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

const metrics = [
    { label: 'Pendapatan', note: 'Periode berjalan', icon: ChartNoAxesCombined },
    { label: 'Laba (Rugi) Bersih', note: 'Setelah seluruh beban', icon: CircleDollarSign },
    { label: 'Kas & Setara Kas', note: 'Kas dan seluruh rekening', icon: Wallet },
    { label: 'Total Aset', note: 'Nilai buku dan aset lancar', icon: Landmark },
    { label: 'Piutang Outstanding', note: 'Invoice belum dilunasi', icon: ReceiptText },
    { label: 'Hutang Outstanding', note: 'Transaksi belum dilunasi', icon: HandCoins },
];

const shortcuts = [
    { title: 'Buat Invoice', description: 'Catat penjualan dan piutang', href: '/modules/transaksi', icon: Plus },
    { title: 'Input Pengeluaran', description: 'Biaya dan pembayaran', href: '/modules/transaksi', icon: Banknote },
    { title: 'Jurnal & Buku Besar', description: 'Tinjau transaksi akuntansi', href: '/modules/laporan', icon: BookCheck },
    { title: 'Buat Anggaran', description: 'Target pendapatan dan biaya', href: '/modules/anggaran', icon: Layers3 },
];

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard Keuangan" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">Accounting workspace</Badge>
                            <Badge variant="outline">Tahap fondasi</Badge>
                        </div>
                        <h1 className="pt-2 text-3xl font-semibold tracking-tight md:text-4xl">Ringkasan Keuangan</h1>
                        <p className="max-w-xl text-sm text-muted-foreground">
                            Satu tempat untuk memantau bisnis, transaksi, dan kondisi keuangan di seluruh cabang.
                        </p>
                    </div>
                    <Link href="/modules/transaksi" className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90">
                        <Plus className="size-4" /> Input transaksi
                    </Link>
                </header>

                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card px-4 py-3 text-sm">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="size-4 text-muted-foreground" />
                        <span className="font-medium">Workspace belum dikonfigurasi</span>
                    </div>
                    <span className="text-muted-foreground">Tidak ada data keuangan yang dihitung pada tahap ini.</span>
                </div>

                <section aria-label="Indikator keuangan utama" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {metrics.map(({ label, note, icon: Icon }) => (
                        <Card key={label} className="gap-4 py-5 shadow-xs">
                            <CardHeader className="flex flex-row items-start justify-between gap-2 px-5">
                                <CardDescription className="text-xs font-medium">{label}</CardDescription>
                                <Icon className="size-4 shrink-0 text-muted-foreground" />
                            </CardHeader>
                            <CardContent className="space-y-2 px-5">
                                <p className="text-2xl font-semibold tracking-tight tabular-nums">—</p>
                                <p className="text-xs text-muted-foreground">{note}</p>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Tren Bisnis</CardTitle>
                                <CardDescription>Pendapatan, beban, dan laba bersih per bulan</CardDescription>
                            </div>
                            <Badge variant="outline">Belum ada data</Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="flex min-h-64 flex-col items-center justify-center gap-3 rounded-lg border border-dashed bg-muted/10 p-6 text-center">
                                <ChartNoAxesCombined className="size-9 text-muted-foreground/60" />
                                <div>
                                    <p className="font-medium">Grafik siap dihubungkan</p>
                                    <p className="mt-1 max-w-md text-sm text-muted-foreground">Tren akan ditampilkan setelah modul invoice, beban, dan ledger terimplementasi.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Kesehatan Bisnis</CardTitle>
                            <CardDescription>19 rasio keuangan dan validasi integritas</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col justify-between gap-6">
                            <div className="rounded-lg bg-muted/30 p-5">
                                <div className="text-4xl font-semibold tabular-nums">—</div>
                                <p className="mt-1 text-sm text-muted-foreground">Skor belum dapat dihitung</p>
                            </div>
                            <Link href="/modules/laporan" className="inline-flex items-center gap-2 text-sm font-medium hover:underline">
                                Lihat rencana laporan <ArrowUpRight className="size-4" />
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Kinerja Cabang</CardTitle>
                            <CardDescription>Konsolidasi dan laba rugi setiap cabang</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex min-h-32 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">Belum ada cabang terdaftar</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Anggaran vs Aktual</CardTitle>
                            <CardDescription>Pencapaian target usaha dalam periode berjalan</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex min-h-32 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">Belum ada anggaran yang dibuat</div>
                        </CardContent>
                    </Card>
                </div>

                <section aria-label="Akses cepat" className="space-y-3">
                    <h2 className="text-lg font-semibold">Akses Cepat</h2>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {shortcuts.map(({ title, description, href, icon: Icon }) => (
                            <Link key={title} href={href} className="group flex items-center gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/50">
                                <span className="flex size-10 items-center justify-center rounded-lg bg-muted"><Icon className="size-5" /></span>
                                <span className="min-w-0 flex-1"><span className="block font-semibold">{title}</span><span className="block text-xs text-muted-foreground">{description}</span></span>
                                <ArrowUpRight className="size-4 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                            </Link>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
