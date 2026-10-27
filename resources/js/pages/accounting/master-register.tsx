import { Head, Link, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = { id: number; code: string; name: string; is_active?: boolean };
type Entity = 'branches' | 'customers' | 'vendors';
const config: Record<Entity, { title: string; singular: string; description: string; store: string; deactivate?: string }> = {
    branches: { title: 'Cabang / Brand', singular: 'Cabang', description: 'Kelola cabang atau brand yang dapat digunakan dalam transaksi dan laporan.', store: '/accounting/master-data/branches', deactivate: '/accounting/master-data/branches' },
    customers: { title: 'Pelanggan', singular: 'Pelanggan', description: 'Kelola pelanggan yang dapat dipilih saat membuat invoice.', store: '/accounting/master-data/customers' },
    vendors: { title: 'Pemasok', singular: 'Pemasok', description: 'Kelola pemasok untuk kebutuhan pembelian dan pengeluaran.', store: '/accounting/master-data/vendors' },
};

export default function MasterRegister({ entity, rows = [] }: { entity: Entity; rows?: Row[] }) {
    const page = config[entity];
    const form = useForm({ code: '', name: '' });
    const columns: ColumnDef<Row>[] = [
        { accessorKey: 'code', header: 'Kode', cell: ({ row }) => <span className="font-medium tabular-nums">{row.original.code}</span> },
        { accessorKey: 'name', header: 'Nama' },
        ...(entity === 'branches' ? [{ accessorKey: 'is_active', header: 'Status', cell: ({ row }: { row: { original: Row } }) => <Badge variant={row.original.is_active ? 'secondary' : 'outline'}>{row.original.is_active ? 'Aktif' : 'Nonaktif'}</Badge> }] : []),
        { id: 'actions', header: '', cell: ({ row }) => <DropdownMenu><DropdownMenuTrigger asChild><Button variant="ghost" size="icon" aria-label={`Aksi ${row.original.name}`}><MoreHorizontal /></Button></DropdownMenuTrigger><DropdownMenuContent align="end"><DropdownMenuItem asChild><Link href={entity === 'customers' ? '/transactions/invoices' : entity === 'vendors' ? '/transactions/expenses' : '/accounting/reports'}>Lihat transaksi terkait</Link></DropdownMenuItem>{page.deactivate && row.original.is_active && <DropdownMenuItem asChild><Link method="post" href={`${page.deactivate}/${row.original.id}/deactivate`} className="text-destructive">Nonaktifkan</Link></DropdownMenuItem>}</DropdownMenuContent></DropdownMenu> },
    ];
    return <><Head title={page.title} /><main className="mx-auto w-full max-w-[1440px] space-y-6 p-4 md:p-8"><header className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-sm text-muted-foreground">Master Data</p><h1 className="text-3xl font-semibold tracking-tight">{page.title}</h1><p className="mt-1 text-sm text-muted-foreground">{page.description}</p></div><Dialog><DialogTrigger asChild><Button><Plus />Tambah {page.singular}</Button></DialogTrigger><DialogContent><DialogHeader><DialogTitle>Tambah {page.singular}</DialogTitle><DialogDescription>Masukkan data master untuk perusahaan aktif.</DialogDescription></DialogHeader><form onSubmit={(event) => { event.preventDefault(); form.post(page.store); }} className="space-y-4"><div className="grid gap-2"><Label htmlFor="master-code">Kode</Label><Input id="master-code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} required /><p className="text-xs text-destructive">{form.errors.code}</p></div><div className="grid gap-2"><Label htmlFor="master-name">Nama</Label><Input id="master-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required /><p className="text-xs text-destructive">{form.errors.name}</p></div><DialogFooter><Button type="submit" disabled={form.processing}>Simpan</Button></DialogFooter></form></DialogContent></Dialog></header><section className="rounded-xl border bg-card p-5"><DataTable data={rows} columns={columns} searchPlaceholder={`Cari kode atau nama ${page.singular.toLowerCase()}…`} emptyMessage={`Belum ada ${page.singular.toLowerCase()}. Tambahkan data pertama untuk memulai.`} /></section></main></>;
}
