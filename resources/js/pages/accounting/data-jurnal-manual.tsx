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
    id: number;
    line_id: number;
    status: string;
    number: string | null;
    attachments: EvidenceFile[];
    no: number;
    month: string;
    date: string;
    branch: string | null;
    account: string | null;
    description: string | null;
    debit: string;
    credit: string;
    code: string | null;
    balance: string;
};
type Props = {
    rows: Row[];
    pagination: { current: number; last: number; total: number; prev: string | null; next: string | null };
    totals: { debit: string; credit: string; count: number };
    filters: { search: string | null; branch_id: number | null; from: string | null; to: string | null };
    columns: string[];
    branches: { id: number; code: string; name: string }[];
    maxAttachments: number;
};

const num = (v: string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function DataJurnalManual({ rows, pagination, totals, filters, columns, branches, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [form, setForm] = useState({ search: filters.search ?? '', branch_id: filters.branch_id ? String(filters.branch_id) : '', from: filters.from ?? '', to: filters.to ?? '' });
    const [reversing, setReversing] = useState<Row | null>(null);
    const [evidence, setEvidence] = useState<Row | null>(null);
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/data/d-jm/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;

    const defs: Omit<RegisterColumn<Row>, 'header'>[] = [
        { cell: (r) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Aksi ${r.number ?? r.no}`}><MoreHorizontal /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    {r.status !== 'reversed' && <DropdownMenuItem className="text-destructive" onSelect={() => setReversing(r)}>Balik seluruh jurnal</DropdownMenuItem>}
                </DropdownMenuContent>
            </DropdownMenu>
        ) },
        { cell: (r) => <Button variant="ghost" size="sm" aria-label={`Bukti ${r.number ?? r.no}`} onClick={() => setEvidence(r)}><Paperclip />{r.attachments.length}</Button> },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.no}</span> },
        { cell: (r) => r.month },
        { cell: (r) => <span className="tabular-nums">{r.date}</span> },
        { cell: (r) => r.branch },
        { cell: (r) => r.account },
        { cell: (r) => <span className="inline-flex items-center gap-2"><span className="inline-block max-w-xs truncate align-middle" title={r.description ?? ''}>{r.description}</span>{r.status === 'reversed' && <Badge variant="outline">Dibalik</Badge>}</span> },
        { numeric: true, cell: (r) => Number(r.debit) ? num(r.debit) : '' },
        { numeric: true, cell: (r) => Number(r.credit) ? num(r.credit) : '' },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.code}</span> },
        { cell: (r) => r.balance },
    ];
    const cols: RegisterColumn<Row>[] = defs.map((d, i) => ({ ...d, header: columns[i] ?? '' }));

    return (
        <>
            <Head title="Data Jurnal Manual" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Data Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Data Jurnal Manual</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Satu baris tabel adalah satu baris jurnal. Cek Balance menilai jurnal itu sendiri. Koreksi membalik seluruh jurnal, bukan satu baris.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                        <Button asChild><Link href="/accounting/manual-journal"><Plus />Input Baru</Link></Button>
                    </div>
                </header>

                {errors.manual && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.manual}</p>}

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/data/d-jm', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cari<Input className="w-64" placeholder="No, akun, keterangan…" value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Dari<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Sampai<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <Button type="submit">Terapkan</Button>
                    <Button type="button" variant="ghost" onClick={() => { setForm({ search: '', branch_id: '', from: '', to: '' }); router.get('/data/d-jm', {}, { replace: true }); }}>Reset</Button>
                </form>

                <RegisterTable
                    label="Tabel Data Jurnal Manual"
                    rows={rows}
                    columns={cols}
                    sticky={[56, 72, 56]}
                    rowClass={(r) => (r.status === 'reversed' ? 'text-muted-foreground line-through decoration-muted-foreground/40' : '')}
                    empty={filters.search || filters.from || filters.to || filters.branch_id ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada jurnal manual. Mulai dengan Input Jurnal Manual.'}
                />

                <section aria-label="Total" className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Jurnal aktif</p><p className="mt-1 font-semibold tabular-nums">{totals.count}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total debit</p><p className="mt-1 font-semibold tabular-nums">{num(totals.debit)}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total kredit</p><p className="mt-1 font-semibold tabular-nums">{num(totals.credit)}</p></div>
                </section>
                <p className="text-xs text-muted-foreground">Total memakai seluruh data sesuai filter dan mengabaikan jurnal yang dibalik.</p>

                <nav className="flex items-center justify-between text-sm text-muted-foreground" aria-label="Halaman">
                    <span>{pagination.total} jurnal · halaman {pagination.current} dari {pagination.last}</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!pagination.prev} asChild={Boolean(pagination.prev)}>{pagination.prev ? <Link href={pagination.prev} preserveState><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                        <Button variant="outline" size="sm" disabled={!pagination.next} asChild={Boolean(pagination.next)}>{pagination.next ? <Link href={pagination.next} preserveState>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                    </div>
                </nav>
            </main>

            <ReverseDialog title={reversing ? `jurnal ${reversing.number ?? ''}` : null} postUrl={reversing ? `/data/d-jm/${reversing.id}/reverse` : null} onClose={() => setReversing(null)} />
            <EvidenceDialog title={evidence?.number ?? null} files={evidence?.attachments ?? []} max={maxAttachments} uploadUrl={evidence ? `/data/d-jm/${evidence.id}/attachments` : null} onClose={() => setEvidence(null)} />
        </>
    );
}
