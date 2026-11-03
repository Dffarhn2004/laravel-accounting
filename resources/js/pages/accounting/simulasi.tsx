import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Cell = { cost: string; accumulated: string; book: string } | null;
type Board = {
    year: number;
    month: number;
    months: string[];
    assets: { id: number; code: string; name: string; account: string; cells: Cell[] }[];
    groups: { account: string; months: string[]; total: string }[];
    pending: { code: string; name: string; month: string; amount: string; expense: string | null; accumulated: string | null }[];
    reconciliation: {
        expense_posted: string;
        expense_ledger: string;
        expense_difference: string;
        accumulated_register: string;
        accumulated_ledger: string;
        accumulated_difference: string;
    };
};
type Props = { filters: { year: number; month: number }; board: Board };

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Simulasi({ filters, board }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const apply = (year: string, month: string) => router.get('/data/simulasi', { year, month }, { preserveState: true, replace: true });

    return (
        <>
            <Head title="Simulasi & Output D&A" />
            <main className="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Data Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Simulasi & Output D&A</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Garis lurus, mulai bulan setelah perolehan. Bulan sebelum perolehan dikosongkan. Aset modal menampilkan akumulasi cut-off, tanpa jurnal masa lalu.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/laporan/lap-asset">Laporan Daftar Asset</Link></Button>
                </header>

                {errors.asset && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.asset}</p>}

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onSubmit={(e) => { e.preventDefault(); const data = new FormData(e.currentTarget); apply(String(data.get('year')), String(data.get('month'))); }}>
                    <label className="grid gap-1 text-sm">Tahun<Input name="year" type="number" min={2000} max={2100} defaultValue={filters.year} className="w-28" /></label>
                    <label className="grid gap-1 text-sm">Bulan posisi
                        <select name="month" defaultValue={filters.month} className="rounded-md border bg-background p-2 text-sm">
                            {board.months.map((label, i) => <option key={label} value={i + 1}>{label}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Jadwal per aset</h2>
                    {board.assets.length === 0 && <p className="text-sm text-muted-foreground">Belum ada aset aktif.</p>}
                    {board.assets.map((asset) => (
                        <div key={asset.id} className="overflow-x-auto rounded-xl border">
                            <p className="border-b bg-muted/40 px-3 py-2 text-sm font-medium">{asset.code} — {asset.name} <span className="font-normal text-muted-foreground">{asset.account}</span></p>
                            <table className="w-full text-sm">
                                <thead><tr>{['', ...board.months].map((m) => <th key={m} className="px-2 py-1 text-right font-medium">{m}</th>)}</tr></thead>
                                <tbody>
                                    {(['cost', 'accumulated', 'book'] as const).map((key) => (
                                        <tr key={key} className="border-t">
                                            <th className="px-2 py-1 text-left font-normal text-muted-foreground">{key === 'cost' ? 'Harga' : key === 'accumulated' ? 'Akumulasi' : 'Nilai buku'}</th>
                                            {asset.cells.map((cell, i) => <td key={i} className="px-2 py-1 text-right tabular-nums">{cell ? num(cell[key]) : ''}</td>)}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}
                </section>

                <section className="space-y-2">
                    <h2 className="text-lg font-semibold">Rekap beban penyusutan</h2>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead><tr>{['Akun', ...board.months, 'Total'].map((m) => <th key={m} className="px-2 py-2 text-right font-medium first:text-left">{m}</th>)}</tr></thead>
                            <tbody>
                                {board.groups.map((group) => (
                                    <tr key={group.account} className="border-t">
                                        <td className="px-2 py-1">{group.account}</td>
                                        {group.months.map((amount, i) => <td key={i} className="px-2 py-1 text-right tabular-nums">{num(amount)}</td>)}
                                        <td className="px-2 py-1 text-right font-medium tabular-nums">{num(group.total)}</td>
                                    </tr>
                                ))}
                                {board.groups.length === 0 && <tr><td className="px-2 py-3 text-muted-foreground" colSpan={14}>Belum ada beban penyusutan terposting.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold">Jurnal yang belum terposting sampai {board.months[board.month - 1]} {board.year}</h2>
                        <Button disabled={board.pending.length === 0} onClick={() => { if (window.confirm('Posting penyusutan yang terlewat sampai bulan posisi, berurutan?')) router.post('/data/simulasi', { year: filters.year, month: filters.month }); }}>Posting</Button>
                    </div>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead><tr><th className="px-2 py-2 text-left">Aset</th><th className="px-2 py-2 text-left">Bulan</th><th className="px-2 py-2 text-left">Debit beban</th><th className="px-2 py-2 text-left">Kredit akumulasi</th><th className="px-2 py-2 text-right">Nilai</th></tr></thead>
                            <tbody>
                                {board.pending.map((row) => (
                                    <tr key={`${row.code}-${row.month}`} className="border-t">
                                        <td className="px-2 py-1">{row.code} — {row.name}</td>
                                        <td className="px-2 py-1 tabular-nums">{row.month}</td>
                                        <td className="px-2 py-1">{row.expense}</td>
                                        <td className="px-2 py-1">{row.accumulated}</td>
                                        <td className="px-2 py-1 text-right tabular-nums">{num(row.amount)}</td>
                                    </tr>
                                ))}
                                {board.pending.length === 0 && <tr><td className="px-2 py-3 text-muted-foreground" colSpan={5}>Semua bulan sampai posisi ini sudah terposting, atau tidak ada aset yang disusutkan.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section aria-label="Rekonsiliasi" className="grid gap-3 text-sm sm:grid-cols-2">
                    <div className="rounded-lg border bg-card p-3">
                        <p className="text-xs text-muted-foreground">Beban penyusutan vs buku besar</p>
                        <p className="mt-1 tabular-nums">Terposting {num(board.reconciliation.expense_posted)} · Buku besar {num(board.reconciliation.expense_ledger)}</p>
                        <p className="tabular-nums">Selisih {num(board.reconciliation.expense_difference)}</p>
                    </div>
                    <div className="rounded-lg border bg-card p-3">
                        <p className="text-xs text-muted-foreground">Akumulasi vs buku besar</p>
                        <p className="mt-1 tabular-nums">Register {num(board.reconciliation.accumulated_register)} · Buku besar {num(board.reconciliation.accumulated_ledger)}</p>
                        <p className="tabular-nums">Selisih {num(board.reconciliation.accumulated_difference)}</p>
                    </div>
                </section>
            </main>
        </>
    );
}
