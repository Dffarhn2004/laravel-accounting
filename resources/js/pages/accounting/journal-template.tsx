import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
const select = 'w-full rounded-md border bg-background p-2 text-sm';

/** The ten sections of the menu, as tabs. `short` is the tab label, `title` the panel heading. */
const TABS = [
    { id: 'cabang', short: '1. Cabang', title: '1. Cabang / Kota / Brand', note: 'Satu baris per cabang. Kode tidak bisa diubah setelah dibuat.', add: 'Tambah cabang' },
    { id: 'tahun', short: '2. Tahun', title: '2. Template Tahun', note: 'Tahun buku tersedia untuk posting dan pemilihan periode. Tutup buku ada di menu Tutup Buku & Pindah Tahun.', add: 'Tambah tahun' },
    { id: 'produk', short: '3. Produk & Harga', title: '3. Produk & Harga', note: 'Harga master dipakai di Input Invoice (mode harga master). Akun pendapatan menentukan kelompok pendapatan.', add: 'Tambah produk' },
    { id: 'persediaan', short: '4. Persediaan', title: '4. Jenis Persediaan', note: 'Akun persediaan dan HPP dipakai saat pembelian dan pemakaian stok.', add: 'Tambah jenis persediaan' },
    { id: 'aset', short: '5. Akun & Umur Aset', title: '5. Setting Akun & Umur Aset', note: 'Penyusutan garis lurus mulai bulan berikutnya. Umur bawaan hanya titik awal; mohon dikonfirmasi akuntan/konsultan pajak.', add: 'Tambah kelompok aset' },
    { id: 'invoice_payment', short: '6. Bayar Invoice', title: '6. Metode Bayar Invoice & Pelunasan', note: 'Pilihan jenis pembayaran di Input Invoice dan Input Pelunasan Piutang, dan akun kas/bank/piutangnya.', add: 'Tambah metode' },
    { id: 'outflow_payment', short: '7. Bayar Beban/Aset/Stok', title: '7. Metode Bayar Beban / Aset / Persediaan', note: 'Pilihan jenis pembayaran di Input Beban, Pembelian Asset, dan Persediaan. Akun bertipe hutang membuat transaksi menjadi hutang terbuka.', add: 'Tambah metode' },
    { id: 'treasury_type', short: '8. Jenis Transaksi Kas', title: '8. Jenis Transaksi Kas / Bank / Hutang / Modal', note: 'Jenis transaksi di Input Pindah Saldo Kas/Bank. Akun Dari (Kredit) dan Ke (Debit) dibatasi oleh kategori di tab 9.', add: 'Tambah jenis transaksi' },
    { id: 'treasury_category', short: '9. Kategori Dari/Ke', title: '9. Kategori Akun Dari / Ke', note: 'Setiap kategori berisi akun yang boleh dipilih sebagai Dari / Ke.', add: 'Tambah akun ke kategori' },
    { id: 'opening_account', short: '10. Akun Modal Awal', title: '10. Akun yang Boleh Dipakai di Modal Awal', note: 'Akun harta/hutang yang boleh diisi di Input Setoran Modal Awal.', add: 'Tambah akun' },
] as const;
type TabId = (typeof TABS)[number]['id'];

function Err({ message }: { message?: string }) {
    return message ? <p role="alert" className="mt-1 text-xs text-destructive">{message}</p> : null;
}

function Th({ children, className = '' }: { children?: ReactNode; className?: string }) {
    return <th className={`p-2 text-left text-xs font-medium text-muted-foreground ${className}`}>{children}</th>;
}

function AccountSelect({ accounts, value, onChange, types, allowEmpty = false, disabled = false, label = 'Akun' }: { accounts: Account[]; value: number | string | null; onChange: (id: number | '') => void; types?: string[]; allowEmpty?: boolean; disabled?: boolean; label?: string }) {
    const list = types ? accounts.filter((a) => types.includes(a.type) || a.id === value) : accounts;

    return (
        <select className={`${select} min-w-40`} disabled={disabled} value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))} aria-label={label}>
            {allowEmpty && <option value="">—</option>}
            {!allowEmpty && value === '' && <option value="">Pilih akun</option>}
            {list.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
        </select>
    );
}

