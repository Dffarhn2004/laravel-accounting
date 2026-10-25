import { Head, Link, useForm } from '@inertiajs/react';

type Row = { id: number; name: string; code?: string; type?: string };

export default function MasterData({
    accounts = [],
    branches = [],
    customers = [],
    vendors = [],
    taxCodes = [],
}: {
    accounts?: Row[];
    branches?: Row[];
    customers?: Row[];
    vendors?: Row[];
    taxCodes?: Row[];
}) {
    const branchForm = useForm({ name: '', code: '' });
    const accountForm = useForm({ name: '', code: '', type: 'asset' });
    const groups = [
        ['Chart of Accounts', accounts],
        ['Cabang', branches],
        ['Customer', customers],
        ['Vendor', vendors],
        ['Tax Code', taxCodes],
    ] as const;

    return (
        <>
            <Head title="Master Data" />
            <main className="mx-auto max-w-7xl space-y-6 p-4 md:p-8">
                <Link href="/dashboard" className="text-sm text-muted-foreground">Kembali ke dashboard</Link>
                <header>
                    <h1 className="text-3xl font-semibold tracking-tight">Master Data</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Data dasar accounting dalam scope company aktif.</p>
                </header>
                <div className="grid gap-4 lg:grid-cols-2">
                    <form onSubmit={(event) => { event.preventDefault(); branchForm.post('/accounting/master-data/branches'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Cabang</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <input aria-label="Nama cabang" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={branchForm.data.name} onChange={(event) => branchForm.setData('name', event.target.value)} placeholder="Nama cabang" />
                            <input aria-label="Kode cabang" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={branchForm.data.code} onChange={(event) => branchForm.setData('code', event.target.value)} placeholder="Kode" />
                        </div>
                        <button disabled={branchForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan cabang</button>
                    </form>
                    <form onSubmit={(event) => { event.preventDefault(); accountForm.post('/accounting/master-data/accounts'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Akun</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <input aria-label="Kode akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.code} onChange={(event) => accountForm.setData('code', event.target.value)} placeholder="Kode" />
                            <input aria-label="Nama akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.name} onChange={(event) => accountForm.setData('name', event.target.value)} placeholder="Nama akun" />
                            <select aria-label="Tipe akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.type} onChange={(event) => accountForm.setData('type', event.target.value)}>
                                {['asset', 'liability', 'equity', 'income', 'expense'].map((type) => <option key={type} value={type}>{type}</option>)}
                            </select>
                        </div>
                        <button disabled={accountForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan akun</button>
                    </form>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {groups.map(([title, rows]) => (
                        <section key={title} className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">{title}</h2>
                            {rows.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">Belum ada data.</p>
                            ) : (
                                <ul className="mt-4 divide-y text-sm">
                                    {rows.slice(0, 8).map((row) => (
                                        <li key={row.id} className="flex justify-between gap-3 py-2">
                                            <span>{row.name}</span>
                                            <span className="flex items-center gap-2 text-muted-foreground">
                                                {row.code ?? row.type ?? '—'}
                                                {title === 'Cabang' && (
                                                    <Link
                                                        method="post"
                                                        href={`/accounting/master-data/branches/${row.id}/deactivate`}
                                                        className="text-xs text-destructive hover:underline"
                                                    >
                                                        Nonaktifkan
                                                    </Link>
                                                )}
                                                {title === 'Chart of Accounts' && (
                                                    <Link
                                                        method="post"
                                                        href={`/accounting/master-data/accounts/${row.id}/deactivate`}
                                                        className="text-xs text-destructive hover:underline"
                                                    >
                                                        Nonaktifkan
                                                    </Link>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            </main>
        </>
    );
}
