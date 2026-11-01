import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, BarChart3, Building2, Check, ChevronRight, CircleDollarSign, FileText, Landmark, PieChart, ReceiptText, ShieldCheck, Sparkles, TrendingUp } from 'lucide-react';
import { dashboard, login, register } from '@/routes';
import ThemeToggle from '@/components/theme-toggle';

const features = [
    { icon: ReceiptText, title: 'Transaksi rapi', description: 'Catat penjualan dan beban dalam satu alur yang mudah ditelusuri.' },
    { icon: BarChart3, title: 'Laporan siap baca', description: 'Pantau laba rugi, neraca, dan arus kas dari data yang sama.' },
    { icon: Building2, title: 'Multi cabang', description: 'Lihat performa cabang tanpa kehilangan detail transaksi.' },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const primaryHref = auth.user ? dashboard() : login();

    return <>
        <Head title="Akuntansi bisnis yang lebih rapi" />
        <main className="min-h-screen overflow-hidden bg-[#f8fbfa] text-slate-900 selection:bg-emerald-200">
            <div className="pointer-events-none absolute inset-x-0 top-0 h-[42rem] bg-[radial-gradient(circle_at_75%_22%,#d8f5e9_0,transparent_30%),radial-gradient(circle_at_15%_8%,#e8f1ff_0,transparent_27%)]" />
            <nav className="relative mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                <Link href="/" className="flex items-center gap-3 font-semibold tracking-tight"><span className="grid size-10 place-items-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20"><Landmark className="size-5" /></span><span>Rapi<span className="text-emerald-600">Kas</span></span></Link>
                <div className="flex items-center gap-2"><ThemeToggle compact /><Link href={login()} className="hidden px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-950 sm:block">Masuk</Link><Link href={primaryHref} className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/15 transition hover:-translate-y-0.5 hover:bg-slate-800">{auth.user ? 'Buka dashboard' : 'Mulai sekarang'} <ArrowRight className="size-4" /></Link></div>
            </nav>

            <section className="relative mx-auto grid max-w-7xl items-center gap-14 px-6 pb-20 pt-14 lg:grid-cols-[1.02fr_.98fr] lg:px-8 lg:pb-28 lg:pt-20">
                <div className="max-w-2xl">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-white/75 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm backdrop-blur"><Sparkles className="size-3.5" /> Keuangan lebih jelas, keputusan lebih yakin</div>
                    <h1 className="text-4xl leading-[1.06] font-semibold tracking-[-0.045em] text-slate-950 sm:text-6xl lg:text-7xl">Kendalikan arus bisnis, <span className="text-emerald-600">bukan hanya angka.</span></h1>
                    <p className="mt-7 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">RapiKas membantu UMKM mencatat transaksi, memantau kesehatan usaha, dan membaca laporan keuangan tanpa spreadsheet yang membingungkan.</p>
                    <div className="mt-8 flex flex-wrap gap-3"><Link href={primaryHref} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3.5 text-sm font-semibold text-white shadow-xl shadow-emerald-600/20 transition hover:-translate-y-0.5 hover:bg-emerald-700">{auth.user ? 'Lanjut ke workspace' : 'Masuk ke aplikasi'} <ArrowRight className="size-4" /></Link>{!auth.user && <Link href={register()} className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Buat akun <ChevronRight className="size-4" /></Link>}</div>
                    <div className="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-600">{['Pencatatan terstruktur', 'Jurnal seimbang', 'Data per cabang'].map((item) => <span key={item} className="flex items-center gap-2"><span className="grid size-5 place-items-center rounded-full bg-emerald-100 text-emerald-700"><Check className="size-3" /></span>{item}</span>)}</div>
                </div>
                <DashboardPreview />
            </section>

            <section className="relative border-y border-slate-200/80 bg-white/80"><div className="mx-auto grid max-w-7xl grid-cols-2 divide-x divide-slate-200 px-6 lg:grid-cols-4 lg:px-8">{[['Satu sumber data', 'untuk transaksi & laporan'], ['Siap multi cabang', 'tanpa rekonsiliasi manual'], ['Jurnal atomik', 'untuk jejak audit'], ['Tampilan terang', 'nyaman dibaca seharian']].map(([title, text]) => <div key={title} className="px-4 py-7 first:pl-0 lg:px-8 lg:first:pl-0"><p className="font-semibold text-slate-900">{title}</p><p className="mt-1 text-sm text-slate-500">{text}</p></div>)}</div></section>

            <section className="mx-auto max-w-7xl px-6 py-24 lg:px-8"><div className="max-w-xl"><p className="text-sm font-semibold text-emerald-700">DIBUAT UNTUK OPERASIONAL HARIAN</p><h2 className="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Yang penting untuk bisnis Anda, tersaji tanpa kerumitan.</h2></div><div className="mt-12 grid gap-5 lg:grid-cols-3">{features.map(({ icon: Icon, title, description }) => <article key={title} className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-slate-200/50"><span className="grid size-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><Icon className="size-5" /></span><h3 className="mt-6 text-lg font-semibold">{title}</h3><p className="mt-2 leading-6 text-slate-600">{description}</p><span className="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700">Pelajari alurnya <ArrowRight className="size-4" /></span></article>)}</div></section>

            <section className="mx-auto max-w-7xl px-6 pb-24 lg:px-8"><div className="relative overflow-hidden rounded-3xl bg-slate-900 px-7 py-12 text-white sm:px-12"><div className="absolute -right-20 -top-20 size-72 rounded-full bg-emerald-500/25 blur-3xl" /><div className="relative max-w-2xl"><p className="text-sm font-semibold text-emerald-300">MULAI DARI DATA YANG RAPI</p><h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Keuangan yang lebih tenang dimulai dari satu pencatatan yang benar.</h2><p className="mt-4 leading-7 text-slate-300">Masuk untuk melanjutkan pencatatan usaha Anda.</p><Link href={primaryHref} className="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50">{auth.user ? 'Buka dashboard' : 'Masuk sekarang'} <ArrowRight className="size-4" /></Link></div></div></section>
        </main>
    </>;
}

