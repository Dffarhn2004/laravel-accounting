import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ReportShell, filterBar, selectClass } from '@/components/report-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Status = 'good' | 'near' | 'bad' | 'na';
type Line = { budget: string; actual: string; achievement: string | null; status: Status };
type Row = Line & { code: string; name: string; type: string };
type Props = {
    report: { rows: Row[]; kpi: Record<'income' | 'expense' | 'profit', Line & { label: string }> };
    filters: { from: string; to: string; branch_id: number | null };
    branches: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pct = (v: string | null) => (v === null ? 'N/A' : `${num(v)}%`);
const TONE: Record<Status, string> = {
    good: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    near: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    bad: 'bg-destructive/10 text-destructive',
    na: 'text-muted-foreground',
};
const LABEL: Record<Status, string> = { good: 'Sesuai target', near: 'Dekat target', bad: 'Jauh dari target', na: 'Target 0' };

function Badge({ status, children }: { status: Status; children: ReactNode }) {
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${TONE[status]}`} title={LABEL[status]}>{children}<span className="sr-only"> ({LABEL[status]})</span></span>;
}

export default function AngKinerja({ report, filters, branches }: Props) {
    const [form, setForm] = useState({ from: filters.from, to: filters.to, branch_id: filters.branch_id ? String(filters.branch_id) : '' });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));

    return (
        <ReportShell
            title="Laporan Kinerja Anggaran"
            period={`${filters.from} s/d ${filters.to}`}
            subtitle="Anggaran dibandingkan aktual dari jurnal pada bulan yang dipilih. Pendapatan dan laba hijau bila ≥ 100%; beban hijau bila ≤ 100%. Kuning bila selisih dalam 10 poin, merah bila lebih jauh. Target 0 ditampilkan N/A karena persentase tidak terdefinisi."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/anggaran/ang-kinerja', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={selectClass} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <section aria-label="Ringkasan kinerja" className="grid gap-3 sm:grid-cols-3">
                {(['income', 'expense', 'profit'] as const).map((k) => {
                    const kpi = report.kpi[k];
                    return (
                        <div key={k} className="rounded-lg border bg-card p-3">
                            <p className="text-xs text-muted-foreground">{kpi.label}</p>
                            <p className="mt-1 text-2xl font-semibold tabular-nums"><Badge status={kpi.status}>{pct(kpi.achievement)}</Badge></p>
                            <p className="mt-1 text-xs tabular-nums text-muted-foreground">Aktual {num(kpi.actual)} · Anggaran {num(kpi.budget)}</p>
                        </div>
                    );
                })}
            </section>
            <div className="overflow-x-auto rounded-xl border bg-card">
                <table className="w-full min-w-[720px] text-sm">
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground"><tr><th className="p-3">No Akun</th><th className="p-3">Nama Akun</th><th className="p-3 text-right">Anggaran</th><th className="p-3 text-right">Aktual</th><th className="p-3 text-right">Achievement</th></tr></thead>
                    <tbody>
                        {report.rows.length ? report.rows.map((r) => (
                            <tr key={r.code} className="border-b last:border-0">
                                <td className="p-3 tabular-nums">{r.code}</td><td className="p-3">{r.name}</td>
                                <td className="p-3 text-right tabular-nums">{num(r.budget)}</td><td className="p-3 text-right tabular-nums">{num(r.actual)}</td>
                                <td className="p-3 text-right"><Badge status={r.status}>{pct(r.achievement)}</Badge></td>
                            </tr>
                        )) : <tr><td colSpan={5} className="p-10 text-center text-muted-foreground">Belum ada anggaran atau transaksi pada periode ini.</td></tr>}
                    </tbody>
                </table>
            </div>
        </ReportShell>
    );
}
