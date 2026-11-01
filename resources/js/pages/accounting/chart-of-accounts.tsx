import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';
type Account = {
    id: number;
    code: string;
    name: string;
    type: AccountType;
    module: string | null;
    definition: string | null;
    is_contra: boolean;
    is_active: boolean;
};

const labels: Record<AccountType, string> = { asset: 'Aset', liability: 'Kewajiban', equity: 'Ekuitas', income: 'Pendapatan', expense: 'Beban' };
const typeByPrefix: Record<string, AccountType> = { '1': 'asset', '2': 'liability', '3': 'equity', '4': 'income', '5': 'expense' };
const normalBalance = (type: AccountType) => (type === 'asset' || type === 'expense' ? 'Debit' : 'Kredit');

export default function ChartOfAccounts({ accounts = [] }: { accounts?: Account[] }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);

    const addForm = useForm({ code: '', name: '', type: 'asset' as AccountType, module: '', definition: '' });
    const editForm = useForm({ name: '', module: '', definition: '' });

    const openEdit = (account: Account) => {
        editForm.setData({ name: account.name, module: account.module ?? '', definition: account.definition ?? '' });
        editForm.clearErrors();
        setEditing(account);
    };

    const columns: ColumnDef<Account>[] = [
        { accessorKey: 'code', header: 'Kode Akun', cell: ({ row }) => <span className="font-medium tabular-nums">{row.original.code}</span> },
        {
            accessorKey: 'name',
            header: 'Nama Akun',
            cell: ({ row }) => (
                <span className={row.original.is_active ? undefined : 'text-muted-foreground line-through'}>
                    {row.original.name}
                    {row.original.is_contra && <span className="ml-2 text-xs text-muted-foreground">(pengurang)</span>}
                </span>
            ),
        },
        { accessorKey: 'module', header: 'Modul', cell: ({ row }) => row.original.module ?? '—' },
        {
            accessorKey: 'type',
            header: 'Jenis',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <Badge variant="outline">{labels[row.original.type]}</Badge>
                    <span className="text-xs text-muted-foreground">{normalBalance(row.original.type)}</span>
                </div>
            ),
        },
        { accessorKey: 'definition', header: 'Definisi', cell: ({ row }) => <span className="block max-w-md whitespace-normal text-muted-foreground">{row.original.definition ?? '—'}</span> },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label={`Aksi ${row.original.name}`}>
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => openEdit(row.original)}>Ubah nama / definisi</DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={`/accounting/ledger?account_id=${row.original.id}`}>Lihat buku besar</Link>
                        </DropdownMenuItem>
                        {row.original.is_active && (
                            <DropdownMenuItem asChild>
                                <Link href={`/accounting/master-data/accounts/${row.original.id}/deactivate`} method="post" className="text-destructive">
                                    Nonaktifkan
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const prefixMismatch = addForm.data.code && typeByPrefix[addForm.data.code[0]] !== addForm.data.type;

    return (
        <>
            <Head title="Master Akun" />
            <main className="mx-auto w-full max-w-[1440px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">System</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Master Akun</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Awalan kode: 1 Aset, 2 Kewajiban, 3 Ekuitas, 4 Pendapatan, 5 Beban. Saldo normal: 1 dan 5 debit; 2, 3, dan 4 kredit. Nama yang diubah muncul di menu dan laporan.
                        </p>
                    </div>
                    <Button onClick={() => setAdding(true)}>
                        <Plus />
                        Tambah Akun
                    </Button>
                </header>

                {errors.account && (
                    <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                        {errors.account}
                    </p>
                )}

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-sm text-muted-foreground">Total akun</p>
                        <p className="mt-1 text-2xl font-semibold">{accounts.length}</p>
                    </div>
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-sm text-muted-foreground">Akun aktif</p>
                        <p className="mt-1 text-2xl font-semibold">{accounts.filter((a) => a.is_active).length}</p>
                    </div>
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-sm text-muted-foreground">Kelompok digunakan</p>
                        <p className="mt-1 text-2xl font-semibold">{new Set(accounts.map((a) => a.type)).size} / 5</p>
                    </div>
                </div>

                <section className="rounded-xl border bg-card p-5">
                    <DataTable columns={columns} data={accounts} searchPlaceholder="Cari kode, nama, modul, atau definisi…" emptyMessage="Belum ada akun. Jalankan seeder COA atau tambahkan akun pertama." />
                </section>
            </main>

            <Dialog open={adding} onOpenChange={setAdding}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Akun</DialogTitle>
                        <DialogDescription>Akun baru masuk ke kelompok sesuai awalan kode.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            addForm.post('/accounting/master-data/accounts', { onSuccess: () => { setAdding(false); addForm.reset(); } });
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="account-code">Kode akun</Label>
                            <Input
                                id="account-code"
                                inputMode="numeric"
                                value={addForm.data.code}
                                onChange={(event) => {
                                    const code = event.target.value.replace(/\D/g, '');
                                    addForm.setData((data) => ({ ...data, code, type: typeByPrefix[code[0]] ?? data.type }));
                                }}
                                required
                            />
                            <p className="text-xs text-destructive">{addForm.errors.code}</p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account-name">Nama akun</Label>
                            <Input id="account-name" value={addForm.data.name} onChange={(event) => addForm.setData('name', event.target.value)} required />
                            <p className="text-xs text-destructive">{addForm.errors.name}</p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account-type">Jenis</Label>
                            <select id="account-type" className="rounded-md border bg-background p-2 text-sm" value={addForm.data.type} onChange={(event) => addForm.setData('type', event.target.value as AccountType)}>
                                {Object.entries(labels).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <p className="text-xs text-destructive">{addForm.errors.type ?? (prefixMismatch ? 'Jenis tidak sesuai awalan kode.' : '')}</p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account-module">Modul</Label>
                            <Input id="account-module" value={addForm.data.module} onChange={(event) => addForm.setData('module', event.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account-definition">Definisi</Label>
                            <Input id="account-definition" value={addForm.data.definition} onChange={(event) => addForm.setData('definition', event.target.value)} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={addForm.processing || Boolean(prefixMismatch)}>Simpan akun</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ubah akun {editing?.code}</DialogTitle>
                        <DialogDescription>Kode dan jenis tidak bisa diubah karena dipakai oleh histori jurnal.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!editing) return;
                            router.patch(`/accounting/master-data/accounts/${editing.id}`, editForm.data, { onSuccess: () => setEditing(null), preserveScroll: true });
                        }}
                        className="space-y-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Nama akun</Label>
                            <Input id="edit-name" value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} required />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-module">Modul</Label>
                            <Input id="edit-module" value={editForm.data.module} onChange={(event) => editForm.setData('module', event.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-definition">Definisi</Label>
                            <Input id="edit-definition" value={editForm.data.definition} onChange={(event) => editForm.setData('definition', event.target.value)} />
                        </div>
                        <DialogFooter>
                            <Button type="submit">Simpan perubahan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
