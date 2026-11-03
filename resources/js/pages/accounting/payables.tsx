import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    source_id: number;
    branch: string | null;
    description: string;
    account: string | null;
    date: string | null;
    nilai: string;
    paid: string;
    remaining: string;
    status: string;
    age: string;
    last_payment: string | null;
    source: string;
    url: string | null;
};
type Props = {
    rows: Row[];
    reconciliation: { documents: string; undocumented: string; ledger: string };
    columns: string[];
    filters: { as_of: string; account_id: number | null; branch_id: number | null; status: string };
    accounts: { id: number; code: string; name: string }[];
    branches: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Payables({ rows, reconciliation, columns, filters, accounts, branches }: Props) {
    const [form, setForm] = useState({
        as_of: filters.as_of,
        account_id: filters.account_id ? String(filters.account_id) : '',
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        status: filters.status,
    });
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/laporan/lap-hutang/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;

    return (
        <>
            <Head title="Laporan Daftar Hutang" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Laporan Daftar Hutang</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Hutang dari beban, setoran modal, saldo neraca, dan pinjaman. Sisa yang ada di buku besar tetapi tidak punya dokumen ditampilkan sendiri.</p>
                    </div>
                    <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/laporan/lap-hutang', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tanggal posisi<Input type="date" value={form.as_of} onChange={(e) => setForm({ ...form, as_of: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Akun hutang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>
                            <option value="">Semua akun</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
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

                <div className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[1200px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>{columns.map((c) => <th key={c} className="p-3">{c}</th>)}</tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={`${row.source}-${row.source_id}-${index}`} className="border-b last:border-0">
                                    <td className="p-3">{row.branch}</td>
                                    <td className="p-3">{row.url ? <Link href={row.url} className="underline">{row.description}</Link> : row.description}</td>
                                    <td className="p-3">{row.account}</td>
                                    <td className="p-3 tabular-nums">{row.date ?? '—'}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.nilai)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.paid)}</td>
                                    <td className="p-3 text-right tabular-nums">{num(row.remaining)}</td>
                                    <td className="p-3">{row.status}</td>
                                    <td className="p-3">{row.age}</td>
                                    <td className="p-3 tabular-nums">{row.last_payment ?? '—'}</td>
                                    <td className="p-3">{row.source}</td>
                                </tr>
                            )) : <tr><td colSpan={columns.length} className="p-10 text-center text-muted-foreground">Tidak ada hutang pada tanggal posisi ini.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <section aria-label="Rekonsiliasi" className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Sisa dokumen</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.documents)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Tanpa dokumen</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.undocumented)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Saldo akun hutang</p><p className="mt-1 font-semibold tabular-nums">{num(reconciliation.ledger)}</p></div>
                </section>
                <p className="text-xs text-muted-foreground">Sisa dokumen ditambah saldo tanpa dokumen sama dengan saldo akun hutang di buku besar. Aset dan persediaan belum punya dokumen sendiri.</p>
            </main>
        </>
    );
}
