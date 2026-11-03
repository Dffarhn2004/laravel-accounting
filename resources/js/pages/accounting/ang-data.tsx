import { Head, Link, router } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    id: number; branch: string | null; code: string; name: string; activity: string; unit: string | null; rate: string;
    qty: Record<string, string>; amount: Record<string, string>; total_qty: string; total: string;
};
type Props = {
    rows: Row[];
    filters: { year: number; branch_id: number | null; search: string };
    branches: { id: number; code: string; name: string }[];
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function AngData({ rows, filters, branches }: Props) {
    const [form, setForm] = useState({ year: String(filters.year), branch_id: filters.branch_id ? String(filters.branch_id) : '', search: filters.search });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/anggaran/ang-data/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;
    const remove = (row: Row) => {
        if (window.confirm(`Hapus anggaran "${row.activity}" (${row.code})? Tindakan ini tidak mempengaruhi jurnal.`)) router.delete(`/accounting/budgets/${row.id}`);
    };

    return (
        <>
            <Head title="Data Anggaran" />
            <main className="mx-auto w-full max-w-[1800px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Anggaran</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Data Anggaran</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Register kegiatan anggaran: Qty dan Rp tiap bulan. Setiap baris adalah satu kombinasi cabang, akun, dan kegiatan.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                        <Button asChild><Link href="/accounting/budgets"><Plus />Input Anggaran</Link></Button>
                    </div>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/anggaran/ang-data', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tahun<Input type="number" min={2000} max={2100} className="w-28" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Cari<Input value={form.search} placeholder="Kegiatan, kode, atau nama akun" onChange={(e) => setForm({ ...form, search: e.target.value })} /></label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[2400px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="p-3">Aksi</th><th className="p-3">Cabang</th><th className="p-3">Kode Akun</th><th className="p-3">Nama Akun</th><th className="p-3">Rincian Kegiatan</th><th className="p-3">Satuan</th><th className="p-3 text-right">Tarif</th>
                                {MONTHS.map((m) => <th key={m} colSpan={2} className="p-3 text-center">{m}<span className="block text-[10px] font-normal">Qty · Rp</span></th>)}
                                <th className="p-3 text-right">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((r) => (
                                <tr key={r.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <span className="flex gap-1">
                                            <Button size="icon" variant="outline" aria-label={`Ubah ${r.activity}`} asChild><Link href={`/accounting/budgets?edit=${r.id}`}><Pencil /></Link></Button>
                                            <Button size="icon" variant="outline" aria-label={`Hapus ${r.activity}`} onClick={() => remove(r)}><Trash2 /></Button>
                                        </span>
                                    </td>
                                    <td className="p-3">{r.branch}</td><td className="p-3 tabular-nums">{r.code}</td><td className="p-3">{r.name}</td><td className="p-3">{r.activity}</td><td className="p-3">{r.unit ?? '—'}</td>
                                    <td className="p-3 text-right tabular-nums">{num(r.rate)}</td>
                                    {MONTHS.map((_, i) => (
                                        <Fragment key={i}>
                                            <td className="p-3 text-right tabular-nums text-muted-foreground">{num(r.qty[String(i + 1)])}</td>
                                            <td className="p-3 text-right tabular-nums">{num(r.amount[String(i + 1)])}</td>
                                        </Fragment>
                                    ))}
                                    <td className="p-3 text-right font-semibold tabular-nums">{num(r.total)}</td>
                                </tr>
                            )) : <tr><td colSpan={21} className="p-10 text-center text-muted-foreground">Belum ada anggaran yang cocok. Mulai dari Input Anggaran.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
