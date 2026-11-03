import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    items: { id: number; sku: string; name: string; unit: string }[];
    branches: { id: number; code: string; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    paymentMethods: { id: number; code: string; label: string; settles: 'now' | 'debt' | null }[];
    nextNumber: string;
    maxAttachments: number;
};

const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;
const money = (v: string) => (/^\d+(\.\d{1,2})?$/.test(v) ? v : '0');

export default function PersediaanForm({ items, branches, fiscalPeriods, paymentMethods, nextNumber, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `prs-${Date.now()}`);
    const initial = () => ({
        branch_id: (branches[0]?.id ?? '') as number | '',
        moved_on: new Date().toISOString().slice(0, 10),
        kind: 'purchase' as 'purchase' | 'issue',
        payment_method: paymentMethods[0]?.code ?? '',
        inventory_item_id: '' as number | '',
        description: '',
        quantity: '',
        unit_cost: '',
        freight: '',
        tax: '',
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const d = form.data;
    const issue = d.kind === 'issue';
    const inPeriod = fiscalPeriods.some((p) => d.moved_on >= p.starts_on.slice(0, 10) && d.moved_on <= p.ends_on.slice(0, 10));
    const qtyOk = /^\d{1,12}(\.\d{1,4})?$/.test(d.quantity) && Number(d.quantity) > 0;
    const priceOk = issue || (/^\d{1,15}(\.\d{1,2})?$/.test(d.unit_cost) && Number(d.unit_cost) >= 0);
    const extraOk = (v: string) => v === '' || /^\d{1,15}(\.\d{1,2})?$/.test(v);
    const tooBig = d.attachments.some((f) => f.size > MAX_BYTES);
    const total = issue ? null : (Number(money(d.quantity)) * Number(money(d.unit_cost)) + Number(money(d.freight)) + Number(money(d.tax))).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const canSubmit = d.branch_id !== '' && d.inventory_item_id !== '' && d.description.trim() !== '' && qtyOk && priceOk && extraOk(d.freight) && extraOk(d.tax) && (issue || d.payment_method !== '') && inPeriod && !tooBig && !form.processing;
    const err = (k: string) => errors[k];

    const submit = () => {
        form.transform((data) => ({ ...data, idempotency_key: key.current, payment_method: data.kind === 'issue' ? '' : data.payment_method }));
        form.post('/inputan/persediaan', { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Input Persediaan" />
            <main className="mx-auto w-full max-w-[900px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Persediaan</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">Pembelian menambah stok pada harga beli ditambah ongkir dan pajak. Pemakaian memakai harga rata-rata cabang dan mencatat HPP. Nomor berikutnya {nextNumber}.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href="/data/d-persediaan">EDIT DATA</Link></Button>
                        <Button variant="outline" asChild><Link href="/accounting/inventory">Laporan Persediaan</Link></Button>
                    </div>
                </header>

                {err('inventory') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('inventory')}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={field} value={d.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                        <span className="text-xs text-destructive">{err('branch_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal
                        <Input type="date" value={d.moved_on} onChange={(e) => form.setData('moved_on', e.target.value)} />
                        <span className="text-xs text-destructive">{err('moved_on') ?? (inPeriod ? '' : 'Tidak ada tahun buku terbuka untuk tanggal ini.')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis transaksi
                        <select className={field} value={issue ? 'issue' : d.payment_method} onChange={(e) => {
                            if (e.target.value === 'issue') form.setData({ ...d, kind: 'issue', payment_method: '' });
                            else form.setData({ ...d, kind: 'purchase', payment_method: e.target.value });
                        }}>
                            {paymentMethods.map((m) => <option key={m.id} value={m.code}>{m.label}</option>)}
                            <option value="issue">Pemakaian</option>
                        </select>
                        <span className="text-xs text-muted-foreground">{issue ? 'Mengurangi stok dan membebankan HPP. Harga mengikuti rata-rata cabang.' : 'Menambah stok. Ongkir dan pajak masuk nilai persediaan.'}</span>
                        <span className="text-xs text-destructive">{err('payment_method')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis persediaan
                        <select className={field} value={d.inventory_item_id} onChange={(e) => form.setData('inventory_item_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih jenis…</option>{items.map((item) => <option key={item.id} value={item.id}>{item.sku} — {item.name} ({item.unit})</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('inventory_item_id') ?? (items.length === 0 ? 'Tambah jenis di Template Jurnal bagian 4.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Keterangan
                        <Input value={d.description} maxLength={255} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. beli biji kopi" />
                        <span className="text-xs text-destructive">{err('description')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Quantity
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.quantity} onChange={(e) => form.setData('quantity', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{err('quantity')}</span>
                    </label>
                    {!issue && (
                        <>
                            <label className="grid gap-1 text-sm">Harga satuan
                                <Input inputMode="decimal" className="text-right tabular-nums" value={d.unit_cost} onChange={(e) => form.setData('unit_cost', e.target.value)} placeholder="0" />
                                <span className="text-xs text-destructive">{err('unit_cost')}</span>
                            </label>
                            <label className="grid gap-1 text-sm">Ongkir
                                <Input inputMode="decimal" className="text-right tabular-nums" value={d.freight} onChange={(e) => form.setData('freight', e.target.value)} placeholder="0" />
                                <span className="text-xs text-destructive">{err('freight')}</span>
                            </label>
                            <label className="grid gap-1 text-sm">Pajak
                                <Input inputMode="decimal" className="text-right tabular-nums" value={d.tax} onChange={(e) => form.setData('tax', e.target.value)} placeholder="0" />
                                <span className="text-xs text-destructive">{err('tax')}</span>
                            </label>
                            <div className="grid content-end gap-1 text-sm sm:col-span-2">
                                <span className="text-muted-foreground">Total harga</span>
                                <p className="text-lg font-semibold tabular-nums">{total}</p>
                            </div>
                        </>
                    )}
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Bukti <span className="font-normal text-muted-foreground">(maks {maxAttachments} file JPG/PNG/WebP/PDF, 5 MB)</span></h2>
                    <input aria-label="Unggah bukti" className="mt-3 block w-full text-sm" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, maxAttachments))} />
                    <ul className="mt-2 text-sm text-muted-foreground">{d.attachments.map((f, i) => <li key={i}>{f.name}</li>)}</ul>
                    <p className="text-xs text-destructive">{tooBig ? 'Ada file lebih dari 5 MB.' : (err('attachments') ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1])}</p>
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => { if (window.confirm('Kosongkan seluruh isian?')) form.setData(initial()); }}>Reset</Button>
                    <Button disabled={!canSubmit} onClick={() => { if (window.confirm('Posting persediaan ini? Setelah diposting hanya bisa dikoreksi dengan pembalikan.')) submit(); }}>Posting persediaan</Button>
                </div>
            </main>
        </>
    );
}
