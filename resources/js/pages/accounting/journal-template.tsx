import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string; type: string };
type Branch = { id: number; code: string; name: string; city: string | null; brand: string | null; is_active: boolean };
type Year = { id: number; starts_on: string; ends_on: string; status: string };
type Product = { id: number; sku: string; name: string; unit: string; price: string; revenue_account_id: number | null; is_active: boolean };
type InventoryType = { id: number; sku: string; name: string; unit: string; minimum_stock: string; inventory_account_id: number; cogs_account_id: number; revenue_account_id: number | null; is_active: boolean };
type AssetCategory = { id: number; asset_account_id: number; accumulated_account_id: number | null; expense_account_id: number | null; useful_life_years: number; asset_account: { code: string; name: string } };
type Option = { id: number; group: string; code: string; label: string; account_id: number | null; meta: Record<string, string> | null; is_active: boolean };

type Props = {
    groups: Record<string, string>;
    branches: Branch[];
    years: Year[];
    products: Product[];
    inventoryTypes: InventoryType[];
    assetCategories: AssetCategory[];
    options: Option[];
    accounts: Account[];
};

const base = '/system/template';
const cell = 'p-2 align-top';
const select = 'w-full min-w-40 rounded-md border bg-background p-2 text-sm';

const sections = [
    ['cabang', '1. Cabang / Kota / Brand'],
    ['tahun', '2. Template Tahun'],
    ['produk', '3. Produk & Harga'],
    ['persediaan', '4. Jenis Persediaan'],
    ['aset', '5. Setting Akun & Umur Aset'],
    ['invoice_payment', '6. Metode Bayar Invoice & Pelunasan'],
    ['outflow_payment', '7. Metode Bayar Beban / Aset / Persediaan'],
    ['treasury_type', '8. Jenis Transaksi Kas / Bank / Hutang / Modal'],
    ['treasury_category', '9. Kategori Akun Dari / Ke'],
    ['opening_account', '10. Akun untuk Modal Awal'],
] as const;

function Section({ id, title, description, children }: { id: string; title: string; description?: string; children: ReactNode }) {
    return (
        <section id={id} className="scroll-mt-20 rounded-xl border bg-card">
            <div className="border-b px-5 py-4">
                <h2 className="font-semibold">{title}</h2>
                {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
            </div>
            <div className="overflow-x-auto p-3">{children}</div>
        </section>
    );
}

function Th({ children, className = '' }: { children?: ReactNode; className?: string }) {
    return <th className={`p-2 text-left text-xs font-medium text-muted-foreground ${className}`}>{children}</th>;
}

function Err({ message }: { message?: string }) {
    return message ? <p role="alert" className="mt-1 text-xs text-destructive">{message}</p> : null;
}

function AccountSelect({ accounts, value, onChange, types, allowEmpty = false, disabled = false }: { accounts: Account[]; value: number | string | null; onChange: (id: number | '') => void; types?: string[]; allowEmpty?: boolean; disabled?: boolean }) {
    const list = types ? accounts.filter((a) => types.includes(a.type) || a.id === value) : accounts;

    return (
        <select className={select} disabled={disabled} value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))} aria-label="Akun">
            {allowEmpty && <option value="">—</option>}
            {!allowEmpty && value === '' && <option value="">Pilih akun</option>}
            {list.map((a) => (
                <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
            ))}
        </select>
    );
}

