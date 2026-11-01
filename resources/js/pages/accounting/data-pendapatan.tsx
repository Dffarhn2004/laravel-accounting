import { Head, Link, router, usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type { ColumnDef, VisibilityState } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight, Columns3, Download, FileText, MoreHorizontal, Paperclip, Plus } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type Attachment = { id: number; name: string; mime: string };
type Row = {
    invoice_id: number;
    status: string;
    attachments: Attachment[] | null;
    no: number;
    month: string;
    date: string;
    branch: string | null;
    invoice_number: string;
    customer: string | null;
    customer_code: string | null;
    payment_method: string | null;
    bank_cash: string | null;
    bank_cash_code: string | null;
    pph23: string | null;
    pph23_code: string | null;
    discount: string | null;
    discount_code: string | null;
    group: string | null;
    revenue: string | null;
    revenue_code: string | null;
    vat: string | null;
    vat_code: string | null;
    receivable: string | null;
    receivable_code: string | null;
    product: string | null;
    qty: string | null;
    price: string | null;
    pct: string | null;
};
type Filters = { search: string | null; branch_id: number | null; from: string | null; to: string | null };
type Props = {
    rows: Row[];
    pagination: { current: number; last: number; total: number; prev: string | null; next: string | null };
    totals: { bank_cash: string; discount: string; revenue: string; vat: string; receivable: string; invoices: number };
    filters: Filters;
    columns: string[];
    branches: { id: number; code: string; name: string }[];
    maxAttachments: number;
};

