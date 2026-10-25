import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ChartNoAxesCombined, LockKeyhole, NotebookPen, ShieldCheck } from 'lucide-react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Akuntansi Bisnis yang Lebih Rapi" />
            <main className="flex min-h-screen flex-col bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-7xl items-center justify-between p-6 md:px-10">
                    <div className="flex items-center gap-3 text-lg font-bold tracking-tight">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-foreground text-background"><ChartNoAxesCombined className="size-5" /></span>
                        Laravel Accounting
                    </div>
                    <Link href={auth.user ? dashboard() : login()} className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-colors hover:bg-muted">
                        {auth.user ? 'Buka dashboard' : 'Masuk'} <ArrowRight className="size-4" />
                    </Link>
                </div>

                <div className="mx-auto grid w-full max-w-7xl flex-1 items-center gap-12 px-6 py-14 md:px-10 lg:grid-cols-2">
                    <div className="max-w-xl space-y-7">
                        <span className="inline-flex items-center rounded-full border bg-muted/40 px-3 py-1 text-xs font-semibold tracking-wide">Laravel 13 · React · Inertia</span>
                        <h1 className="text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                            Kelola keuangan bisnis dengan lebih <span className="text-muted-foreground">terstruktur.</span>
                        </h1>
                        <p className="text-base leading-7 text-muted-foreground">
                            Fondasi aplikasi akuntansi untuk pencatatan transaksi, laporan konsolidasi, dan analisis bisnis di berbagai cabang.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Link href={auth.user ? dashboard() : login()} className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
                                {auth.user ? 'Masuk ke workspace' : 'Masuk ke aplikasi'} <ArrowRight className="size-4" />
                            </Link>
                            {!auth.user && <Link href={register()} className="inline-flex items-center rounded-lg border px-5 py-3 text-sm font-semibold hover:bg-muted">Daftar akun</Link>}
                        </div>
                        <p className="text-xs text-muted-foreground">Tahap awal pengembangan. Modul transaksi dan laporan belum tersedia untuk penggunaan produksi.</p>
                    </div>
                    <div className="grid gap-4 rounded-3xl border bg-muted/20 p-5 shadow-sm md:p-7">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-semibold">Accounting workspace</span>
                            <span className="rounded-full border px-3 py-1 text-xs text-muted-foreground">Fondasi</span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {[
                                { icon: NotebookPen, title: 'Satu kali input', text: 'Rencana posting jurnal otomatis' },
                                { icon: ChartNoAxesCombined, title: 'Laporan bisnis', text: 'Neraca, laba rugi dan arus kas' },
                                { icon: ShieldCheck, title: 'Integritas data', text: 'Validasi debit dan kredit' },
                                { icon: LockKeyhole, title: 'Akses terlindungi', text: 'Autentikasi dari starter resmi' },
                            ].map(({ icon: Icon, title, text }) => (
                                <div key={title} className="rounded-2xl border bg-card p-5">
                                    <Icon className="mb-5 size-6 text-muted-foreground" />
                                    <h2 className="font-semibold">{title}</h2>
                                    <p className="mt-2 text-xs leading-5 text-muted-foreground">{text}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}
