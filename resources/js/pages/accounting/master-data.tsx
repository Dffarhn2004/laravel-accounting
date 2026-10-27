import { Head, Link, useForm } from '@inertiajs/react';

type Row = { id: number; name: string; code?: string; type?: string };
type CashAccount = { id: number; name: string; type: string; number?: string; account?: { code: string; name: string } };
type Product = { id: number; sku: string; name: string; unit: string; is_active: boolean; revenue_account?: { code: string; name: string } };

export default function MasterData({
    screen = 'overview',
    accounts = [],
    branches = [],
    customers = [],
    vendors = [],
    taxCodes = [],
    cashAccounts = [],
    inventoryItems = [],
}: {
    screen?: 'overview' | 'accounts' | 'branches' | 'customers' | 'vendors' | 'cash-accounts' | 'products';
    accounts?: Row[];
    branches?: Row[];
    customers?: Row[];
    vendors?: Row[];
    taxCodes?: Row[];
    cashAccounts?: CashAccount[];
    inventoryItems?: Product[];
}) {
    const branchForm = useForm({ name: '', code: '' });
    const accountForm = useForm({ name: '', code: '', type: 'asset' });
    const customerForm = useForm({ name: '', code: '' });
    const vendorForm = useForm({ name: '', code: '' });
    const cashAccountForm = useForm({ name: '', account_id: accounts[0]?.id ?? '', type: 'cash', number: '' });
    const productForm = useForm({ sku: '', name: '', unit: 'unit', minimum_stock: '0', inventory_account_id: accounts.find((account) => account.type === 'asset')?.id ?? '', cogs_account_id: accounts.find((account) => account.type === 'expense')?.id ?? '', revenue_account_id: accounts.find((account) => account.type === 'income')?.id ?? '' });
    const groups = [
        ['Chart of Accounts', accounts],
        ['Cabang', branches],
        ['Customer', customers],
        ['Vendor', vendors],
        ['Tax Code', taxCodes],
    ] as const;
    const isAccounts = screen === 'accounts';
    const isBranches = screen === 'branches';
    const isCustomers = screen === 'customers';
    const isVendors = screen === 'vendors';
    const isCashAccounts = screen === 'cash-accounts';
    const isProducts = screen === 'products';
    const title = isAccounts ? 'Chart of Accounts' : isBranches ? 'Cabang / Brand' : isCustomers ? 'Pelanggan' : isVendors ? 'Pemasok' : isCashAccounts ? 'Rekening Kas & Bank' : isProducts ? 'Produk & Harga' : 'Master Data';
    const description = isAccounts
        ? 'Kelola struktur akun perusahaan. Akun yang sudah dipakai tidak dapat dihapus; nonaktifkan bila sudah tidak digunakan.'
        : isBranches
          ? 'Kelola cabang dan brand dalam scope perusahaan aktif.'
          : isCustomers
            ? 'Kelola pelanggan yang dapat dipakai pada invoice perusahaan aktif.'
            : isVendors
              ? 'Kelola pemasok untuk pencatatan pembelian dan pengeluaran.'
              : isCashAccounts
                ? 'Hubungkan kas, bank, atau e-wallet dengan akun buku besar yang sesuai.'
                : isProducts
                  ? 'Kelola produk atau item persediaan dan pemetaan akun akuntansinya.'
          : 'Data dasar accounting dalam scope company aktif.';
    const visibleGroups = isAccounts ? groups.filter(([groupTitle]) => groupTitle === 'Chart of Accounts') : isBranches ? groups.filter(([groupTitle]) => groupTitle === 'Cabang') : isCustomers ? groups.filter(([groupTitle]) => groupTitle === 'Customer') : isVendors ? groups.filter(([groupTitle]) => groupTitle === 'Vendor') : (isCashAccounts || isProducts) ? [] : groups;

    return (
        <>
            <Head title="Master Data" />
            <main className="mx-auto max-w-7xl space-y-6 p-4 md:p-8">
                <header className="space-y-1">
                    <Link href="/dashboard" className="text-sm text-muted-foreground hover:text-foreground">Dashboard / Master Data</Link>
                    <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </header>
                <div className="grid gap-4 lg:grid-cols-2">
                    {(!isAccounts && !isCustomers && !isVendors && !isCashAccounts && !isProducts) && <form onSubmit={(event) => { event.preventDefault(); branchForm.post('/accounting/master-data/branches'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Cabang</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <input aria-label="Nama cabang" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={branchForm.data.name} onChange={(event) => branchForm.setData('name', event.target.value)} placeholder="Nama cabang" />
                            <input aria-label="Kode cabang" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={branchForm.data.code} onChange={(event) => branchForm.setData('code', event.target.value)} placeholder="Kode" />
                        </div>
                        <button disabled={branchForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan cabang</button>
                    </form>}
                    {(!isBranches && !isCustomers && !isVendors && !isCashAccounts && !isProducts) && <form onSubmit={(event) => { event.preventDefault(); accountForm.post('/accounting/master-data/accounts'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Akun</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <input aria-label="Kode akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.code} onChange={(event) => accountForm.setData('code', event.target.value)} placeholder="Kode" />
                            <input aria-label="Nama akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.name} onChange={(event) => accountForm.setData('name', event.target.value)} placeholder="Nama akun" />
                            <select aria-label="Tipe akun" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={accountForm.data.type} onChange={(event) => accountForm.setData('type', event.target.value)}>
                                {['asset', 'liability', 'equity', 'income', 'expense'].map((type) => <option key={type} value={type}>{type}</option>)}
                            </select>
                        </div>
                        <button disabled={accountForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan akun</button>
                    </form>}
                    {isCustomers && <form onSubmit={(event) => { event.preventDefault(); customerForm.post('/accounting/master-data/customers'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Pelanggan</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <input required aria-label="Kode pelanggan" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={customerForm.data.code} onChange={(event) => customerForm.setData('code', event.target.value)} placeholder="Kode pelanggan" />
                            <input required aria-label="Nama pelanggan" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={customerForm.data.name} onChange={(event) => customerForm.setData('name', event.target.value)} placeholder="Nama pelanggan" />
                        </div>
                        <button disabled={customerForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan pelanggan</button>
                    </form>}
                    {isVendors && <form onSubmit={(event) => { event.preventDefault(); vendorForm.post('/accounting/master-data/vendors'); }} className="rounded-xl border bg-card p-5">
                        <h2 className="font-semibold">Tambah Pemasok</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <input required aria-label="Kode pemasok" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={vendorForm.data.code} onChange={(event) => vendorForm.setData('code', event.target.value)} placeholder="Kode pemasok" />
                            <input required aria-label="Nama pemasok" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={vendorForm.data.name} onChange={(event) => vendorForm.setData('name', event.target.value)} placeholder="Nama pemasok" />
                        </div>
                        <button disabled={vendorForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan pemasok</button>
                    </form>}
                    {isCashAccounts && <form onSubmit={(event) => { event.preventDefault(); cashAccountForm.post('/accounting/master-data/accounts-cash'); }} className="rounded-xl border bg-card p-5 lg:col-span-2">
                        <h2 className="font-semibold">Tambah Rekening Kas / Bank</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <input required aria-label="Nama rekening" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={cashAccountForm.data.name} onChange={(event) => cashAccountForm.setData('name', event.target.value)} placeholder="Nama rekening" />
                            <select required aria-label="Akun buku besar" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={cashAccountForm.data.account_id} onChange={(event) => cashAccountForm.setData('account_id', Number(event.target.value))}>{accounts.filter((account) => account.type === 'asset').map((account) => <option key={account.id} value={account.id}>{account.code} — {account.name}</option>)}</select>
                            <select aria-label="Tipe rekening" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={cashAccountForm.data.type} onChange={(event) => cashAccountForm.setData('type', event.target.value)}><option value="cash">Kas</option><option value="bank">Bank</option><option value="e-wallet">E-wallet</option></select>
                            <input aria-label="Nomor rekening" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={cashAccountForm.data.number} onChange={(event) => cashAccountForm.setData('number', event.target.value)} placeholder="Nomor / 4 digit akhir" />
                        </div>
                        <button disabled={cashAccountForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan rekening</button>
                    </form>}
                    {isProducts && <form onSubmit={(event) => { event.preventDefault(); productForm.post('/accounting/master-data/products'); }} className="rounded-xl border bg-card p-5 lg:col-span-2">
                        <h2 className="font-semibold">Tambah Produk / Item</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <input required aria-label="SKU" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.sku} onChange={(event) => productForm.setData('sku', event.target.value)} placeholder="SKU" />
                            <input required aria-label="Nama produk" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.name} onChange={(event) => productForm.setData('name', event.target.value)} placeholder="Nama produk / jasa" />
                            <input required aria-label="Satuan" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.unit} onChange={(event) => productForm.setData('unit', event.target.value)} placeholder="Satuan" />
                            <select required aria-label="Akun persediaan" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.inventory_account_id} onChange={(event) => productForm.setData('inventory_account_id', Number(event.target.value))}>{accounts.map((account) => <option key={account.id} value={account.id}>Persediaan: {account.code} — {account.name}</option>)}</select>
                            <select required aria-label="Akun HPP" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.cogs_account_id} onChange={(event) => productForm.setData('cogs_account_id', Number(event.target.value))}>{accounts.map((account) => <option key={account.id} value={account.id}>HPP: {account.code} — {account.name}</option>)}</select>
                            <select required aria-label="Akun pendapatan" className="rounded-md border bg-transparent px-3 py-2 text-sm" value={productForm.data.revenue_account_id} onChange={(event) => productForm.setData('revenue_account_id', Number(event.target.value))}>{accounts.map((account) => <option key={account.id} value={account.id}>Pendapatan: {account.code} — {account.name}</option>)}</select>
                        </div>
                        <button disabled={productForm.processing} className="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan produk</button>
                    </form>}
                </div>
                {isCashAccounts && <section className="rounded-xl border bg-card"><div className="border-b px-5 py-4"><h2 className="font-semibold">Rekening aktif</h2><p className="text-sm text-muted-foreground">Nomor rekening ditampilkan secara terbatas.</p></div>{cashAccounts.length === 0 ? <p className="p-8 text-center text-sm text-muted-foreground">Belum ada rekening kas atau bank.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[600px] text-sm"><thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground"><tr><th className="p-3">Nama</th><th className="p-3">Tipe</th><th className="p-3">Nomor</th><th className="p-3">Akun GL</th></tr></thead><tbody>{cashAccounts.map((cashAccount) => <tr key={cashAccount.id} className="border-b last:border-0"><td className="p-3 font-medium">{cashAccount.name}</td><td className="p-3 capitalize">{cashAccount.type}</td><td className="p-3">{cashAccount.number ? `•••• ${cashAccount.number.slice(-4)}` : '—'}</td><td className="p-3">{cashAccount.account ? `${cashAccount.account.code} — ${cashAccount.account.name}` : '—'}</td></tr>)}</tbody></table></div>}</section>}
                {isProducts && <section className="rounded-xl border bg-card"><div className="border-b px-5 py-4"><h2 className="font-semibold">Produk aktif</h2><p className="text-sm text-muted-foreground">Harga jual belum tersedia pada model data saat ini; halaman ini menampilkan master dan pemetaan akun.</p></div>{inventoryItems.length === 0 ? <p className="p-8 text-center text-sm text-muted-foreground">Belum ada produk atau item.</p> : <div className="overflow-x-auto"><table className="w-full min-w-[600px] text-sm"><thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground"><tr><th className="p-3">SKU</th><th className="p-3">Nama</th><th className="p-3">Satuan</th><th className="p-3">Akun pendapatan</th></tr></thead><tbody>{inventoryItems.map((item) => <tr key={item.id} className="border-b last:border-0"><td className="p-3 font-medium">{item.sku}</td><td className="p-3">{item.name}</td><td className="p-3">{item.unit}</td><td className="p-3">{item.revenue_account ? `${item.revenue_account.code} — ${item.revenue_account.name}` : '—'}</td></tr>)}</tbody></table></div>}</section>}
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {visibleGroups.map(([groupTitle, rows]) => (
                        <section key={groupTitle} className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">{groupTitle}</h2>
                            {rows.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">Belum ada data.</p>
                            ) : (
                                <ul className="mt-4 divide-y text-sm">
                                    {rows.slice(0, 8).map((row) => (
                                        <li key={row.id} className="flex justify-between gap-3 py-2">
                                            <span>{row.name}</span>
                                            <span className="flex items-center gap-2 text-muted-foreground">
                                                {row.code ?? row.type ?? '—'}
                                                {groupTitle === 'Cabang' && (
                                                    <Link
                                                        method="post"
                                                        href={`/accounting/master-data/branches/${row.id}/deactivate`}
                                                        className="text-xs text-destructive hover:underline"
                                                    >
                                                        Nonaktifkan
                                                    </Link>
                                                )}
                                                {groupTitle === 'Chart of Accounts' && (
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
