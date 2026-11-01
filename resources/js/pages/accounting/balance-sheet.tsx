import { router } from '@inertiajs/react';
import { useState } from 'react';
import { fmt, ReportMatrix } from '@/components/report-matrix';
import type { MatrixRow } from '@/components/report-matrix';
import { ReportShell, filterBar, selectClass } from '@/components/report-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    report: { columns: string[]; rows: MatrixRow[]; balance: { aktiva: string[]; pasiva: string[]; difference: string[]; balanced: boolean } };
    filters: { branch_id: number | null; from: string; to: string; all: boolean };
    branches: { id: number; code: string; name: string }[];
};

export default function BalanceSheet({ report, filters, branches }: Props) {
    const [form, setForm] = useState({ branch_id: filters.branch_id ? String(filters.branch_id) : '', from: filters.from, to: filters.to, all: filters.all });
    const query = () => ({ from: form.from, to: form.to, ...(form.branch_id ? { branch_id: form.branch_id } : {}), ...(form.all ? { all: 1 } : {}) });
    const branch = branches.find((b) => String(b.id) === form.branch_id);
    const b = report.balance;

    return (
        <ReportShell
            title="Laporan Neraca"
            period={`Posisi ${filters.to} (dibanding awal ${filters.from}) · ${branch ? branch.name : 'Semua cabang'}`}
            subtitle="Saldo awal dan posisi akhir setiap akun, dikelompokkan per modul, dengan pertumbuhan. Laba yang belum ditutup ke ekuitas ditampilkan sebagai Laba Ditahan dan Laba Tahun Berjalan sehingga Aktiva selalu sama dengan Pasiva."
            filters={
                <form className={filterBar} role="search" onSubmit={(e) => { e.preventDefault(); router.get('/reports/balance-sheet', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={selectClass} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((x) => <option key={x.id} value={x.id}>{x.code} — {x.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={form.all} onChange={(e) => setForm({ ...form, all: e.target.checked })} />Tampilkan akun tanpa saldo</label>
                    <Button type="submit">Tampilkan</Button>
                </form>
            }
        >
            <section aria-label="Cek keseimbangan" className="grid gap-3 text-sm sm:grid-cols-4">
                <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total Aktiva (akhir)</p><p className="mt-1 font-semibold tabular-nums">{fmt(b.aktiva[1])}</p></div>
                <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total Pasiva (akhir)</p><p className="mt-1 font-semibold tabular-nums">{fmt(b.pasiva[1])}</p></div>
                <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Selisih (awal / akhir)</p><p className="mt-1 font-semibold tabular-nums">{fmt(b.difference[0])} / {fmt(b.difference[1])}</p></div>
                <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Status</p><p className="mt-1">{b.balanced ? <Badge>Seimbang</Badge> : <Badge variant="destructive">Tidak seimbang</Badge>}</p></div>
            </section>
            <ReportMatrix title="Neraca" columns={report.columns} rows={report.rows} extra={{ key: 'growth', label: 'Growth' }} drill={{ from: filters.from, to: filters.to, ...(filters.branch_id ? { branch_id: String(filters.branch_id) } : {}) }} />
        </ReportShell>
    );
}
