import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    line_id: number;
    entry_id: number;
    source: string;
    source_kind: string | null;
    reference: string;
    branch: string | null;
    year: number;
    month: string;
    date: string;
    account_code: string;
    account_name: string;
    description: string | null;
    debit: string;
    credit: string;
    balance: string;
    source_url: string | null;
};
type Props = {
    rows: Row[];
    pagination: { current: number; last: number; total: number; prev: string | null; next: string | null };
    totals: { debit: string; credit: string; difference: string; lines: number };
    filters: { branch_id: number | null; source: string | null; year: number | null; month: number | null; search: string | null };
    branches: { id: number; code: string; name: string }[];
    sources: { key: string; label: string }[];
    years: number[];
    months: string[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dash = (v: string) => (Number(v) === 0 ? '' : num(v));
const HEAD = ['Sumber', 'No Trx', 'Cabang', 'Tahun', 'Bulan', 'Tanggal', 'No Akun', 'Nama Akun', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
const RIGHT = new Set(['Debit', 'Kredit', 'Saldo']);

export default function Journal({ rows, pagination, totals, filters, branches, sources, years, months }: Props) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        source: filters.source ?? '',
        year: filters.year ? String(filters.year) : '',
        month: filters.month ? String(filters.month) : '',
    });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/accounting/journals/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;
    const balanced = Number(totals.difference) === 0;
    const select = 'rounded-md border bg-background p-2 text-sm';

    return (
        <>
            <Head title="Jurnal Umum" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Jurnal Umum</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Seluruh posting dari invoice, beban, persediaan, pelunasan, aset, penyusutan, kas/bank, jurnal manual, dan saldo awal. Saldo adalah saldo berjalan akun pada sisi normalnya (dihitung dari awal pembukuan, tidak terpengaruh filter bulan). Halaman ini hanya baca; koreksi dilakukan dengan pembalikan di dokumen sumber.
                        </p>
                    </div>
                    <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/accounting/journals', query(), { preserveState: true, replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cari
                        <Input className="w-56" placeholder="No Trx, akun, keterangan…" value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} />
                    </label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={select} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Sumber
                        <select className={select} value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })}>
                            <option value="">Semua sumber</option>{sources.map((s) => <option key={s.key} value={s.key}>{s.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Tahun
                        <select className={select} value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })}>
                            <option value="">Semua</option>{years.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Bulan
                        <select className={select} value={form.month} onChange={(e) => setForm({ ...form, month: e.target.value })}>
                            <option value="">Semua</option>{months.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                    <Button type="button" variant="ghost" onClick={() => { setForm({ search: '', branch_id: '', source: '', year: '', month: '' }); router.get('/accounting/journals', {}, { replace: true }); }}>Reset</Button>
                </form>

                <section aria-label="Total" className="grid gap-3 text-sm sm:grid-cols-4">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total Debit</p><p className="mt-1 font-semibold tabular-nums">{num(totals.debit)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total Kredit</p><p className="mt-1 font-semibold tabular-nums">{num(totals.credit)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Selisih</p><p className="mt-1 font-semibold tabular-nums">{num(totals.difference)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Status ({totals.lines} baris)</p><p className="mt-1">{totals.lines === 0 ? <Badge variant="outline">Belum ada jurnal</Badge> : balanced ? <Badge>Seimbang</Badge> : <Badge variant="destructive">Tidak seimbang</Badge>}</p></div>
                </section>

                <div className="overflow-x-auto rounded-xl border bg-card" tabIndex={0} aria-label="Tabel Jurnal Umum">
                    <table className="w-full min-w-[1200px] text-sm">
                        <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                            <tr>{HEAD.map((h) => <th key={h} className={`h-11 whitespace-nowrap px-3 font-medium ${RIGHT.has(h) ? 'text-right' : ''}`}>{h}</th>)}</tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && <tr><td colSpan={HEAD.length} className="h-32 text-center text-muted-foreground">Belum ada jurnal yang cocok. Jurnal terbentuk otomatis saat transaksi diposting.</td></tr>}
                            {rows.map((r, i) => (
                                <tr key={r.line_id} className={`hover:bg-muted/30 ${i > 0 && rows[i - 1].entry_id !== r.entry_id ? 'border-t-2' : 'border-t'}`}>
                                    <td className="whitespace-nowrap px-3 py-2"><Badge variant="outline">{r.source}</Badge></td>
                                    <td className="whitespace-nowrap px-3 py-2 tabular-nums">
                                        {r.source_url ? <Link href={r.source_url} className="inline-flex items-center gap-1 underline">{r.reference}<ExternalLink className="size-3" aria-label="Buka dokumen sumber" /></Link> : r.reference}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2">{r.branch}</td>
                                    <td className="px-3 py-2 tabular-nums">{r.year}</td>
                                    <td className="whitespace-nowrap px-3 py-2">{r.month}</td>
                                    <td className="whitespace-nowrap px-3 py-2 tabular-nums">{r.date}</td>
                                    <td className="px-3 py-2 tabular-nums">{r.account_code}</td>
                                    <td className="whitespace-nowrap px-3 py-2">{r.account_name}</td>
                                    <td className="max-w-xs truncate px-3 py-2 text-muted-foreground" title={r.description ?? ''}>{r.description}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{dash(r.debit)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{dash(r.credit)}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{num(r.balance)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <nav className="flex items-center justify-between text-sm text-muted-foreground" aria-label="Halaman">
                    <span>{pagination.total} baris · halaman {pagination.current} dari {pagination.last}</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!pagination.prev} asChild={Boolean(pagination.prev)}>{pagination.prev ? <Link href={pagination.prev} preserveState><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                        <Button variant="outline" size="sm" disabled={!pagination.next} asChild={Boolean(pagination.next)}>{pagination.next ? <Link href={pagination.next} preserveState>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                    </div>
                </nav>
            </main>
        </>
    );
}
