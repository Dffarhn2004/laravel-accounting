import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Summary = {
    branch: string;
    item: string;
    item_id: number;
    opening_qty: string;
    opening_value: string;
    in_qty: string;
    in_value: string;
    out_qty: string;
    out_value: string;
    end_qty: string;
    end_value: string;
    average: string;
    minimum: string;
    status: string;
};
type Card = {
    branch: string;
    date: string;
    description: string | null;
    kind: string;
    in_qty: string;
    out_qty: string;
    qty: string;
    in_value: string;
    out_value: string;
    value: string;
};
type Props = {
    rows: Summary[];
    card: Card[];
    reconciliation: { documents: string; ledger: string; difference: string };
    asOf: string;
    filters: { year: number; month: number; branch_id: number | null; item_id: number | null };
    branches: { id: number; code: string; name: string }[];
    items: { id: number; sku: string; name: string }[];
};

const num = (v: string, digits = 2) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export default function Inventory({ rows, card, reconciliation, asOf, filters, branches, items }: Props) {
    const [form, setForm] = useState({
        year: String(filters.year),
        month: String(filters.month),
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        item_id: filters.item_id ? String(filters.item_id) : '',
    });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));

    return (
        <>
            <Head title="Laporan Persediaan" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Laporan</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Laporan Persediaan</h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Posisi stok sampai {asOf}. Harga rata-rata dihitung per cabang. Pemakaian menjadi HPP.</p>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/accounting/inventory', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Bulan
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.month} onChange={(e) => setForm({ ...form, month: e.target.value })}>
                            {months.map((name, i) => <option key={name} value={i + 1}>{name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Tahun<Input className="w-28" inputMode="numeric" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Kartu stok
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.item_id} onChange={(e) => setForm({ ...form, item_id: e.target.value })}>
                            <option value="">Pilih barang…</option>{items.map((item) => <option key={item.id} value={item.id}>{item.sku} — {item.name}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[1200px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                {['Cabang', 'Jenis', 'Qty awal', 'Nilai awal', 'Qty masuk', 'Nilai masuk', 'Qty keluar', 'Nilai keluar', 'Qty akhir', 'Nilai akhir', 'Harga rata-rata', 'Minimum', 'Status'].map((c) => <th key={c} className="p-3">{c}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={`${row.branch}-${row.item_id}`} className="border-b last:border-0">
                                    <td className="p-3">{row.branch}</td>
                                    <td className="p-3">{row.item}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.opening_qty, 4)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.opening_value)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.in_qty, 4)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.in_value)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.out_qty, 4)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.out_value)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.end_qty, 4)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.end_value)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.average)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.minimum, 4)}</td>
                                    <td className="p-3">{row.status === 'Aman' ? <Badge variant="secondary">Aman</Badge> : <Badge variant="outline">{row.status}</Badge>}</td>
                                </tr>
                            )) : <tr><td colSpan={13} className="p-10 text-center text-muted-foreground">Belum ada jenis persediaan.</td></tr>}
                        </tbody>
                    </table>
                </div>

                {filters.item_id !== null && (
                    <section className="overflow-x-auto rounded-xl border bg-card">
                        <h2 className="border-b px-4 py-3 font-semibold">Kartu stok</h2>
                        <table className="w-full min-w-[1000px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>{['Cabang', 'Tanggal', 'Keterangan', 'Jenis', 'Qty masuk', 'Qty keluar', 'Saldo qty', 'Nilai masuk', 'Nilai keluar', 'Saldo nilai'].map((c) => <th key={c} className="p-3">{c}</th>)}</tr>
                            </thead>
                            <tbody>
                                {card.length ? card.map((row, i) => (
                                    <tr key={i} className="border-b last:border-0">
                                        <td className="p-3">{row.branch}</td>
                                        <td className="p-3 tabular-nums">{row.date}</td>
                                        <td className="p-3">{row.description}</td>
                                        <td className="p-3">{row.kind}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.in_qty, 4)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.out_qty, 4)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.qty, 4)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.in_value)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.out_value)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(row.value)}</td>
                                    </tr>
                                )) : <tr><td colSpan={10} className="p-8 text-center text-muted-foreground">Tidak ada gerakan pada bulan ini.</td></tr>}
                            </tbody>
                        </table>
                    </section>
                )}

                <section aria-label="Rekonsiliasi" className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Nilai stok</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.documents)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Saldo akun persediaan</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.ledger)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Selisih</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.difference)}</p></div>
                </section>
                <p className="text-xs text-muted-foreground">Selisih adalah saldo buku besar yang tidak menempel pada gerakan persediaan, misalnya saldo awal atau jurnal manual.</p>
            </main>
        </>
    );
}