/* ---------- 1. Cabang ---------- */
function BranchRow({ branch }: { branch: Branch }) {
    const form = useForm({ name: branch.name, city: branch.city ?? '', brand: branch.brand ?? '', is_active: branch.is_active });

    return (
        <tr className="border-b last:border-0">
            <td className={`${cell} font-medium tabular-nums`}>{branch.code}</td>
            <td className={cell}><Input aria-label="Nama cabang" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /><Err message={form.errors.name} /></td>
            <td className={cell}><Input aria-label="Kota" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Brand" value={form.data.brand} onChange={(e) => form.setData('brand', e.target.value)} /></td>
            <td className={cell}>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Aktif</label>
                <Err message={(form.errors as Record<string, string>).branch} />
            </td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/branches/${branch.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function BranchAdd() {
    const form = useForm({ code: '', name: '', city: '', brand: '' });

    return (
        <tr className="bg-muted/30">
            <td className={cell}><Input aria-label="Kode cabang baru" placeholder="Kode" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /><Err message={form.errors.code} /></td>
            <td className={cell}><Input aria-label="Nama cabang baru" placeholder="Nama" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Kota baru" placeholder="Kota" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Brand baru" placeholder="Brand" value={form.data.brand} onChange={(e) => form.setData('brand', e.target.value)} /></td>
            <td className={cell} />
            <td className={cell}><Button size="sm" disabled={form.processing || !form.data.code || !form.data.name} onClick={() => form.post(`${base}/branches`, { preserveScroll: true, onSuccess: () => form.reset() })}>Tambah</Button></td>
        </tr>
    );
}

/* ---------- 2. Tahun ---------- */
function YearAdd() {
    const form = useForm({ year: String(new Date().getFullYear() + 1) });

    return (
        <div className="flex flex-wrap items-start gap-2 p-2">
            <div>
                <Input aria-label="Tahun buku baru" type="number" className="w-32" value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} />
                <Err message={form.errors.year} />
            </div>
            <Button size="sm" disabled={form.processing} onClick={() => form.post(`${base}/years`, { preserveScroll: true })}>Tambah tahun</Button>
        </div>
    );
}

/* ---------- 3. Produk ---------- */
function ProductRow({ product, accounts }: { product: Product; accounts: Account[] }) {
    const form = useForm({ name: product.name, unit: product.unit, price: product.price, revenue_account_id: product.revenue_account_id ?? ('' as number | ''), is_active: product.is_active });

    return (
        <tr className="border-b last:border-0">
            <td className={`${cell} font-medium`}>{product.sku}</td>
            <td className={cell}><Input aria-label="Nama produk" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Satuan" className="w-24" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Harga" type="number" min="0" step="0.01" className="w-36 text-right tabular-nums" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} /><Err message={form.errors.price} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['income']} value={form.data.revenue_account_id} onChange={(id) => form.setData('revenue_account_id', id)} /><Err message={form.errors.revenue_account_id} /></td>
            <td className={cell}><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Aktif</label></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/products/${product.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function ProductAdd({ accounts }: { accounts: Account[] }) {
    const form = useForm({ sku: '', name: '', unit: 'unit', price: '0', revenue_account_id: '' as number | '' });

    return (
        <tr className="bg-muted/30">
            <td className={cell}><Input aria-label="SKU baru" placeholder="SKU" value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} /><Err message={form.errors.sku} /></td>
            <td className={cell}><Input aria-label="Nama produk baru" placeholder="Nama" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Satuan baru" className="w-24" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Harga baru" type="number" min="0" step="0.01" className="w-36 text-right" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['income']} value={form.data.revenue_account_id} onChange={(id) => form.setData('revenue_account_id', id)} /><Err message={form.errors.revenue_account_id} /></td>
            <td className={cell} />
            <td className={cell}><Button size="sm" disabled={form.processing || !form.data.sku || !form.data.name || form.data.revenue_account_id === ''} onClick={() => form.post(`${base}/products`, { preserveScroll: true, onSuccess: () => form.reset() })}>Tambah</Button></td>
        </tr>
    );
}

/* ---------- 4. Jenis persediaan ---------- */
function InventoryRow({ item, accounts }: { item: InventoryType; accounts: Account[] }) {
    const form = useForm({ name: item.name, unit: item.unit, minimum_stock: item.minimum_stock, inventory_account_id: item.inventory_account_id as number | '', cogs_account_id: item.cogs_account_id as number | '', revenue_account_id: (item.revenue_account_id ?? '') as number | '', is_active: item.is_active });

    return (
        <tr className="border-b last:border-0">
            <td className={`${cell} font-medium`}>{item.sku}</td>
            <td className={cell}><Input aria-label="Nama persediaan" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Satuan" className="w-24" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Stok minimum" type="number" min="0" step="0.0001" className="w-28 text-right" value={form.data.minimum_stock} onChange={(e) => form.setData('minimum_stock', e.target.value)} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} value={form.data.inventory_account_id} onChange={(id) => form.setData('inventory_account_id', id)} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} value={form.data.cogs_account_id} onChange={(id) => form.setData('cogs_account_id', id)} /></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/inventory/${item.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function InventoryAdd({ accounts }: { accounts: Account[] }) {
    const form = useForm({ sku: '', name: '', unit: 'unit', minimum_stock: '0', inventory_account_id: '' as number | '', cogs_account_id: '' as number | '', revenue_account_id: '' as number | '' });

    return (
        <tr className="bg-muted/30">
            <td className={cell}><Input aria-label="SKU persediaan baru" placeholder="SKU" value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} /><Err message={form.errors.sku} /></td>
            <td className={cell}><Input aria-label="Nama persediaan baru" placeholder="Nama" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Satuan baru" className="w-24" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></td>
            <td className={cell}><Input aria-label="Stok minimum baru" type="number" min="0" className="w-28 text-right" value={form.data.minimum_stock} onChange={(e) => form.setData('minimum_stock', e.target.value)} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} value={form.data.inventory_account_id} onChange={(id) => form.setData('inventory_account_id', id)} /><Err message={form.errors.inventory_account_id} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} value={form.data.cogs_account_id} onChange={(id) => form.setData('cogs_account_id', id)} /><Err message={form.errors.cogs_account_id} /></td>
            <td className={cell}><Button size="sm" disabled={form.processing || !form.data.sku || !form.data.name || form.data.inventory_account_id === '' || form.data.cogs_account_id === ''} onClick={() => form.post(`${base}/inventory`, { preserveScroll: true, onSuccess: () => form.reset() })}>Tambah</Button></td>
        </tr>
    );
}

