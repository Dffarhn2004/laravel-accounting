import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, MoreHorizontal, Paperclip, Plus } from 'lucide-react';
import { useState } from 'react';
import { EvidenceDialog, ReverseDialog } from '@/components/register-dialogs';
import type { EvidenceFile } from '@/components/register-dialogs';
import { RegisterTable } from '@/components/register-table';
import type { RegisterColumn } from '@/components/register-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type Row = {
    asset_id: number;
    reversed: boolean;
    can_reverse_depreciation: boolean;
    can_reverse_purchase: boolean;
    reference: string;
    attachments: EvidenceFile[];
    no: number;
    month: string;
    date: string;
    branch: string | null;
    payment: string | null;
    account_code: string | null;
    account_name: string | null;
    description: string | null;
    total: string;
    counter_code: string | null;
    counter_name: string | null;
    counter_amount: string;
    life_years: string;
    monthly: string;
    end_year: string;
};
type Props = {
    rows: Row[];
    pagination: { current: number; last: number; total: number; prev: string | null; next: string | null };
    totals: { amount: string; count: number };
    filters: { search: string | null; branch_id: number | null };
    columns: string[];
    branches: { id: number; code: string; name: string }[];
    maxAttachments: number;
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function DataAsset({ rows, pagination, totals, filters, columns, branches, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [form, setForm] = useState({ search: filters.search ?? '', branch_id: filters.branch_id ? String(filters.branch_id) : '' });
    const [reversing, setReversing] = useState<{ row: Row; kind: 'purchase' | 'depreciation' } | null>(null);
    const [evidence, setEvidence] = useState<Row | null>(null);
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/data/d-asset/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;

    const defs: Omit<RegisterColumn<Row>, 'header'>[] = [
        { cell: (r) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Aksi ${r.reference}`}><MoreHorizontal /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem asChild><Link href="/transactions/fixed-assets">Koreksi: input pembelian baru</Link></DropdownMenuItem>
                    {r.can_reverse_depreciation && <DropdownMenuItem className="text-destructive" onSelect={() => setReversing({ row: r, kind: 'depreciation' })}>Balik penyusutan terakhir</DropdownMenuItem>}
                    {r.can_reverse_purchase && <DropdownMenuItem className="text-destructive" onSelect={() => setReversing({ row: r, kind: 'purchase' })}>Balik pembelian</DropdownMenuItem>}
                </DropdownMenuContent>
            </DropdownMenu>
        ) },
        { cell: (r) => <Button variant="ghost" size="sm" aria-label={`Bukti ${r.reference}`} onClick={() => setEvidence(r)}><Paperclip />{r.attachments.length}</Button> },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.no}</span> },
        { cell: (r) => r.month },
        { cell: (r) => <span className="tabular-nums">{r.date}</span> },
        { cell: (r) => r.branch },
        { cell: (r) => <span className="inline-flex items-center gap-2">{r.payment}{r.reversed && <Badge variant="outline">Dibalik</Badge>}</span> },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.account_code}</span> },
        { cell: (r) => r.account_name },
        { cell: (r) => <span className="inline-block max-w-xs truncate align-middle" title={r.description ?? ''}>{r.description}</span> },
        { numeric: true, cell: (r) => num(r.total) },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.counter_code}</span> },
        { cell: (r) => r.counter_name },
        { numeric: true, cell: (r) => num(r.counter_amount) },
        { numeric: true, cell: (r) => r.life_years },
        { numeric: true, cell: (r) => num(r.monthly) },
        { cell: (r) => r.end_year },
    ];
    const cols: RegisterColumn<Row>[] = defs.map((d, i) => ({ ...d, header: columns[i] ?? '' }));

    return (
        <>
            <Head title="Data Asset" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Data Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Data Asset</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Pembelian dibalik hanya setelah penyusutan hidup dibalik dari bulan terakhir. Aset dari modal awal dibalik lewat Data Modal Awal. Jurnal asli tetap tersimpan.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                        <Button asChild><Link href="/transactions/fixed-assets"><Plus />Input Baru</Link></Button>
                    </div>
                </header>

                {errors.asset && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.asset}</p>}

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/data/d-asset', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cari<Input className="w-64" placeholder="Nomor atau keterangan…" value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <RegisterTable label="Tabel Data Asset" rows={rows} columns={cols} sticky={[56, 72, 56]} rowClass={(r) => (r.reversed ? 'text-muted-foreground line-through decoration-muted-foreground/40' : '')} empty={filters.search || filters.branch_id ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada aset. Mulai dengan Input Pembelian Asset.'} />

                <section aria-label="Total" className="grid gap-3 text-sm sm:grid-cols-2">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Aset aktif</p><p className="mt-1 font-semibold tabular-nums">{totals.count}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total harga aktif</p><p className="mt-1 font-semibold tabular-nums">{num(totals.amount)}</p></div>
                </section>

                <nav className="flex items-center justify-between text-sm text-muted-foreground" aria-label="Halaman">
                    <span>{pagination.total} aset · halaman {pagination.current} dari {pagination.last}</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!pagination.prev} asChild={Boolean(pagination.prev)}>{pagination.prev ? <Link href={pagination.prev} preserveState><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                        <Button variant="outline" size="sm" disabled={!pagination.next} asChild={Boolean(pagination.next)}>{pagination.next ? <Link href={pagination.next} preserveState>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                    </div>
                </nav>
            </main>

            <ReverseDialog title={reversing ? (reversing.kind === 'depreciation' ? `penyusutan ${reversing.row.reference}` : `pembelian ${reversing.row.reference}`) : null} postUrl={reversing ? `/data/d-asset/${reversing.row.asset_id}/${reversing.kind === 'depreciation' ? 'reverse-depreciation' : 'reverse'}` : null} onClose={() => setReversing(null)} />
            <EvidenceDialog title={evidence?.reference ?? null} files={evidence?.attachments ?? []} max={maxAttachments} uploadUrl={evidence ? `/data/d-asset/${evidence.asset_id}/attachments` : null} onClose={() => setEvidence(null)} />
        </>
    );
}
