import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, CheckCircle2, CircleDashed, HandCoins, Landmark, ReceiptText, ShieldAlert, TrendingUp, Wallet, XCircle } from 'lucide-react';
import { useState } from 'react';
import { LineChart, ShareBars } from '@/components/charts';
import { fmt } from '@/components/report-matrix';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Pair = { label: string; amount: string };
type Control = { key: string; title: string; status: 'ok' | 'warn' | 'fail'; detail: string; href: string | null };
type Open = { id: number; number: string; party: string | null; date: string; amount: string };
type Dashboard = {
    kpis: { revenue: string; net_income: string; cash: string; total_assets: string; receivable: string; payable: string };
    trend: { year: number; months: string[]; revenue: string[]; expense: string[]; profit: string[]; cash: string[] };
    cash_flow: { operating: string; investing: string; financing: string; increase: string };
    expense_composition: Pair[];
    asset_composition: Pair[];
    branches: { branch_id: number; name: string; revenue: string; net_income: string }[];
    matrix: { label: string; values: string[] }[];
    top_receivables: Open[];
    top_payables: Open[];
    integrity: { status: 'valid' | 'attention' | 'invalid' | 'empty'; summary: string; controls: Control[] };
    unavailable: { key: string; title: string; step: string; href: string }[];
};
type Props = {
    dashboard: Dashboard | null;
    filters: { branch_id: number | null; from: string; to: string } | null;
    branches: { id: number; code: string; name: string }[];
};