/* ---------- 5. Aset ---------- */
function AssetRow({ category, accounts }: { category: AssetCategory; accounts: Account[] }) {
    const form = useForm({ useful_life_years: String(category.useful_life_years), accumulated_account_id: (category.accumulated_account_id ?? '') as number | '', expense_account_id: (category.expense_account_id ?? '') as number | '' });

    return (
        <tr className="border-b last:border-0">
            <td className={`${cell} font-medium`}>{category.asset_account.code} — {category.asset_account.name}</td>
            <td className={cell}><Input aria-label="Umur (tahun)" type="number" min="0" max="80" className="w-24 text-right" value={form.data.useful_life_years} onChange={(e) => form.setData('useful_life_years', e.target.value)} /><p className="mt-1 text-xs text-muted-foreground">0 = tidak disusutkan</p></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} allowEmpty value={form.data.accumulated_account_id} onChange={(id) => form.setData('accumulated_account_id', id)} /><Err message={form.errors.accumulated_account_id} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} allowEmpty value={form.data.expense_account_id} onChange={(id) => form.setData('expense_account_id', id)} /><Err message={form.errors.expense_account_id} /></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/assets/${category.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function AssetAdd({ accounts }: { accounts: Account[] }) {
    const form = useForm({ asset_account_id: '' as number | '', useful_life_years: '4', accumulated_account_id: '' as number | '', expense_account_id: '' as number | '' });

    return (
        <tr className="bg-muted/30">
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} value={form.data.asset_account_id} onChange={(id) => form.setData('asset_account_id', id)} /><Err message={form.errors.asset_account_id} /></td>
            <td className={cell}><Input aria-label="Umur baru" type="number" min="0" max="80" className="w-24 text-right" value={form.data.useful_life_years} onChange={(e) => form.setData('useful_life_years', e.target.value)} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} allowEmpty value={form.data.accumulated_account_id} onChange={(id) => form.setData('accumulated_account_id', id)} /><Err message={form.errors.accumulated_account_id} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} allowEmpty value={form.data.expense_account_id} onChange={(id) => form.setData('expense_account_id', id)} /><Err message={form.errors.expense_account_id} /></td>
            <td className={cell}><Button size="sm" disabled={form.processing || form.data.asset_account_id === ''} onClick={() => form.post(`${base}/assets`, { preserveScroll: true, onSuccess: () => form.reset() })}>Tambah</Button></td>
        </tr>
    );
}

