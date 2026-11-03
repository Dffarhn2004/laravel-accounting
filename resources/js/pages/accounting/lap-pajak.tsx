import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Summary = {
    tax: string; tax_label: string; account: string; carry_forward: string; accrued: string; paid: string; remaining: string;
    outstanding: string; advance: string; credit_label: string | null; credit_balance: string | null; due_rule: string;
};
type Masa = {
    tax: string; tax_label: string; period: string; masa: string; accrued: string; paid: string; remaining: string; credit: string;
    status: string; status_label: string; overdue: boolean; due_estimate: string; url: string;
};
type LedgerRow = { date: string; reference: string | null; description: string; account: string; debit: string; credit: string; source: string; url: string | null };
type Expense = { rows: { code: string; name: string; months: Record<string, string>; total: string }[]; total: string };
type Props = {
    summary: Summary[];
    masa: Masa[];
    ledger: { rows: LedgerRow[]; truncated: boolean };
    expense: Expense;
    reconciliation: { tax: string; tax_label: string; report: string; ledger: string; difference: string }[];
    filters: { year: number; branch_id: number | null; tax: string; month: number | null; status: string };
    taxes: { key: string; label: string }[];
    branches: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const badge = (m: Masa) => (m.overdue ? 'bg-destructive/10 text-destructive' : m.status === 'paid' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : m.status === 'none' ? 'text-muted-foreground' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400');

export default function LapPajak({ summary, masa, ledger, expense, reconciliation, filters, taxes, branches }: Props) {
    const [form, setForm] = useState({
        year: String(filters.year),
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        tax: filters.tax,
        month: filters.month ? String(filters.month) : '',
        status: filters.status,
    });
    const query = () => Object.fromEntries(Object.entries(form).filter(([k, v]) => v !== '' && !(k === 'tax' && v === 'all') && !(k === 'status' && v === 'all')));
    const exportUrl = `/laporan/lap-pajak/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;
    const select = 'rounded-md border bg-background p-2 text-sm';

    return (
        <>
            <Head title="Laporan Pajak" />
            <main className="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Laporan Pajak</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Kewajiban dan setoran pajak per masa. Setoran dialokasikan ke masa terlama yang masih terbuka. Jatuh tempo hanya perkiraan dan perlu ditinjau terhadap peraturan yang berlaku.</p>
                    </div>
                    <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/laporan/lap-pajak', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Tahun<Input type="number" min={2000} max={2100} className="w-28" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={select} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis pajak
                        <select className={select} value={form.tax} onChange={(e) => setForm({ ...form, tax: e.target.value })}>
                            <option value="all">Semua</option>{taxes.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Masa
                        <select className={select} value={form.month} onChange={(e) => setForm({ ...form, month: e.target.value })}>
                            <option value="">Semua bulan</option>{MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Status
                        <select className={select} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                            <option value="all">Semua</option>
                            <option value="open">Belum lunas</option>
                            <option value="paid">Lunas</option>
                            <option value="overdue">Lewat perkiraan jatuh tempo</option>
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <section aria-labelledby="blok-a" className="space-y-2">
                    <h2 id="blok-a" className="text-lg font-semibold">A. Ringkasan per pajak</h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full min-w-[1000px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr><th className="p-3">Pajak</th><th className="p-3">Akun</th><th className="p-3 text-right">Sisa masa sebelumnya</th><th className="p-3 text-right">Terutang tahun ini</th><th className="p-3 text-right">Disetor</th><th className="p-3 text-right">Total belum disetor</th><th className="p-3 text-right">Kelebihan setoran</th><th className="p-3">Kredit pajak</th></tr>
                            </thead>
                            <tbody>
                                {summary.map((s) => (
                                    <tr key={s.tax} className="border-b last:border-0">
                                        <td className="p-3 font-medium">{s.tax_label}</td>
                                        <td className="p-3">{s.account}</td>
                                        <td className="p-3 text-right tabular-nums">{num(s.carry_forward)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(s.accrued)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(s.paid)}</td>
                                        <td className="p-3 text-right font-semibold tabular-nums">{num(s.outstanding)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(s.advance)}</td>
                                        <td className="p-3">{s.credit_label && s.credit_balance !== null ? `${s.credit_label}: ${num(s.credit_balance)}` : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-xs text-muted-foreground">Kredit pajak (PPN Masukan, PPh 23 dibayar di muka) hanya informasi. Bila diperhitungkan dengan utang pajak, catat lewat jurnal yang mendebit akun utang pajak; jurnal itu dihitung sebagai setoran.</p>
                </section>

                <section aria-labelledby="blok-b" className="space-y-2">
                    <h2 id="blok-b" className="text-lg font-semibold">B. Status masa pajak</h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full min-w-[1000px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr><th className="p-3">Pajak</th><th className="p-3">Masa</th><th className="p-3 text-right">Terutang</th><th className="p-3 text-right">Disetor</th><th className="p-3 text-right">Sisa</th><th className="p-3">Status</th><th className="p-3">Perkiraan jatuh tempo</th><th className="p-3 text-right">Kredit pajak</th></tr>
                            </thead>
                            <tbody>
                                {masa.length ? masa.map((m) => (
                                    <tr key={`${m.tax}-${m.period}`} className="border-b last:border-0">
                                        <td className="p-3">{m.tax_label}</td>
                                        <td className="p-3"><Link href={m.url} className="underline">{m.masa}</Link></td>
                                        <td className="p-3 text-right tabular-nums">{num(m.accrued)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(m.paid)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(m.remaining)}</td>
                                        <td className="p-3"><span className={`rounded-full px-2 py-0.5 text-xs ${badge(m)}`}>{m.status_label}{m.overdue ? ' · lewat perkiraan' : ''}</span></td>
                                        <td className="p-3 tabular-nums text-muted-foreground">{m.due_estimate} <span className="text-xs">(perkiraan)</span></td>
                                        <td className="p-3 text-right tabular-nums">{num(m.credit)}</td>
                                    </tr>
                                )) : <tr><td colSpan={8} className="p-10 text-center text-muted-foreground">Tidak ada masa pajak yang cocok dengan filter.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section aria-labelledby="blok-c" className="space-y-2">
                    <h2 id="blok-c" className="text-lg font-semibold">C. Buku pembantu pajak</h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full min-w-[1000px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr><th className="p-3">Tanggal</th><th className="p-3">Referensi</th><th className="p-3">Keterangan</th><th className="p-3">Akun</th><th className="p-3 text-right">Debit</th><th className="p-3 text-right">Kredit</th><th className="p-3">Sumber</th></tr>
                            </thead>
                            <tbody>
                                {ledger.rows.length ? ledger.rows.map((r, i) => (
                                    <tr key={i} className="border-b last:border-0">
                                        <td className="p-3 tabular-nums">{r.date}</td>
                                        <td className="p-3">{r.reference ?? '—'}</td>
                                        <td className="p-3">{r.url ? <Link href={r.url} className="underline">{r.description}</Link> : r.description}</td>
                                        <td className="p-3">{r.account}</td>
                                        <td className="p-3 text-right tabular-nums">{num(r.debit)}</td>
                                        <td className="p-3 text-right tabular-nums">{num(r.credit)}</td>
                                        <td className="p-3">{r.source}</td>
                                    </tr>
                                )) : <tr><td colSpan={7} className="p-10 text-center text-muted-foreground">Belum ada mutasi akun pajak pada periode ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {ledger.truncated && <p className="text-xs text-muted-foreground">Hanya 500 baris pertama yang ditampilkan. Persempit dengan filter masa atau cabang.</p>}
                </section>

                <section aria-labelledby="blok-d" className="space-y-2">
                    <h2 id="blok-d" className="text-lg font-semibold">D. Beban pajak di Laba Rugi</h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full min-w-[1200px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr><th className="p-3">Akun</th>{MONTHS.map((m) => <th key={m} className="p-3 text-right">{m}</th>)}<th className="p-3 text-right">Total</th></tr>
                            </thead>
                            <tbody>
                                {expense.rows.length ? expense.rows.map((r) => (
                                    <tr key={r.code} className="border-b last:border-0">
                                        <td className="p-3">{r.code} — {r.name}</td>
                                        {MONTHS.map((_, i) => <td key={i} className="p-3 text-right tabular-nums">{num(r.months[i + 1])}</td>)}
                                        <td className="p-3 text-right font-semibold tabular-nums">{num(r.total)}</td>
                                    </tr>
                                )) : <tr><td colSpan={14} className="p-10 text-center text-muted-foreground">Belum ada beban pajak pada periode ini.</td></tr>}
                            </tbody>
                            {expense.rows.length > 0 && <tfoot><tr className="border-t font-semibold"><td className="p-3" colSpan={13}>Total beban pajak</td><td className="p-3 text-right tabular-nums">{num(expense.total)}</td></tr></tfoot>}
                        </table>
                    </div>
                </section>

                <section aria-label="Rekonsiliasi" className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    {reconciliation.map((r) => (
                        <div key={r.tax} className="rounded-lg border bg-card p-3">
                            <p className="text-xs text-muted-foreground">{r.tax_label}: laporan vs buku besar</p>
                            <p className="mt-1 font-semibold tabular-nums">{num(r.report)} / {num(r.ledger)}</p>
                            <p className={`text-xs ${Number(r.difference) === 0 ? 'text-muted-foreground' : 'text-destructive'}`}>Selisih {num(r.difference)}</p>
                        </div>
                    ))}
                </section>
            </main>
        </>
    );
}
