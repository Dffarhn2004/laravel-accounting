import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ReportMatrix } from '@/components/report-matrix';
import type { MatrixRow } from '@/components/report-matrix';
import { ReportShell, filterBar } from '@/components/report-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    report: { columns: string[]; branch_ids: number[]; rows: MatrixRow[]; summary: Record<string, string> };
    filters: { from: string; to: string; all: boolean };
};

export default function BranchProfitLoss({ report, filters }: Props) {
    const [form, setForm] = useState({ from: filters.from, to: filters.to, all: filters.all });
    const query = () => ({ from: form.from, to: form.to, ...(form.all ? { all: 1 } : {}) });

    return (
        <ReportShell
            title="Laporan Laba Rugi per Cabang"
            period={`${filters.from} s/d ${filters.to}`}
            subtitle="Pendapatan dan beban setiap cabang berdampingan. Kolom TOTAL sama dengan Laba Rugi Konsolidasi untuk periode yang sama."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/reports/branch-profit-loss', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={form.all} onChange={(e) => setForm({ ...form, all: e.target.checked })} />Tampilkan akun tanpa mutasi</label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <ReportMatrix title="Laba Rugi per Cabang" columns={report.columns} rows={report.rows} showTotal drill={{ from: filters.from, to: filters.to }} />
        </ReportShell>
    );
}