/* ---------- 6-10. Daftar pilihan ---------- */
function OptionRow({ option, accounts, categories }: { option: Option; accounts: Account[]; categories: string[] }) {
    const fixedAccount = option.group === 'treasury_category' || option.group === 'opening_account';
    const form = useForm({ label: option.label, account_id: (option.account_id ?? '') as number | '', is_active: option.is_active });

    return (
        <tr className="border-b last:border-0">
            {option.group === 'treasury_category' && <td className={`${cell} text-muted-foreground`}>{option.meta?.category}</td>}
            <td className={cell}><Input aria-label="Label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} /><Err message={form.errors.label} /></td>
            {option.group === 'treasury_type' ? (
                <td className={`${cell} text-sm text-muted-foreground`}>
                    Dari <Badge variant="outline">{option.meta?.from_category}</Badge> → Ke <Badge variant="outline">{option.meta?.to_category}</Badge>
                    {![option.meta?.from_category, option.meta?.to_category].every((c) => c && categories.includes(c)) && <p className="mt-1 text-xs text-destructive">Kategori belum punya akun (bagian 9).</p>}
                </td>
            ) : (
                <td className={cell}><AccountSelect accounts={accounts} allowEmpty={false} disabled={fixedAccount} value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} /><Err message={form.errors.account_id} /></td>
            )}
            <td className={cell}><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Aktif</label></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/options/${option.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function OptionAdd({ group, accounts, categories }: { group: string; accounts: Account[]; categories: string[] }) {
    const form = useForm({ group, label: '', account_id: '' as number | '', from_category: '', to_category: '', category: '' });
    const isType = group === 'treasury_type';
    const isCategory = group === 'treasury_category';
    const ready = form.data.label && (isType ? form.data.from_category && form.data.to_category : form.data.account_id !== '') && (!isCategory || form.data.category);

    return (
        <tr className="bg-muted/30">
            {isCategory && <td className={cell}><Input aria-label="Kode kategori" placeholder="kode_kategori" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} /><Err message={form.errors.category} /></td>}
            <td className={cell}><Input aria-label="Label baru" placeholder="Label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} /><Err message={form.errors.label} /></td>
            {isType ? (
                <td className={cell}>
                    <div className="flex gap-2">
                        {(['from_category', 'to_category'] as const).map((field) => (
                            <select key={field} aria-label={field === 'from_category' ? 'Kategori Dari' : 'Kategori Ke'} className={select} value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)}>
                                <option value="">{field === 'from_category' ? 'Dari (Kredit)…' : 'Ke (Debit)…'}</option>
                                {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        ))}
                    </div>
                </td>
            ) : (
                <td className={cell}><AccountSelect accounts={accounts} value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} /><Err message={form.errors.account_id} /></td>
            )}
            <td className={cell} />
            <td className={cell}><Button size="sm" disabled={form.processing || !ready} onClick={() => form.post(`${base}/options`, { preserveScroll: true, onSuccess: () => form.reset('label', 'account_id', 'from_category', 'to_category', 'category') })}>Tambah</Button></td>
        </tr>
    );
}

