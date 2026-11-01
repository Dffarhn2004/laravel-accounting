import { router } from '@inertiajs/react';
import { useState } from 'react';
import { fmt, ReportMatrix } from '@/components/report-matrix';
import type { MatrixRow } from '@/components/report-matrix';
import { ReportShell, filterBar, selectClass } from '@/components/report-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    report: {
        columns: string[];
        rows: MatrixRow[];
        summary: { operating: string; investing: string; financing: string; increase: string; start_cash: string; end_cash: string; balance_sheet_cash: string; reconciled: boolean };
    };
    filters: { branch_id: number | null; from: string; to: string };
    branches: { id: number; code: string; name: string }[];
};

export default function CashFlow({ report, filters, branches }: Props) {
    const [form, setForm] = useState({ branch_id: filters.branch_id ? String(filters.branch_id) : '', from: filters.from, to: filters.to });
    const query = () => ({ from: form.from, to: form.to, ...(form.branch_id ? { branch_id: form.branch_id } : {}) });
    const s = report.summary;

    return (
        <ReportShell
            title="Laporan Cashflow"
            period={`${filters.from} s/d ${filters.to}`}
            subtitle="Metode tidak langsung: laba bersih disesuaikan dengan beban non-kas dan perubahan modal kerja, lalu aktivitas investasi dan pendanaan. Saldo awal yang diinput (Saldo Neraca / Modal Awal) dihitung sebagai posisi awal, bukan arus kas. Pembelian aset dengan utang tampil sebagai investasi dan kenaikan hutang."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/reports/cash-flow', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={selectClass} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((x) => <option key={x.id} value={x.id}>{x.code} — {x.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <section aria-label="Ringkasan" className="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                {[['Operasi', s.operating], ['Investasi', s.investing], ['Pendanaan', s.financing], ['Kenaikan kas', s.increase], ['Kas akhir', s.end_cash]].map(([label, value]) => (
                    <div key={label} className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 font-semibold tabular-nums">{fmt(value)}</p></div>
                ))}
                <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Kas akhir = Kas di Neraca</p><p className="mt-1">{s.reconciled ? <Badge>Cocok</Badge> : <Badge variant="destructive">Selisih</Badge>}</p></div>
            </section>
            <ReportMatrix title="Arus Kas" columns={report.columns} rows={report.rows} drill={{ from: filters.from, to: filters.to }} />
        </ReportShell>
    );
}