/** Shared frame of every "Tambah" dialog: fields in, one submit out. */
function AddDialog({ title, description, open, onClose, canSubmit, processing, onSubmit, children }: { title: string; description: string; open: boolean; onClose: () => void; canSubmit: boolean; processing: boolean; onSubmit: () => void; children: ReactNode }) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={(e) => { e.preventDefault(); if (canSubmit) onSubmit(); }}>
                    {children}
                    <DialogFooter><Button type="submit" disabled={!canSubmit || processing}>Simpan</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="grid gap-1 text-sm">{label}{children}<Err message={error} /></label>;
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

function BranchAdd({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ code: '', name: '', city: '', brand: '' });

    return (
        <AddDialog title="Tambah cabang" description="Kode tidak bisa diubah setelah dibuat." open={open} onClose={onClose} processing={form.processing} canSubmit={Boolean(form.data.code && form.data.name)}
            onSubmit={() => form.post(`${base}/branches`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } })}>
            <Field label="Kode" error={form.errors.code}><Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /></Field>
            <Field label="Nama" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
            <Field label="Kota"><Input value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} /></Field>
            <Field label="Brand"><Input value={form.data.brand} onChange={(e) => form.setData('brand', e.target.value)} /></Field>
        </AddDialog>
    );
}

/* ---------- 2. Tahun ---------- */
function YearAdd({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ year: String(new Date().getFullYear() + 1) });

    return (
        <AddDialog title="Tambah tahun buku" description="Membuat tahun buku 1 Januari sampai 31 Desember. Tahun yang sudah ada tidak digandakan." open={open} onClose={onClose} processing={form.processing} canSubmit={/^\d{4}$/.test(form.data.year)}
            onSubmit={() => form.post(`${base}/years`, { preserveScroll: true, onSuccess: () => onClose() })}>
            <Field label="Tahun" error={form.errors.year}><Input type="number" value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} /></Field>
        </AddDialog>
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
            <td className={cell}><AccountSelect accounts={accounts} types={['income']} value={form.data.revenue_account_id} onChange={(id) => form.setData('revenue_account_id', id)} label="Kelompok pendapatan" /><Err message={form.errors.revenue_account_id} /></td>
            <td className={cell}><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Aktif</label></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/products/${product.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function ProductAdd({ open, onClose, accounts }: { open: boolean; onClose: () => void; accounts: Account[] }) {
    const form = useForm({ sku: '', name: '', unit: 'unit', price: '0', revenue_account_id: '' as number | '' });

    return (
        <AddDialog title="Tambah produk" description="Harga master dipakai di Input Invoice." open={open} onClose={onClose} processing={form.processing} canSubmit={Boolean(form.data.sku && form.data.name && form.data.revenue_account_id !== '')}
            onSubmit={() => form.post(`${base}/products`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } })}>
            <Field label="SKU" error={form.errors.sku}><Input value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} /></Field>
            <Field label="Nama" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Satuan"><Input value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></Field>
                <Field label="Harga (Rp)" error={form.errors.price}><Input type="number" min="0" step="0.01" className="text-right" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} /></Field>
            </div>
            <Field label="Kelompok pendapatan" error={form.errors.revenue_account_id}><AccountSelect accounts={accounts} types={['income']} value={form.data.revenue_account_id} onChange={(id) => form.setData('revenue_account_id', id)} label="Kelompok pendapatan" /></Field>
        </AddDialog>
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
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} value={form.data.inventory_account_id} onChange={(id) => form.setData('inventory_account_id', id)} label="Akun persediaan" /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} value={form.data.cogs_account_id} onChange={(id) => form.setData('cogs_account_id', id)} label="Akun HPP" /></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/inventory/${item.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function InventoryAdd({ open, onClose, accounts }: { open: boolean; onClose: () => void; accounts: Account[] }) {
    const form = useForm({ sku: '', name: '', unit: 'unit', minimum_stock: '0', inventory_account_id: '' as number | '', cogs_account_id: '' as number | '', revenue_account_id: '' as number | '' });

    return (
        <AddDialog title="Tambah jenis persediaan" description="Akun persediaan dan HPP dipakai saat pembelian dan pemakaian stok." open={open} onClose={onClose} processing={form.processing}
            canSubmit={Boolean(form.data.sku && form.data.name && form.data.inventory_account_id !== '' && form.data.cogs_account_id !== '')}
            onSubmit={() => form.post(`${base}/inventory`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } })}>
            <Field label="SKU" error={form.errors.sku}><Input value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} /></Field>
            <Field label="Nama" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Satuan"><Input value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} /></Field>
                <Field label="Stok minimum"><Input type="number" min="0" className="text-right" value={form.data.minimum_stock} onChange={(e) => form.setData('minimum_stock', e.target.value)} /></Field>
            </div>
            <Field label="Akun persediaan" error={form.errors.inventory_account_id}><AccountSelect accounts={accounts} types={['asset']} value={form.data.inventory_account_id} onChange={(id) => form.setData('inventory_account_id', id)} label="Akun persediaan" /></Field>
            <Field label="Akun HPP" error={form.errors.cogs_account_id}><AccountSelect accounts={accounts} types={['expense']} value={form.data.cogs_account_id} onChange={(id) => form.setData('cogs_account_id', id)} label="Akun HPP" /></Field>
        </AddDialog>
    );
}