export default function JournalTemplate({ groups, branches, years, products, inventoryTypes, assetCategories, options, accounts }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const categories = [...new Set(options.filter((o) => o.group === 'treasury_category').map((o) => o.meta?.category ?? ''))].filter(Boolean);
    const empty = accounts.length === 0;
    const byGroup = (group: string) => options.filter((o) => o.group === group);

    const optionTable = (group: string, head: string[]) => (
        <table className="w-full min-w-[640px] text-sm">
            <thead className="border-b"><tr>{head.map((h) => <Th key={h}>{h}</Th>)}<Th /><Th /></tr></thead>
            <tbody>
                {byGroup(group).map((o) => <OptionRow key={o.id} option={o} accounts={accounts} categories={categories} />)}
                <OptionAdd group={group} accounts={accounts} categories={categories} />
            </tbody>
        </table>
    );

    return (
        <>
            <Head title="Template Jurnal" />
            <main className="mx-auto w-full max-w-[1440px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">System</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Template Jurnal</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Master untuk formulir transaksi: cabang, produk, persediaan, aturan akun, dan metode pembayaran. Perubahan di sini menjadi pilihan di formulir terkait. Cabang yang punya histori tidak dihapus, hanya dinonaktifkan.
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => router.post(`${base}/defaults`, {}, { preserveScroll: true })}>Muat template bawaan</Button>
                </header>

                {(errors.branch || empty) && (
                    <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                        {errors.branch ?? 'Belum ada akun. Klik "Muat template bawaan" untuk mengisi COA dan template awal.'}
                    </p>
                )}

                <nav aria-label="Bagian template" className="flex flex-wrap gap-2">
                    {sections.map(([id, title]) => (
                        <a key={id} href={`#${id}`} className="rounded-full border px-3 py-1 text-xs hover:bg-muted">{title}</a>
                    ))}
                </nav>

                <Section id="cabang" title={sections[0][1]} description="Satu baris per cabang. Kode tidak bisa diubah setelah dibuat.">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="border-b"><tr><Th>Kode</Th><Th>Nama</Th><Th>Kota</Th><Th>Brand</Th><Th>Status</Th><Th /></tr></thead>
                        <tbody>
                            {branches.map((b) => <BranchRow key={b.id} branch={b} />)}
                            <BranchAdd />
                        </tbody>
                    </table>
                </Section>

                <Section id="tahun" title={sections[1][1]} description="Tahun buku tersedia untuk posting dan pemilihan periode. Tutup buku ada di menu Tutup Buku & Pindah Tahun.">
                    <ul className="flex flex-wrap gap-2 p-2">
                        {years.map((y) => (
                            <li key={y.id}><Badge variant={y.status === 'open' ? 'secondary' : 'outline'}>{y.starts_on.slice(0, 4)} · {y.status === 'open' ? 'Terbuka' : 'Ditutup'}</Badge></li>
                        ))}
                        {years.length === 0 && <li className="text-sm text-muted-foreground">Belum ada tahun buku.</li>}
                    </ul>
                    <YearAdd />
                </Section>

                <Section id="produk" title={sections[2][1]} description="Harga master dipakai di Input Invoice (mode harga master). Akun pendapatan menentukan kelompok pendapatan.">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="border-b"><tr><Th>SKU</Th><Th>Nama</Th><Th>Satuan</Th><Th className="text-right">Harga (Rp)</Th><Th>Kelompok Pendapatan</Th><Th>Status</Th><Th /></tr></thead>
                        <tbody>
                            {products.map((p) => <ProductRow key={p.id} product={p} accounts={accounts} />)}
                            <ProductAdd accounts={accounts} />
                        </tbody>
                    </table>
                </Section>

                <Section id="persediaan" title={sections[3][1]} description="Akun persediaan dan HPP dipakai saat pembelian dan pemakaian stok.">
                    <table className="w-full min-w-[980px] text-sm">
                        <thead className="border-b"><tr><Th>SKU</Th><Th>Nama</Th><Th>Satuan</Th><Th className="text-right">Stok minimum</Th><Th>Akun persediaan</Th><Th>Akun HPP</Th><Th /></tr></thead>
                        <tbody>
                            {inventoryTypes.map((i) => <InventoryRow key={i.id} item={i} accounts={accounts} />)}
                            <InventoryAdd accounts={accounts} />
                        </tbody>
                    </table>
                </Section>

                <Section id="aset" title={sections[4][1]} description="Penyusutan garis lurus mulai bulan berikutnya. Umur bawaan hanya titik awal; mohon dikonfirmasi akuntan/konsultan pajak.">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead className="border-b"><tr><Th>Akun aset</Th><Th>Umur (tahun)</Th><Th>Akun akumulasi penyusutan</Th><Th>Akun beban penyusutan</Th><Th /></tr></thead>
                        <tbody>
                            {assetCategories.map((c) => <AssetRow key={c.id} category={c} accounts={accounts} />)}
                            <AssetAdd accounts={accounts} />
                        </tbody>
                    </table>
                </Section>

                <Section id="invoice_payment" title={sections[5][1]} description={groups.invoice_payment}>
                    {optionTable('invoice_payment', ['Metode', 'Akun'])}
                </Section>
                <Section id="outflow_payment" title={sections[6][1]} description={groups.outflow_payment}>
                    {optionTable('outflow_payment', ['Metode', 'Akun'])}
                </Section>
                <Section id="treasury_type" title={sections[7][1]} description="Jenis transaksi di Input Pindah Saldo Kas/Bank. Akun Dari (Kredit) dan Ke (Debit) dibatasi oleh kategori di bagian 9.">
                    {optionTable('treasury_type', ['Jenis transaksi', 'Kategori akun'])}
                </Section>
                <Section id="treasury_category" title={sections[8][1]} description="Setiap kategori berisi akun yang boleh dipilih sebagai Dari / Ke.">
                    {optionTable('treasury_category', ['Kategori', 'Label', 'Akun'])}
                </Section>
                <Section id="opening_account" title={sections[9][1]} description="Akun harta/hutang yang boleh diisi di Input Setoran Modal Awal.">
                    {optionTable('opening_account', ['Label', 'Akun'])}
                </Section>
            </main>
        </>
    );
}
