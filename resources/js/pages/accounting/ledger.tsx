import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Summary = { account_id: number; code: string; name: string; type: string; active: boolean; opening: string; debit: string; credit: string; closing: string };
type Line = { line_id: number; entry_id: number; date: string; reference: string; source: string; description: string | null; counter: string; debit: string; credit: string; balance: string; source_url: string | null };
type Pagination = { current: number; last: number; total: number; prev: string | null; next: string | null };
type Props = {
    summary: Summary[];
    summaryTotals: { debit: string; credit: string };
    detail: {
        account: { id: number; code: string; name: string; type: string; normal: string };
        opening: string;
        rows: Line[];
        totals: { debit: string; credit: string; difference: string; lines: number };
        pagination: Pagination;
    } | null;
    filters: { account_id: number | null; branch_id: number | null; from: string | null; to: string | null };
    branches: { id: number; code: string; name: string }[];
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dash = (v: string) => (Number(v) === 0 ? '' : num(v));
const typeLabel: Record<string, string> = { asset: 'Aset', liability: 'Kewajiban', equity: 'Ekuitas', income: 'Pendapatan', expense: 'Beban' };
const select = 'rounded-md border bg-background p-2 text-sm';

export default function Ledger({ summary, summaryTotals, detail, filters, branches }: Props) {
    const [form, setForm] = useState({
        account_id: filters.account_id ? String(filters.account_id) : '',
        branch_id: filters.branch_id ? String(filters.branch_id) : '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [hideZero, setHideZero] = useState(true);
    const query = (override: Record<string, string> = {}) => Object.fromEntries(Object.entries({ ...form, ...override }).filter(([, v]) => v !== ''));
    const go = (override: Record<string, string> = {}) => router.get('/accounting/ledger', query(override), { preserveState: false, replace: true });
    const qs = (extra: Record<string, string>) => new URLSearchParams({ ...(query() as Record<string, string>), ...extra }).toString();
    const shown = summary.filter((s) => !hideZero || Number(s.opening) !== 0 || Number(s.debit) !== 0 || Number(s.credit) !== 0 || Number(s.closing) !== 0);

    return (
        <>
            <Head title="Laporan Buku Besar" />
            <main className="mx-auto w-full max-w-[1400px] space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Laporan</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Laporan Buku Besar</h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Detail mutasi satu akun dan ringkasan seluruh akun. Saldo ditampilkan pada sisi normal akun (aset dan beban di debit; kewajiban, ekuitas, dan pendapatan di kredit).</p>
                </header>

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); go(); }}>
                    <label className="grid gap-1 text-sm">Akun
                        <select className={`${select} min-w-64`} value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>
                            <option value="">— pilih akun —</option>
                            {summary.map((s) => <option key={s.account_id} value={s.account_id}>{s.code} — {s.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={select} value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Periode awal<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Periode akhir<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <Button type="submit">Tampilkan</Button>
                    <Button type="button" variant="ghost" onClick={() => router.get('/accounting/ledger', {}, { replace: true })}>Reset</Button>
                </form>

                <section aria-labelledby="bb-detail" className="rounded-xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                        <div>
                            <h2 id="bb-detail" className="font-semibold">Detail akun{detail ? `: ${detail.account.code} — ${detail.account.name}` : ''}</h2>
                            {detail && <p className="text-sm text-muted-foreground">{typeLabel[detail.account.type]} · saldo normal {detail.account.normal} · saldo awal {num(detail.opening)}</p>}
                        </div>
                        {detail && <Button variant="outline" size="sm" asChild><a href={`/accounting/ledger/export?${qs({ type: 'detail' })}`}><Download />Export detail</a></Button>}
                    </div>
                    {!detail ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">Pilih akun di atas, atau klik sebuah akun pada Ringkasan di bawah.</p>
                    ) : (
                        <>
                            <div className="overflow-x-auto" tabIndex={0} aria-label="Detail buku besar">
                                <table className="w-full min-w-[960px] text-sm">
                                    <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                                        <tr><th className="h-10 px-3">Tanggal</th><th className="px-3">No Trx</th><th className="px-3">Sumber</th><th className="px-3">Keterangan</th><th className="px-3">Akun Lawan</th><th className="px-3 text-right">Debit</th><th className="px-3 text-right">Kredit</th><th className="px-3 text-right">Saldo</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr className="border-t bg-muted/20"><td className="px-3 py-2" colSpan={7}>Saldo awal</td><td className="px-3 py-2 text-right tabular-nums">{num(detail.opening)}</td></tr>
                                        {detail.rows.length === 0 && <tr><td colSpan={8} className="h-24 text-center text-muted-foreground">Tidak ada mutasi pada periode ini.</td></tr>}
                                        {detail.rows.map((r) => (
                                            <tr key={r.line_id} className="border-t hover:bg-muted/30">
                                                <td className="whitespace-nowrap px-3 py-2 tabular-nums">{r.date}</td>
                                                <td className="whitespace-nowrap px-3 py-2 tabular-nums">{r.source_url ? <Link href={r.source_url} className="inline-flex items-center gap-1 underline">{r.reference}<ExternalLink className="size-3" aria-label="Buka dokumen sumber" /></Link> : r.reference}</td>
                                                <td className="whitespace-nowrap px-3 py-2"><Badge variant="outline">{r.source}</Badge></td>
                                                <td className="max-w-xs truncate px-3 py-2 text-muted-foreground" title={r.description ?? ''}>{r.description}</td>
                                                <td className="whitespace-nowrap px-3 py-2 tabular-nums text-muted-foreground">{r.counter}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{dash(r.debit)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{dash(r.credit)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{num(r.balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="border-t-2 text-sm font-semibold"><tr><td className="px-3 py-2" colSpan={5}>Total periode</td><td className="px-3 py-2 text-right tabular-nums">{num(detail.totals.debit)}</td><td className="px-3 py-2 text-right tabular-nums">{num(detail.totals.credit)}</td><td /></tr></tfoot>
                                </table>
                            </div>
                            <nav className="flex items-center justify-between border-t px-5 py-3 text-sm text-muted-foreground" aria-label="Halaman detail">
                                <span>{detail.pagination.total} mutasi · halaman {detail.pagination.current} dari {detail.pagination.last}</span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" disabled={!detail.pagination.prev} asChild={Boolean(detail.pagination.prev)}>{detail.pagination.prev ? <Link href={detail.pagination.prev}><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                                    <Button variant="outline" size="sm" disabled={!detail.pagination.next} asChild={Boolean(detail.pagination.next)}>{detail.pagination.next ? <Link href={detail.pagination.next}>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                                </div>
                            </nav>
                        </>
                    )}
                </section>

                <section aria-labelledby="bb-summary" className="rounded-xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                        <h2 id="bb-summary" className="font-semibold">Ringkasan seluruh akun</h2>
                        <div className="flex items-center gap-4">
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={hideZero} onChange={(e) => setHideZero(e.target.checked)} />Sembunyikan akun tanpa saldo</label>
                            <Button variant="outline" size="sm" asChild><a href={`/accounting/ledger/export?${qs({ type: 'summary' })}`}><Download />Export ringkasan</a></Button>
                        </div>
                    </div>
                    <div className="overflow-x-auto" tabIndex={0} aria-label="Ringkasan buku besar">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                                <tr><th className="h-10 px-3">Kode</th><th className="px-3">Nama</th><th className="px-3 text-right">Saldo Awal</th><th className="px-3 text-right">Debit</th><th className="px-3 text-right">Kredit</th><th className="px-3 text-right">Saldo Akhir</th></tr>
                            </thead>
                            <tbody>
                                {shown.length === 0 && <tr><td colSpan={6} className="h-24 text-center text-muted-foreground">Belum ada akun bersaldo. Jurnal terbentuk saat transaksi diposting.</td></tr>}
                                {shown.map((s) => (
                                    <tr key={s.account_id} className={`cursor-pointer border-t hover:bg-muted/30 ${detail?.account.id === s.account_id ? 'bg-muted/40' : ''}`} onClick={() => go({ account_id: String(s.account_id) })}>
                                        <td className="px-3 py-2 tabular-nums"><button type="button" className="underline" onClick={(e) => { e.stopPropagation(); go({ account_id: String(s.account_id) }); }}>{s.code}</button></td>
                                        <td className="px-3 py-2">{s.name} {!s.active && <Badge variant="outline">Nonaktif</Badge>}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{num(s.opening)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{num(s.debit)}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{num(s.credit)}</td>
                                        <td className="px-3 py-2 text-right font-medium tabular-nums">{num(s.closing)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 font-semibold"><tr><td className="px-3 py-2" colSpan={3}>Total mutasi {Number(summaryTotals.debit) === Number(summaryTotals.credit) ? <Badge className="ml-2">Seimbang</Badge> : <Badge variant="destructive" className="ml-2">Tidak seimbang</Badge>}</td><td className="px-3 py-2 text-right tabular-nums">{num(summaryTotals.debit)}</td><td className="px-3 py-2 text-right tabular-nums">{num(summaryTotals.credit)}</td><td /></tr></tfoot>
                        </table>
                    </div>
                </section>
            </main>
        </>
    );
}