/* ---------- 5. Aset ---------- */
function AssetRow({ category, accounts }: { category: AssetCategory; accounts: Account[] }) {
    const form = useForm({ useful_life_years: String(category.useful_life_years), accumulated_account_id: (category.accumulated_account_id ?? '') as number | '', expense_account_id: (category.expense_account_id ?? '') as number | '' });

    return (
        <tr className="border-b last:border-0">
            <td className={`${cell} font-medium`}>{category.asset_account.code} — {category.asset_account.name}</td>
            <td className={cell}><Input aria-label="Umur (tahun)" type="number" min="0" max="80" className="w-24 text-right" value={form.data.useful_life_years} onChange={(e) => form.setData('useful_life_years', e.target.value)} /><p className="mt-1 text-xs text-muted-foreground">0 = tidak disusutkan</p></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['asset']} allowEmpty value={form.data.accumulated_account_id} onChange={(id) => form.setData('accumulated_account_id', id)} label="Akun akumulasi" /><Err message={form.errors.accumulated_account_id} /></td>
            <td className={cell}><AccountSelect accounts={accounts} types={['expense']} allowEmpty value={form.data.expense_account_id} onChange={(id) => form.setData('expense_account_id', id)} label="Akun beban penyusutan" /><Err message={form.errors.expense_account_id} /></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/assets/${category.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function AssetAdd({ open, onClose, accounts }: { open: boolean; onClose: () => void; accounts: Account[] }) {
    const form = useForm({ asset_account_id: '' as number | '', useful_life_years: '4', accumulated_account_id: '' as number | '', expense_account_id: '' as number | '' });

    return (
        <AddDialog title="Tambah kelompok aset" description="Aset yang disusutkan (umur lebih dari 0) wajib punya akun akumulasi dan akun beban penyusutan." open={open} onClose={onClose} processing={form.processing} canSubmit={form.data.asset_account_id !== ''}
            onSubmit={() => form.post(`${base}/assets`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } })}>
            <Field label="Akun aset" error={form.errors.asset_account_id}><AccountSelect accounts={accounts} types={['asset']} value={form.data.asset_account_id} onChange={(id) => form.setData('asset_account_id', id)} label="Akun aset" /></Field>
            <Field label="Umur (tahun), 0 = tidak disusutkan"><Input type="number" min="0" max="80" className="text-right" value={form.data.useful_life_years} onChange={(e) => form.setData('useful_life_years', e.target.value)} /></Field>
            <Field label="Akun akumulasi penyusutan" error={form.errors.accumulated_account_id}><AccountSelect accounts={accounts} types={['asset']} allowEmpty value={form.data.accumulated_account_id} onChange={(id) => form.setData('accumulated_account_id', id)} label="Akun akumulasi" /></Field>
            <Field label="Akun beban penyusutan" error={form.errors.expense_account_id}><AccountSelect accounts={accounts} types={['expense']} allowEmpty value={form.data.expense_account_id} onChange={(id) => form.setData('expense_account_id', id)} label="Akun beban penyusutan" /></Field>
        </AddDialog>
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
                    {![option.meta?.from_category, option.meta?.to_category].every((c) => c && categories.includes(c)) && <p className="mt-1 text-xs text-destructive">Kategori belum punya akun (tab 9).</p>}
                </td>
            ) : (
                <td className={cell}><AccountSelect accounts={accounts} disabled={fixedAccount} value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} /><Err message={form.errors.account_id} /></td>
            )}
            <td className={cell}><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />Aktif</label></td>
            <td className={cell}><Button size="sm" variant="outline" disabled={form.processing} onClick={() => form.patch(`${base}/options/${option.id}`, { preserveScroll: true })}>Simpan</Button></td>
        </tr>
    );
}