const rp = (v: string) => `Rp ${Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const STATUS_BADGE: Record<Dashboard['integrity']['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    valid: { label: 'VALID', variant: 'default' },
    attention: { label: 'PERHATIAN', variant: 'secondary' },
    invalid: { label: 'TIDAK VALID', variant: 'destructive' },
    empty: { label: 'BELUM ADA DATA', variant: 'outline' },
};
const ICON = { ok: CheckCircle2, warn: AlertTriangle, fail: XCircle } as const;
const ICON_CLASS = { ok: 'text-emerald-600', warn: 'text-amber-600', fail: 'text-destructive' } as const;
const COLOR = { a: 'var(--chart-1)', b: 'var(--chart-2)', c: 'var(--chart-3)', d: 'var(--chart-4)' };

export default function DashboardPage({ dashboard, filters, branches }: Props) {
    const [form, setForm] = useState({ branch_id: filters?.branch_id ? String(filters.branch_id) : '', from: filters?.from ?? '', to: filters?.to ?? '' });

    if (!dashboard || !filters) {
        return (
            <>
                <Head title="Dashboard" />
                <main className="mx-auto max-w-3xl p-8">
                    <h1 className="text-3xl font-semibold tracking-tight">Dashboard</h1>
                    <p className="mt-3 rounded-lg border border-dashed p-6 text-sm text-muted-foreground">Akun Anda belum terhubung ke perusahaan, jadi belum ada data keuangan untuk ditampilkan.</p>
                </main>
            </>
        );
    }

    const d = dashboard;
    const scopeQs = filters.branch_id ? `&branch_id=${filters.branch_id}` : '';
    const year = d.trend.year;
    const cards = [
        { label: 'Pendapatan', value: d.kpis.revenue, note: 'Pendapatan operasional periode', icon: TrendingUp, href: `/reports/profit-loss?year=${year}${scopeQs}` },
        { label: 'Laba (Rugi) Bersih', value: d.kpis.net_income, note: 'Setelah seluruh beban dan pajak', icon: Landmark, href: `/reports/profit-loss?year=${year}${scopeQs}` },
        { label: 'Kas & Setara Kas', value: d.kpis.cash, note: 'Hanya akun kas dan bank', icon: Wallet, href: `/reports/cash-flow?from=${filters.from}&to=${filters.to}${scopeQs}` },
        { label: 'Total Aset', value: d.kpis.total_assets, note: 'Seluruh aktiva pada akhir periode', icon: Landmark, href: `/reports/balance-sheet?from=${filters.from}&to=${filters.to}${scopeQs}` },
        { label: 'Piutang Outstanding', value: d.kpis.receivable, note: 'Saldo akun piutang (buku besar)', icon: ReceiptText, href: '/accounting/aging' },
        { label: 'Hutang Outstanding', value: d.kpis.payable, note: 'Saldo akun hutang (buku besar)', icon: HandCoins, href: `/accounting/ledger?from=${filters.from}&to=${filters.to}${scopeQs}` },
    ];
    const status = STATUS_BADGE[d.integrity.status];
    const select = 'rounded-md border bg-background p-2 text-sm';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1500px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">Dashboard</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Semua angka dibaca dari buku besar dan mengikuti filter di bawah. Pendapatan dan laba untuk periode; kas, aset, piutang, dan hutang adalah saldo pada akhir periode.</p>
                    </div>
                    <div className="flex items-center gap-2" aria-live="polite"><span className="text-sm text-muted-foreground">Status integritas</span><Badge variant={status.variant}>{status.label}</Badge></div>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/dashboard', Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '')), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cabang / Brand
                        <select className={select} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <section aria-label="Indikator utama" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map((c) => (
                        <Link key={c.label} href={c.href} className="group rounded-xl border bg-card p-5 transition-colors hover:bg-muted/40">
                            <div className="flex items-center justify-between"><p className="text-sm text-muted-foreground">{c.label}</p><c.icon className="size-4 text-muted-foreground" /></div>
                            <p className={`mt-2 text-2xl font-semibold tabular-nums ${Number(c.value) < 0 ? 'text-destructive' : ''}`}>{rp(c.value)}</p>
                            <p className="mt-1 flex items-center justify-between text-xs text-muted-foreground">{c.note}<ArrowUpRight className="size-3 opacity-0 transition-opacity group-hover:opacity-100" /></p>
                        </Link>
                    ))}
                </section>

                <section aria-labelledby="integrity" className="rounded-xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4">
                        <h2 id="integrity" className="font-semibold">Kontrol integritas</h2>
                        <p className="flex items-center gap-2 text-sm"><ShieldAlert className="size-4" />{d.integrity.summary}</p>
                    </div>
                    <ul className="grid gap-px bg-border md:grid-cols-2">
                        {d.integrity.controls.map((c) => {
                            const Icon = ICON[c.status];
                            const body = (
                                <>
                                    <Icon className={`mt-0.5 size-5 shrink-0 ${ICON_CLASS[c.status]}`} aria-label={c.status === 'ok' ? 'Lolos' : c.status === 'warn' ? 'Perhatian' : 'Gagal'} />
                                    <span><span className="block text-sm font-medium">{c.title}</span><span className="block text-xs text-muted-foreground">{c.detail}</span></span>
                                </>
                            );
                            return <li key={c.key} className="bg-card">{c.href ? <Link href={c.href} className="flex gap-3 p-4 hover:bg-muted/40">{body}</Link> : <div className="flex gap-3 p-4">{body}</div>}</li>;
                        })}
                    </ul>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section aria-labelledby="trend" className="rounded-xl border bg-card p-5">
                        <h2 id="trend" className="font-semibold">Tren bulanan {year}</h2>
                        <p className="mb-2 text-xs text-muted-foreground">Pendapatan, beban, dan laba</p>
                        <LineChart title="Tren pendapatan, beban, dan laba" labels={d.trend.months} series={[
                            { label: 'Pendapatan', values: d.trend.revenue, color: COLOR.a }, { label: 'Beban', values: d.trend.expense, color: COLOR.b }, { label: 'Laba', values: d.trend.profit, color: COLOR.c },
                        ]} />
                    </section>
                    <section aria-labelledby="cash" className="rounded-xl border bg-card p-5">
                        <h2 id="cash" className="font-semibold">Tren kas {year}</h2>
                        <p className="mb-2 text-xs text-muted-foreground">Saldo kas dan bank akhir bulan</p>
                        <LineChart title="Tren saldo kas" labels={d.trend.months} series={[{ label: 'Kas & bank', values: d.trend.cash, color: COLOR.d }]} />
                    </section>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section aria-labelledby="flow" className="rounded-xl border bg-card p-5">
                        <h2 id="flow" className="font-semibold">Arus kas per aktivitas</h2>
                        <dl className="mt-3 grid grid-cols-[1fr_auto] gap-y-2 text-sm">
                            <dt>Operasi</dt><dd className="text-right tabular-nums">{fmt(d.cash_flow.operating)}</dd>
                            <dt>Investasi</dt><dd className="text-right tabular-nums">{fmt(d.cash_flow.investing)}</dd>
                            <dt>Pendanaan</dt><dd className="text-right tabular-nums">{fmt(d.cash_flow.financing)}</dd>
                            <dt className="border-t pt-2 font-semibold">Kenaikan kas</dt><dd className="border-t pt-2 text-right font-semibold tabular-nums">{fmt(d.cash_flow.increase)}</dd>
                        </dl>
                    </section>
                    <section aria-labelledby="exp" className="rounded-xl border bg-card p-5"><h2 id="exp" className="mb-3 font-semibold">Komposisi beban</h2><ShareBars items={d.expense_composition} color={COLOR.b} /></section>
                    <section aria-labelledby="ast" className="rounded-xl border bg-card p-5"><h2 id="ast" className="mb-3 font-semibold">Komposisi aset</h2><ShareBars items={d.asset_composition} color={COLOR.a} /></section>
                </div>

                <section aria-labelledby="br" className="rounded-xl border bg-card">
                    <div className="flex items-center justify-between border-b px-5 py-4"><h2 id="br" className="font-semibold">Kinerja tiap cabang</h2><Link className="text-sm underline" href={`/reports/branch-profit-loss?from=${filters.from}&to=${filters.to}`}>Laba Rugi per Cabang</Link></div>
                    <div className="overflow-x-auto"><table className="w-full min-w-[480px] text-sm">
                        <thead className="bg-muted/60 text-left text-xs text-muted-foreground"><tr><th className="h-10 px-4">Cabang</th><th className="px-4 text-right">Pendapatan</th><th className="px-4 text-right">Laba Bersih</th></tr></thead>
                        <tbody>{d.branches.length === 0 ? <tr><td colSpan={3} className="h-16 text-center text-muted-foreground">Belum ada cabang.</td></tr> : d.branches.map((b) => (
                            <tr key={b.branch_id} className="border-t"><td className="px-4 py-2">{b.name}</td><td className="px-4 py-2 text-right tabular-nums">{fmt(b.revenue)}</td><td className="px-4 py-2 text-right tabular-nums">{fmt(b.net_income)}</td></tr>))}</tbody>
                    </table></div>
                </section>

                <section aria-labelledby="mx" className="rounded-xl border bg-card">
                    <div className="flex items-center justify-between border-b px-5 py-4"><h2 id="mx" className="font-semibold">Laba rugi 12 bulan</h2><Link className="text-sm underline" href={`/reports/profit-loss?year=${year}${scopeQs}`}>Laporan lengkap</Link></div>
                    <div className="overflow-x-auto"><table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/60 text-xs text-muted-foreground"><tr><th className="sticky left-0 h-10 bg-muted px-4 text-left">Uraian</th>{d.trend.months.map((m) => <th key={m} className="px-3 text-right">{m.slice(0, 3)}</th>)}</tr></thead>
                        <tbody>{d.matrix.map((r) => <tr key={r.label} className="border-t"><th scope="row" className="sticky left-0 whitespace-nowrap bg-card px-4 py-2 text-left font-medium">{r.label}</th>{r.values.map((v, i) => <td key={i} className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{fmt(v)}</td>)}</tr>)}</tbody>
                    </table></div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    {([['Top 5 piutang terbuka', d.top_receivables, 'Pelanggan', (o: Open) => `/transactions/invoices/${o.id}`], ['Top 5 hutang terbuka', d.top_payables, 'Pemasok', (o: Open) => `/transactions/expenses/${o.id}`]] as const).map(([title, rows, party, href]) => (
                        <section key={title} className="rounded-xl border bg-card">
                            <div className="border-b px-5 py-4"><h2 className="font-semibold">{title}</h2></div>
                            {rows.length === 0 ? <p className="p-6 text-center text-sm text-muted-foreground">Tidak ada.</p> : (
                                <table className="w-full text-sm"><thead className="bg-muted/60 text-left text-xs text-muted-foreground"><tr><th className="h-10 px-4">No</th><th className="px-4">{party}</th><th className="px-4">Tanggal</th><th className="px-4 text-right">Sisa</th></tr></thead>
                                    <tbody>{rows.map((o) => <tr key={o.id} className="border-t"><td className="px-4 py-2 tabular-nums"><Link href={href(o)} className="underline">{o.number}</Link></td><td className="px-4 py-2">{o.party ?? '—'}</td><td className="px-4 py-2 tabular-nums">{o.date}</td><td className="px-4 py-2 text-right tabular-nums">{fmt(o.amount)}</td></tr>)}</tbody></table>
                            )}
                        </section>
                    ))}
                </div>

                <section aria-labelledby="soon">
                    <h2 id="soon" className="mb-3 font-semibold">Belum tersedia</h2>
                    <p className="mb-3 text-sm text-muted-foreground">Blok berikut menunggu halaman sumbernya selesai dibangun. Tidak ada angka sementara yang ditampilkan.</p>
                    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {d.unavailable.map((u) => (
                            <li key={u.key} className="rounded-xl border border-dashed p-4">
                                <p className="flex items-center gap-2 text-sm font-medium"><CircleDashed className="size-4 text-muted-foreground" />{u.title}</p>
                                <p className="mt-1 text-xs text-muted-foreground">Dari menu <Link href={u.href} className="underline">{u.step}</Link>.</p>
                            </li>
                        ))}
                    </ul>
                </section>
            </main>
        </>
    );
}
