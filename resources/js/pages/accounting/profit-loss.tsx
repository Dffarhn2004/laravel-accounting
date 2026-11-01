import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ReportMatrix } from '@/components/report-matrix';
import type { MatrixRow } from '@/components/report-matrix';
import { ReportShell, filterBar, selectClass } from '@/components/report-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    report: { columns: string[]; rows: MatrixRow[]; summary: Record<string, string> };
    filters: { year: number; branch_id: number | null; all: boolean };
    branches: { id: number; code: string; name: string }[];
};

export default function ProfitLoss({ report, filters, branches }: Props) {
    const [form, setForm] = useState({ year: String(filters.year), branch_id: filters.branch_id ? String(filters.branch_id) : '', all: filters.all });
    const query = () => ({ year: form.year, ...(form.branch_id ? { branch_id: form.branch_id } : {}), ...(form.all ? { all: 1 } : {}) });
    const branch = branches.find((b) => String(b.id) === form.branch_id);

    return (
        <ReportShell
            title="Laporan Laba Rugi Konsolidasi"
            period={`Tahun ${filters.year} · ${branch ? branch.name : 'Semua cabang'}`}
            subtitle="Matriks Januari–Desember dengan Gross Margin, EBITDA, EBIT, EBT, dan Laba Bersih. % Rasio adalah porsi terhadap pendapatan operasional (N/A bila tidak ada pendapatan). Klik nama akun untuk menelusuri buku besarnya."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/reports/profit-loss', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tahun<Input type="number" min="2000" max="2100" className="w-28" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={selectClass} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={form.all} onChange={(e) => setForm({ ...form, all: e.target.checked })} />Tampilkan akun tanpa mutasi</label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <ReportMatrix
                title="Laba Rugi Konsolidasi"
                columns={report.columns}
                rows={report.rows}
                showTotal
                extra={{ key: 'ratio', label: '% Rasio' }}
                drill={{ from: `${filters.year}-01-01`, to: `${filters.year}-12-31`, ...(filters.branch_id ? { branch_id: String(filters.branch_id) } : {}) }}
            />
        </ReportShell>
    );
}