function OptionAdd({ group, title, open, onClose, accounts, categories }: { group: string; title: string; open: boolean; onClose: () => void; accounts: Account[]; categories: string[] }) {
    const form = useForm({ group, label: '', account_id: '' as number | '', from_category: '', to_category: '', category: '' });
    const isType = group === 'treasury_type';
    const isCategory = group === 'treasury_category';
    const ready = Boolean(form.data.label) && (isType ? Boolean(form.data.from_category && form.data.to_category) : form.data.account_id !== '') && (!isCategory || Boolean(form.data.category));

    return (
        <AddDialog title={title} description={isType ? 'Pilih kategori akun asal (Kredit) dan tujuan (Debit).' : 'Pilihan ini muncul di formulir terkait.'} open={open} onClose={onClose} processing={form.processing} canSubmit={ready}
            onSubmit={() => form.post(`${base}/options`, { preserveScroll: true, onSuccess: () => { form.reset('label', 'account_id', 'from_category', 'to_category', 'category'); onClose(); } })}>
            {isCategory && <Field label="Kode kategori (huruf kecil, mis. kas_bank)" error={form.errors.category}><Input value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} /></Field>}
            <Field label="Label" error={form.errors.label}><Input value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} /></Field>
            {isType ? (
                <div className="grid grid-cols-2 gap-4">
                    {(['from_category', 'to_category'] as const).map((f) => (
                        <Field key={f} label={f === 'from_category' ? 'Dari (Kredit)' : 'Ke (Debit)'} error={form.errors[f]}>
                            <select className={select} value={form.data[f]} onChange={(e) => form.setData(f, e.target.value)}><option value="">Pilih kategori…</option>{categories.map((c) => <option key={c} value={c}>{c}</option>)}</select>
                        </Field>
                    ))}
                </div>
            ) : (
                <Field label="Akun" error={form.errors.account_id}><AccountSelect accounts={accounts} value={form.data.account_id} onChange={(id) => form.setData('account_id', id)} /></Field>
            )}
        </AddDialog>
    );
}

