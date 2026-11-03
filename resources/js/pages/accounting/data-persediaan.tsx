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
    movement_id: number;
    reversed: boolean;
    can_reverse: boolean;
    reference: string;
    attachments: EvidenceFile[];
    no: number;
    month: string;
    date: string;
    branch: string | null;
    kind: string | null;
    item: string | null;
    account_code: string | null;
    account_name: string | null;
    description: string | null;
    quantity: string;
    unit_cost: string;
    freight: string;
    tax: string;
    total: string;
    counter_code: string | null;
    counter_name: string | null;
    counter_amount: string;
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

const num = (v: string, digits = 2) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });

export default function DataPersediaan({ rows, pagination, totals, filters, columns, branches, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [form, setForm] = useState({ search: filters.search ?? '', branch_id: filters.branch_id ? String(filters.branch_id) : '' });
    const [reversing, setReversing] = useState<Row | null>(null);
    const [evidence, setEvidence] = useState<Row | null>(null);
    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const exportUrl = `/data/d-persediaan/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;

    const defs: Omit<RegisterColumn<Row>, 'header'>[] = [
        { cell: (r) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Aksi ${r.reference}`}><MoreHorizontal /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem asChild><Link href="/inputan/persediaan">Koreksi: input persediaan baru</Link></DropdownMenuItem>
                    {r.can_reverse && <DropdownMenuItem className="text-destructive" onSelect={() => setReversing(r)}>Balik gerakan terakhir</DropdownMenuItem>}
                </DropdownMenuContent>
            </DropdownMenu>
        ) },
        { cell: (r) => <Button variant="ghost" size="sm" aria-label={`Bukti ${r.reference}`} onClick={() => setEvidence(r)}><Paperclip />{r.attachments.length}</Button> },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.no}</span> },
        { cell: (r) => r.month },
        { cell: (r) => <span className="tabular-nums">{r.date}</span> },
        { cell: (r) => r.branch },
        { cell: (r) => <span className="inline-flex items-center gap-2">{r.kind}{r.reversed && <Badge variant="outline">Dibalik</Badge>}</span> },
        { cell: (r) => r.item },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.account_code}</span> },
        { cell: (r) => r.account_name },
        { cell: (r) => <span className="inline-block max-w-xs truncate align-middle" title={r.description ?? ''}>{r.description}</span> },
        { numeric: true, cell: (r) => num(r.quantity, 4) },
        { numeric: true, cell: (r) => num(r.unit_cost) },
        { numeric: true, cell: (r) => num(r.freight) },
        { numeric: true, cell: (r) => num(r.tax) },
        { numeric: true, cell: (r) => num(r.total) },
        { cell: (r) => <span className="tabular-nums text-muted-foreground">{r.counter_code}</span> },
        { cell: (r) => r.counter_name },
        { numeric: true, cell: (r) => num(r.counter_amount) },
    ];
    const cols: RegisterColumn<Row>[] = defs.map((d, i) => ({ ...d, header: columns[i] ?? '' }));

    return (
        <>
            <Head title="Data Persediaan" />
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Data Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Data Persediaan</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Arus pembelian dan pemakaian. Hanya gerakan terakhir pada jenis dan cabang yang sama yang dapat dibalik. Jurnal asli tetap tersimpan.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                        <Button asChild><Link href="/inputan/persediaan"><Plus />Input Baru</Link></Button>
                    </div>
                </header>

                {errors.inventory && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.inventory}</p>}

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" role="search" onSubmit={(e) => { e.preventDefault(); router.get('/data/d-persediaan', query(), { replace: true }); }}>
                    <label className="grid gap-1 text-sm">Cari<Input className="w-64" placeholder="Nomor, keterangan, jenis…" value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <Button type="submit">Terapkan</Button>
                </form>

                <RegisterTable label="Tabel Data Persediaan" rows={rows} columns={cols} sticky={[56, 72, 56]} rowClass={(r) => (r.reversed ? 'text-muted-foreground line-through decoration-muted-foreground/40' : '')} empty={filters.search || filters.branch_id ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada persediaan. Mulai dengan Input Persediaan.'} />

                <section aria-label="Total" className="grid gap-3 text-sm sm:grid-cols-2">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Gerakan aktif</p><p className="mt-1 font-semibold tabular-nums">{totals.count}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total harga aktif</p><p className="mt-1 font-semibold tabular-nums">{num(totals.amount)}</p></div>
                </section>

                <nav className="flex items-center justify-between text-sm text-muted-foreground" aria-label="Halaman">
                    <span>{pagination.total} gerakan · halaman {pagination.current} dari {pagination.last}</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!pagination.prev} asChild={Boolean(pagination.prev)}>{pagination.prev ? <Link href={pagination.prev} preserveState><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                        <Button variant="outline" size="sm" disabled={!pagination.next} asChild={Boolean(pagination.next)}>{pagination.next ? <Link href={pagination.next} preserveState>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                    </div>
                </nav>
            </main>

            <ReverseDialog title={reversing ? `persediaan ${reversing.reference}` : null} postUrl={reversing ? `/data/d-persediaan/${reversing.movement_id}/reverse` : null} onClose={() => setReversing(null)} />
            <EvidenceDialog title={evidence?.reference ?? null} files={evidence?.attachments ?? []} max={maxAttachments} uploadUrl={evidence ? `/data/d-persediaan/${evidence.movement_id}/attachments` : null} onClose={() => setEvidence(null)} />
        </>
    );
}