function DashboardPreview() {
    return <div className="relative mx-auto w-full max-w-xl"><div className="absolute -inset-8 rounded-[3rem] bg-emerald-300/20 blur-3xl" /><div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-300/60 sm:p-5"><div className="flex items-center justify-between border-b border-slate-100 pb-4"><div className="flex items-center gap-2"><span className="size-2 rounded-full bg-rose-400" /><span className="size-2 rounded-full bg-amber-400" /><span className="size-2 rounded-full bg-emerald-400" /></div><span className="rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500">Oktober 2026</span></div><div className="mt-5 grid grid-cols-2 gap-3"><Metric label="Saldo kas" value="Rp 42,8 jt" accent="text-emerald-600" icon={CircleDollarSign} /><Metric label="Pendapatan" value="Rp 86,4 jt" accent="text-blue-600" icon={TrendingUp} /></div><div className="mt-4 rounded-xl border border-slate-100 p-4"><div className="flex items-center justify-between"><div><p className="text-sm font-semibold">Arus kas</p><p className="mt-1 text-xs text-slate-500">30 hari terakhir</p></div><PieChart className="size-5 text-slate-400" /></div><div className="mt-5 flex h-24 items-end gap-2">{[34, 51, 42, 72, 58, 84, 68, 94, 75, 100, 86, 92].map((height, index) => <div key={index} style={{ height: `${height}%` }} className={`flex-1 rounded-t-sm ${index > 8 ? 'bg-emerald-500' : 'bg-emerald-100'}`} />)}</div></div><div className="mt-4 flex items-center gap-3 rounded-xl bg-emerald-50 p-3"><span className="grid size-9 place-items-center rounded-lg bg-white text-emerald-600 shadow-sm"><BadgeCheck className="size-5" /></span><p className="text-xs leading-5 text-emerald-900"><strong className="font-semibold">Catatan rapi.</strong> Jurnal periode ini seimbang.</p></div></div></div>;
}

function Metric({ label, value, accent, icon: Icon }: { label: string; value: string; accent: string; icon: typeof CircleDollarSign }) { return <div className="rounded-xl border border-slate-100 p-4"><div className="flex items-center justify-between"><p className="text-xs text-slate-500">{label}</p><Icon className={`size-4 ${accent}`} /></div><p className="mt-3 text-lg font-semibold tracking-tight text-slate-900">{value}</p><p className="mt-1 text-xs text-emerald-600">+12,4% bulan ini</p></div>; }
