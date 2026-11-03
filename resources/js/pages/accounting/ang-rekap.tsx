import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ReportMatrix } from '@/components/report-matrix';
import type { MatrixRow } from '@/components/report-matrix';
import { ReportShell, filterBar, selectClass } from '@/components/report-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    report: { columns: string[]; rows: MatrixRow[]; summary: Record<string, string | null> };
    filters: { year: number; branch_id: number | null; all: boolean };
    branches: { id: number; code: string; name: string }[];
};

const rp = (v: string | null | undefined) => (v === null || v === undefined ? 'N/A' : `Rp ${Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);

export default function AngRekap({ report, filters, branches }: Props) {
    const [form, setForm] = useState({ year: String(filters.year), branch_id: filters.branch_id ? String(filters.branch_id) : '', all: filters.all });
    const query = () => ({ year: form.year, ...(form.branch_id ? { branch_id: form.branch_id } : {}), ...(form.all ? { all: 1 } : {}) });
    const branch = branches.find((b) => String(b.id) === form.branch_id);
    const kpis: [string, string][] = [
        ['Pendapatan', rp(report.summary.revenue)],
        ['Beban', rp(report.summary.expense)],
        ['Target laba', rp(report.summary.net_income)],
        ['Net margin', report.summary.net_margin === null ? 'N/A' : `${Number(report.summary.net_margin).toLocaleString('id-ID', { minimumFractionDigits: 2 })}%`],
    ];

    return (
        <ReportShell
            title="Rekap Anggaran"
            period={`Tahun ${filters.year} · ${branch ? branch.name : 'Semua cabang'}`}
            subtitle="Anggaran disusun dengan struktur yang sama seperti Laba Rugi aktual: Gross Margin, EBITDA, EBIT, EBT, dan Laba Bersih. % Rasio adalah porsi terhadap pendapatan anggaran (N/A bila tidak ada)."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/anggaran/ang-rekap', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tahun<Input type="number" min="2000" max="2100" className="w-28" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={selectClass} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang (konsolidasi)</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={form.all} onChange={(e) => setForm({ ...form, all: e.target.checked })} />Tampilkan akun tanpa anggaran</label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <section aria-label="Ringkasan" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {kpis.map(([label, value]) => (
                    <div key={label} className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 text-lg font-semibold tabular-nums">{value}</p></div>
                ))}
            </section>
            <ReportMatrix title="Rekap Anggaran" columns={report.columns} rows={report.rows} showTotal extra={{ key: 'ratio', label: '% Rasio' }} />
        </ReportShell>
    );
}