const num = (v: string | null) => (v === null || v === undefined ? '' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const code = (v: string | null) => (v ? <span className="text-xs text-muted-foreground tabular-nums">{v}</span> : null);

/** Sticky left columns: Aksi, Bukti, No. Widths are fixed so the offsets line up. */
const STICKY = { 0: { left: 0, width: 56 }, 1: { left: 56, width: 64 }, 2: { left: 120, width: 56 } } as Record<number, { left: number; width: number }>;

export default function DataPendapatan({ rows, pagination, totals, filters, columns, branches, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [form, setForm] = useState({ search: filters.search ?? '', branch_id: filters.branch_id ? String(filters.branch_id) : '', from: filters.from ?? '', to: filters.to ?? '' });
    const [visibility, setVisibility] = useState<VisibilityState>({});
    const [reversing, setReversing] = useState<Row | null>(null);
    const [evidence, setEvidence] = useState<Row | null>(null);
    const [reason, setReason] = useState('');
    const [postedOn, setPostedOn] = useState(new Date().toISOString().slice(0, 10));

    const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const apply = () => router.get('/transactions/invoices', query(), { preserveState: true, replace: true });
    const exportUrl = `/transactions/invoices/export?${new URLSearchParams(query() as Record<string, string>).toString()}`;

    const amount = (key: keyof Row, codeKey?: keyof Row): ColumnDef<Row>['cell'] => ({ row }) => (
        <div className="text-right tabular-nums">{num(row.original[key] as string | null)}</div>
    );

    // One definition per PRD column, in PRD order; headers come from the server so both stay in sync.
    const base: ColumnDef<Row>[] = [
        { id: 'aksi', cell: ({ row }) => row.original.attachments === null ? null : (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Aksi ${row.original.invoice_number}`}><MoreHorizontal /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem asChild><Link href={`/transactions/invoices/${row.original.invoice_id}`}>Lihat detail</Link></DropdownMenuItem>
                    <DropdownMenuItem asChild><Link href={`/transactions/invoices/${row.original.invoice_id}/print`}>Cetak</Link></DropdownMenuItem>
                    <DropdownMenuItem asChild><Link href="/transactions/invoices/create">Koreksi: buat invoice baru</Link></DropdownMenuItem>
                    {row.original.status !== 'reversed' && <DropdownMenuItem className="text-destructive" onSelect={() => { setReason(''); setReversing(row.original); }}>Balik (batalkan) invoice</DropdownMenuItem>}
                </DropdownMenuContent>
            </DropdownMenu>
        ) },
        { id: 'bukti', cell: ({ row }) => row.original.attachments === null ? null : (
            <Button variant="ghost" size="sm" aria-label={`Bukti ${row.original.invoice_number}`} onClick={() => setEvidence(row.original)}><Paperclip />{row.original.attachments.length}</Button>
        ) },
        { id: 'no', cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{row.original.no}</span> },
        { id: 'bulan', cell: ({ row }) => row.original.month },
        { id: 'tanggal', cell: ({ row }) => <span className="tabular-nums">{row.original.date}</span> },
        { id: 'cabang', cell: ({ row }) => row.original.branch },
        { id: 'inv', cell: ({ row }) => (
            <span className="inline-flex items-center gap-2 tabular-nums">{row.original.invoice_number}{row.original.status === 'reversed' && <Badge variant="outline">Dibalik</Badge>}</span>
        ) },
        { id: 'customer', cell: ({ row }) => row.original.customer },
        { id: 'customer_code', cell: ({ row }) => code(row.original.customer_code) },
        { id: 'payment', cell: ({ row }) => row.original.payment_method },
        { id: 'bank_cash', cell: amount('bank_cash') },
        { id: 'bank_cash_code', cell: ({ row }) => code(row.original.bank_cash_code) },
        { id: 'pph23', cell: ({ row }) => <div className="text-right tabular-nums text-muted-foreground" title="Estimasi; dibukukan saat pelanggan memotong pembayaran">{num(row.original.pph23)}</div> },
        { id: 'pph23_code', cell: ({ row }) => code(row.original.pph23_code) },
        { id: 'discount', cell: amount('discount') },
        { id: 'discount_code', cell: ({ row }) => code(row.original.discount_code) },
        { id: 'group', cell: ({ row }) => row.original.group },
        { id: 'revenue', cell: amount('revenue') },
        { id: 'revenue_code', cell: ({ row }) => code(row.original.revenue_code) },
        { id: 'vat', cell: amount('vat') },
        { id: 'vat_code', cell: ({ row }) => code(row.original.vat_code) },
        { id: 'receivable', cell: ({ row }) => <div className="text-right tabular-nums">{num(row.original.receivable)} {row.original.receivable_code && <span className="ml-1">{code(row.original.receivable_code)}</span>}</div> },
        { id: 'product', cell: ({ row }) => row.original.product },
        { id: 'qty', cell: ({ row }) => <div className="text-right tabular-nums">{row.original.qty === null ? '' : Number(row.original.qty)}</div> },
        { id: 'price', cell: amount('price') },
        { id: 'pct', cell: ({ row }) => <div className="text-right tabular-nums">{row.original.pct === null ? '' : Number(row.original.pct)}</div> },
    ];
    const defs: ColumnDef<Row>[] = base.map((d, i) => ({ ...d, header: columns[i] ?? d.id ?? '' }) as ColumnDef<Row>);

    const table = useReactTable({ data: rows, columns: defs, state: { columnVisibility: visibility }, onColumnVisibilityChange: setVisibility, getCoreRowModel: getCoreRowModel() });
    const stickyStyle = (index: number, header: boolean) => (STICKY[index] ? { position: 'sticky' as const, left: STICKY[index].left, minWidth: STICKY[index].width, zIndex: header ? 3 : 1 } : undefined);

    const submitReverse = () => {
        if (!reversing) return;
        router.post(`/transactions/invoices/${reversing.invoice_id}/reverse`, { reason, posted_on: postedOn }, { preserveScroll: true, onSuccess: () => setReversing(null) });
    };

    const upload = (files: FileList | null) => {
        if (!evidence || !files?.length) return;
        router.post(`/transactions/invoices/${evidence.invoice_id}/attachments`, { attachments: Array.from(files) }, { forceFormData: true, preserveScroll: true, onSuccess: () => setEvidence(null) });
    };

    const remaining = evidence ? maxAttachments - (evidence.attachments?.length ?? 0) : 0;

    return (
        <>
            <Head title="Data Pendapatan" />
            <main className="mx-auto w-full max-w-[1800px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Data Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Data Pendapatan</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Satu baris per produk atau pendapatan lain dari tiap invoice. Nilai tingkat invoice (Bank/Cash, PPh 23, PPN, Piutang) tampil di baris pertama saja agar penjumlahan kolom tidak ganda. Koreksi dilakukan dengan membalik invoice, bukan menghapus.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><a href={exportUrl}><Download />Export CSV</a></Button>
                        <Button asChild><Link href="/transactions/invoices/create"><Plus />Input Baru</Link></Button>
                    </div>
                </header>

                {errors.invoice && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.invoice}</p>}

                <form className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4" onSubmit={(e) => { e.preventDefault(); apply(); }} role="search">
                    <label className="grid gap-1 text-sm">Cari
                        <Input className="w-64" placeholder="No INV, customer, produk…" value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} />
                    </label>
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2 text-sm" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                            <option value="">Semua cabang</option>
                            {branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Dari<Input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} /></label>
                    <label className="grid gap-1 text-sm">Sampai<Input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} /></label>
                    <Button type="submit">Terapkan</Button>
                    <Button type="button" variant="ghost" onClick={() => { setForm({ search: '', branch_id: '', from: '', to: '' }); router.get('/transactions/invoices', {}, { replace: true }); }}>Reset</Button>
                    <details className="relative ml-auto">
                        <summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-md border px-3 py-2 text-sm"><Columns3 className="size-4" />Kolom</summary>
                        <div className="absolute right-0 z-10 mt-1 grid max-h-80 w-56 gap-1 overflow-y-auto rounded-md border bg-popover p-3 text-sm shadow-md">
                            {table.getAllLeafColumns().map((c, i) => (
                                <label key={c.id} className="flex items-center gap-2"><input type="checkbox" checked={c.getIsVisible()} disabled={i < 3} onChange={c.getToggleVisibilityHandler()} />{columns[i] ?? c.id} <span className="text-xs text-muted-foreground">{i + 1}</span></label>
                            ))}
                        </div>
                    </details>
                </form>

                <div className="overflow-x-auto rounded-xl border bg-card" tabIndex={0} aria-label="Tabel Data Pendapatan">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                            {table.getHeaderGroups().map((hg) => (
                                <tr key={hg.id}>
                                    {hg.headers.map((h, i) => (
                                        <th key={h.id} style={stickyStyle(i, true)} className={`h-11 whitespace-nowrap px-3 font-medium ${STICKY[i] ? 'bg-muted' : ''}`}>{flexRender(h.column.columnDef.header, h.getContext())}</th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {table.getRowModel().rows.length ? table.getRowModel().rows.map((r) => (
                                <tr key={r.id} className={`border-t hover:bg-muted/30 ${r.original.status === 'reversed' ? 'text-muted-foreground line-through decoration-muted-foreground/40' : ''}`}>
                                    {r.getVisibleCells().map((c, i) => (
                                        <td key={c.id} style={stickyStyle(i, false)} className={`whitespace-nowrap px-3 py-2 align-middle ${STICKY[i] ? 'bg-card' : ''}`}>{flexRender(c.column.columnDef.cell, c.getContext())}</td>
                                    ))}
                                </tr>
                            )) : (
                                <tr><td colSpan={defs.length} className="h-32 text-center text-muted-foreground">{filters.search || filters.from || filters.to || filters.branch_id ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada pendapatan. Mulai dengan Input Invoice.'}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <section aria-label="Total" className="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        ['Invoice aktif', String(totals.invoices)],
                        ['Total Pendapatan', num(totals.revenue)],
                        ['Beban Discount', num(totals.discount)],
                        ['PPN', num(totals.vat)],
                        ['Bank/Cash', num(totals.bank_cash)],
                        ['Piutang', num(totals.receivable)],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 font-semibold tabular-nums">{value}</p></div>
                    ))}
                </section>
                <p className="text-xs text-muted-foreground">Total memakai seluruh data sesuai filter, tidak termasuk invoice yang dibalik.</p>

                <nav className="flex items-center justify-between text-sm text-muted-foreground" aria-label="Halaman">
                    <span>{pagination.total} invoice · halaman {pagination.current} dari {pagination.last}</span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" disabled={!pagination.prev} asChild={Boolean(pagination.prev)}>{pagination.prev ? <Link href={pagination.prev} preserveState><ChevronLeft />Sebelumnya</Link> : <span><ChevronLeft />Sebelumnya</span>}</Button>
                        <Button variant="outline" size="sm" disabled={!pagination.next} asChild={Boolean(pagination.next)}>{pagination.next ? <Link href={pagination.next} preserveState>Berikutnya<ChevronRight /></Link> : <span>Berikutnya<ChevronRight /></span>}</Button>
                    </div>
                </nav>
            </main>

            <Dialog open={reversing !== null} onOpenChange={(open) => !open && setReversing(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Balik invoice {reversing?.invoice_number}</DialogTitle>
                        <DialogDescription>Jurnal asli tetap tersimpan. Dibuat jurnal pembalikan pada tanggal di bawah, dan invoice ditandai Dibalik. Pembayaran yang diterima saat penerbitan ikut dibalik.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <label className="grid gap-1 text-sm">Alasan (wajib)
                            <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Mis. salah customer / harga keliru" />
                            <span className="text-xs text-destructive">{errors.reason}</span>
                        </label>
                        <label className="grid gap-1 text-sm">Tanggal pembalikan<Input type="date" value={postedOn} onChange={(e) => setPostedOn(e.target.value)} /></label>
                    </div>
                    <DialogFooter><Button variant="destructive" disabled={reason.trim().length < 3} onClick={submitReverse}>Balik invoice</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={evidence !== null} onOpenChange={(open) => !open && setEvidence(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Bukti {evidence?.invoice_number}</DialogTitle>
                        <DialogDescription>Maksimal {maxAttachments} file JPG, PNG, WebP, atau PDF (5 MB per file).</DialogDescription>
                    </DialogHeader>
                    {evidence?.attachments?.length ? (
                        <ul className="divide-y rounded-md border text-sm">
                            {evidence.attachments.map((a) => (
                                <li key={a.id} className="flex items-center justify-between gap-3 p-3">
                                    <span className="flex items-center gap-2"><FileText className="size-4" />{a.name}</span>
                                    <span className="flex gap-3">
                                        <a className="underline" href={`/attachments/${a.id}`} target="_blank" rel="noreferrer">Buka</a>
                                        <button type="button" className="text-destructive underline" onClick={() => { if (window.confirm(`Hapus bukti ${a.name}?`)) router.delete(`/attachments/${a.id}`, { preserveScroll: true, onSuccess: () => setEvidence(null) }); }}>Hapus</button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : <p className="text-sm text-muted-foreground">Belum ada bukti.</p>}
                    {remaining > 0 ? (
                        <label className="grid gap-1 text-sm">Tambah bukti ({remaining} slot)
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => upload(e.target.files)} />
                            <span className="text-xs text-destructive">{errors.attachments}</span>
                        </label>
                    ) : <p className="text-xs text-muted-foreground">Slot bukti penuh.</p>}
                </DialogContent>
            </Dialog>
        </>
    );
}
