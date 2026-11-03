import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    id: number;
    branch: string | null;
    customer: string | null;
    number: string;
    date: string;
    total: string;
    paid: string;
    remaining: string;
    status: string;
    days: number;
    bucket: string;
    last_payment: string | null;
    invoice_url: string;
    receipt_url: string | null;
};
type Props = {
    rows: Row[];
    buckets: Record<string, string>;
    reconciliation: { documents: string; ledger: string; difference: string };
    columns: string[];
    filters: { as_of: string; customer_id: number | null; branch_id: number | null; status: string };
    customers: { id: number; name: string }[];
    branches: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Aging({ rows, buckets, reconciliation, columns, filters, customers, branches }: Props) {
    const [form, setForm] = useState({
        as_of: filters.as_of,
        customer_id: filters.customer_id ? String(filters.customer_id) : '',
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        status: filters.status,
    });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/accounting/aging/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;
    const cells = [
        (r: Row) => r.branch,
        (r: Row) => r.customer,
        (r: Row) => <Link href={r.invoice_url} className="underline">{r.number}</Link>,
        (r: Row) => r.date,
        (r: Row) => num(r.total),
        (r: Row) => r.receipt_url ? <Link href={r.receipt_url} className="underline">{num(r.paid)}</Link> : num(r.paid),
        (r: Row) => num(r.remaining),
        (r: Row) => r.status,
        (r: Row) => `${r.days} (${r.bucket})`,
        (r: Row) => r.last_payment ?? '—',
    ];

    return (
        <>
            <Head title="Laporan Piutang" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Laporan Piutang</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Tagihan yang masih hidup pada tanggal posisi. Umur dihitung dari tanggal invoice: ≤30, 31–60, dan lebih dari 60 hari.</p>
                    </div>
                    <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/accounting/aging', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tanggal posisi<Input type="date" value={form.as_of} onChange={(e) => setForm({ ...form, as_of: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Customer
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })}>
                            <option value="">Semua customer</option>{customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Status
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                            <option value="open">Belum lunas</option>
                            <option value="paid">Lunas</option>
                            <option value="all">Semua</option>
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <section aria-label="Umur" className="grid gap-3 text-sm sm:grid-cols-3">
                    {Object.entries(buckets).map(([label, amount]) => (
                        <div key={label} className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Sisa {label} hari</p><p className="mt-1 font-semibold tabular-nums">{num(amount)}</p></div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[1100px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>{columns.map((c) => <th key={c} className="p-3">{c}</th>)}</tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.id} className="border-b last:border-0">
                                    {cells.map((cell, i) => <td key={columns[i]} className={`p-3 ${i >= 4 && i <= 6 ? 'text-right tabular-nums' : ''}`}>{cell(row)}</td>)}
                                </tr>
                            )) : <tr><td colSpan={columns.length} className="p-10 text-center text-muted-foreground">Tidak ada piutang pada tanggal posisi ini.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <section aria-label="Rekonsiliasi" className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Sisa invoice</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.documents)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Saldo akun piutang</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.ledger)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Selisih tanpa invoice</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.difference)}</p></div>
                </section>
                <p className="text-xs text-muted-foreground">Selisih adalah saldo buku besar yang tidak menempel pada invoice, misalnya saldo awal atau jurnal manual.</p>
            </main>
        </>
    );
}