export default function JournalTemplate({ groups, branches, years, products, inventoryTypes, assetCategories, options, accounts }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const initialTab = (): TabId => {
        const wanted = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;

        return (TABS.find((t) => t.id === wanted)?.id ?? 'cabang') as TabId;
    };
    const [tab, setTab] = useState<TabId>(initialTab);
    const [adding, setAdding] = useState(false);
    const active = TABS.find((t) => t.id === tab)!;
    const categories = [...new Set(options.filter((o) => o.group === 'treasury_category').map((o) => o.meta?.category ?? ''))].filter(Boolean);
    const byGroup = (group: string) => options.filter((o) => o.group === group);

    // Switching tabs only changes what is shown; no request, so no page skeleton. The tab is kept in the URL.
    const choose = (id: TabId) => {
        setTab(id);
        setAdding(false);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', id);
        window.history.replaceState(window.history.state, '', url);
    };
    const onKey = (e: KeyboardEvent<HTMLButtonElement>) => {
        const i = TABS.findIndex((t) => t.id === tab);
        const next = e.key === 'ArrowRight' ? TABS[(i + 1) % TABS.length] : e.key === 'ArrowLeft' ? TABS[(i + TABS.length - 1) % TABS.length] : null;
        if (next) {
            e.preventDefault();
            choose(next.id);
            requestAnimationFrame(() => document.getElementById(`tab-${next.id}`)?.focus());
        }
    };

    const optionTable = (group: string, head: string[]) => (
        <table className="w-full min-w-[640px] text-sm">
            <thead className="border-b"><tr>{head.map((h) => <Th key={h}>{h}</Th>)}<Th /><Th /></tr></thead>
            <tbody>
                {byGroup(group).map((o) => <OptionRow key={o.id} option={o} accounts={accounts} categories={categories} />)}
                {byGroup(group).length === 0 && <tr><td colSpan={head.length + 2} className="p-6 text-center text-sm text-muted-foreground">Belum ada pilihan. Klik Tambah, atau muat template bawaan.</td></tr>}
            </tbody>
        </table>
    );

    const empty = (rows: number) => rows === 0 ? <p className="p-6 text-center text-sm text-muted-foreground">Belum ada data. Klik Tambah, atau muat template bawaan.</p> : null;

    return (
        <>
            <Head title="Template Jurnal" />
            <main className="mx-auto w-full max-w-[1440px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">System</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Template Jurnal</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Master untuk formulir transaksi: cabang, produk, persediaan, aturan akun, dan metode pembayaran. Perubahan di sini menjadi pilihan di formulir terkait. Cabang yang punya histori tidak dihapus, hanya dinonaktifkan.</p>
                    </div>
                    <Button variant="outline" onClick={() => router.post(`${base}/defaults`, {}, { preserveScroll: true })}>Muat template bawaan</Button>
                </header>

                {(errors.branch || accounts.length === 0) && (
                    <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                        {errors.branch ?? 'Belum ada akun. Klik "Muat template bawaan" untuk mengisi COA dan template awal.'}
                    </p>
                )}

                <div role="tablist" aria-label="Bagian Template Jurnal" className="-mx-1 flex gap-1 overflow-x-auto border-b px-1">
                    {TABS.map((t) => (
                        <button key={t.id} id={`tab-${t.id}`} type="button" role="tab" aria-selected={t.id === tab} aria-controls={`panel-${t.id}`} tabIndex={t.id === tab ? 0 : -1}
                            onClick={() => choose(t.id)} onKeyDown={onKey}
                            className={`-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2 text-sm transition-colors ${t.id === tab ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>
                            {t.short}
                        </button>
                    ))}
                </div>

                <section id={`panel-${active.id}`} role="tabpanel" aria-labelledby={`tab-${active.id}`} className="rounded-xl border bg-card">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                        <div>
                            <h2 className="font-semibold">{active.title}</h2>
                            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{active.note}</p>
                        </div>
                        <Button onClick={() => setAdding(true)} disabled={accounts.length === 0 && active.id !== 'cabang' && active.id !== 'tahun'}><Plus />{active.add}</Button>
                    </div>

                    <div className="overflow-x-auto p-3">
                        {tab === 'cabang' && (
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="border-b"><tr><Th>Kode</Th><Th>Nama</Th><Th>Kota</Th><Th>Brand</Th><Th>Status</Th><Th /></tr></thead>
                                <tbody>{branches.map((b) => <BranchRow key={b.id} branch={b} />)}</tbody>
                            </table>
                        )}
                        {tab === 'cabang' && empty(branches.length)}

                        {tab === 'tahun' && (
                            <ul className="flex flex-wrap gap-2 p-2">
                                {years.map((y) => <li key={y.id}><Badge variant={y.status === 'open' ? 'secondary' : 'outline'}>{y.starts_on.slice(0, 4)} · {y.status === 'open' ? 'Terbuka' : 'Ditutup'}</Badge></li>)}
                                {years.length === 0 && <li className="text-sm text-muted-foreground">Belum ada tahun buku.</li>}
                            </ul>
                        )}

                        {tab === 'produk' && (
                            <>
                                <table className="w-full min-w-[900px] text-sm">
                                    <thead className="border-b"><tr><Th>SKU</Th><Th>Nama</Th><Th>Satuan</Th><Th className="text-right">Harga (Rp)</Th><Th>Kelompok Pendapatan</Th><Th>Status</Th><Th /></tr></thead>
                                    <tbody>{products.map((p) => <ProductRow key={p.id} product={p} accounts={accounts} />)}</tbody>
                                </table>
                                {empty(products.length)}
                            </>
                        )}

                        {tab === 'persediaan' && (
                            <>
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="border-b"><tr><Th>SKU</Th><Th>Nama</Th><Th>Satuan</Th><Th className="text-right">Stok minimum</Th><Th>Akun persediaan</Th><Th>Akun HPP</Th><Th /></tr></thead>
                                    <tbody>{inventoryTypes.map((i) => <InventoryRow key={i.id} item={i} accounts={accounts} />)}</tbody>
                                </table>
                                {empty(inventoryTypes.length)}
                            </>
                        )}

                        {tab === 'aset' && (
                            <>
                                <table className="w-full min-w-[820px] text-sm">
                                    <thead className="border-b"><tr><Th>Akun aset</Th><Th>Umur (tahun)</Th><Th>Akun akumulasi penyusutan</Th><Th>Akun beban penyusutan</Th><Th /></tr></thead>
                                    <tbody>{assetCategories.map((c) => <AssetRow key={c.id} category={c} accounts={accounts} />)}</tbody>
                                </table>
                                {empty(assetCategories.length)}
                            </>
                        )}

                        {tab === 'invoice_payment' && optionTable('invoice_payment', ['Metode', 'Akun'])}
                        {tab === 'outflow_payment' && optionTable('outflow_payment', ['Metode', 'Akun'])}
                        {tab === 'treasury_type' && optionTable('treasury_type', ['Jenis transaksi', 'Kategori akun'])}
                        {tab === 'treasury_category' && optionTable('treasury_category', ['Kategori', 'Label', 'Akun'])}
                        {tab === 'opening_account' && optionTable('opening_account', ['Label', 'Akun'])}
                    </div>
                </section>
            </main>

            {tab === 'cabang' && <BranchAdd open={adding} onClose={() => setAdding(false)} />}
            {tab === 'tahun' && <YearAdd open={adding} onClose={() => setAdding(false)} />}
            {tab === 'produk' && <ProductAdd open={adding} onClose={() => setAdding(false)} accounts={accounts} />}
            {tab === 'persediaan' && <InventoryAdd open={adding} onClose={() => setAdding(false)} accounts={accounts} />}
            {tab === 'aset' && <AssetAdd open={adding} onClose={() => setAdding(false)} accounts={accounts} />}
            {(['invoice_payment', 'outflow_payment', 'treasury_type', 'treasury_category', 'opening_account'] as const).includes(tab as never) && (
                <OptionAdd key={tab} group={tab} title={active.add} open={adding} onClose={() => setAdding(false)} accounts={accounts} categories={categories} />
            )}
            <span className="sr-only">{groups[tab] ?? ''}</span>
        </>
    );
}
