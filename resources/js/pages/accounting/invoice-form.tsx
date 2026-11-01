import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { InvoiceDocument, money } from '@/components/invoice-document';
import type { DocCompany } from '@/components/invoice-document';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string; type: string };
type Branch = { id: number; code: string; name: string };
type Period = { id: number; starts_on: string; ends_on: string };
type Product = { id: number; sku: string; name: string; unit: string; price: string; revenue_account_id: number | null };
type Method = { id: number; code: string; label: string; account_id: number | null };
type Props = {
    accounts: Account[];
    branches: Branch[];
    customers: { id: number; name: string }[];
    products: Product[];
    fiscalPeriods: Period[];
    paymentMethods: Method[];
    defaults: { receivable_account_id: number | null; vat_output_account_id: number | null; discount_account_id: number | null; vat_rate: string; pph23_rate: string };
    company: DocCompany;
    nextNumber: string;
};

type Line = { product_id: number | ''; description: string; revenue_account_id: number | ''; unit_price: string; quantity: string; discount_percent: string };
type Other = { label: string; account_id: number | ''; amount: string };

const MAX_LINES = 15;
const MAX_OTHER = 3;
const MAX_FILES = 5;
const blankLine = (revenue: number | ''): Line => ({ product_id: '', description: '', revenue_account_id: revenue, unit_price: '', quantity: '1', discount_percent: '0' });

/** Integer cents for live previews only; the server recomputes every figure with bcmath. */
const cents = (v: string) => (/^\d{1,18}(\.\d{1,4})?$/.test(v.trim()) ? Math.round(parseFloat(v) * 100) : 0);
const fromCents = (c: number) => (c / 100).toFixed(2);
const field = 'rounded-md border bg-background p-2 text-sm';

export default function InvoiceForm({ accounts, branches, customers, products, fiscalPeriods, paymentMethods, defaults, company, nextNumber }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const incomeAccounts = accounts.filter((a) => a.type === 'income');
    const firstIncome = (incomeAccounts[0]?.id ?? '') as number | '';
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `inv-${Date.now()}`);
    const today = new Date().toISOString().slice(0, 10);
    const periodFor = (date: string) => fiscalPeriods.find((p) => date >= p.starts_on.slice(0, 10) && date <= p.ends_on.slice(0, 10))?.id ?? fiscalPeriods[0]?.id ?? '';

    const initial = () => ({
        branch_id: (branches[0]?.id ?? '') as number | '',
        fiscal_period_id: periodFor(today) as number | '',
        customer_id: '' as number | '',
        issued_on: today,
        price_mode: 'master' as 'master' | 'manual',
        payment_method: paymentMethods[0]?.code ?? '',
        paid_amount: '0',
        vat_enabled: Number(defaults.vat_rate) > 0,
        vat_rate: String(Number(defaults.vat_rate)),
        pph23_rate: String(Number(defaults.pph23_rate)),
        lines: [blankLine(firstIncome)] as Line[],
        other_incomes: [] as Other[],
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const [preview, setPreview] = useState(false);
    const d = form.data;

    const method = paymentMethods.find((m) => m.code === d.payment_method);
    const onCredit = !method || method.account_id === defaults.receivable_account_id; // "Belum dibayar" keeps everything as receivable

    const calc = useMemo(() => {
        let discount = 0;
        let net = 0;
        const rows = d.lines.map((l) => {
            const gross = Math.round(parseFloat(l.quantity || '0') * cents(l.unit_price)) || 0;
            const pct = /^\d{1,3}(\.\d{1,2})?$/.test(l.discount_percent) ? Math.round(parseFloat(l.discount_percent) * 100) : 0;
            const disc = Math.floor((gross * pct) / 10000);
            discount += disc;
            net += gross - disc;
            return { gross, total: gross - disc };
        });
        const other = d.other_incomes.reduce((s, o) => s + cents(o.amount), 0);
        const dpp = net + other;
        const rate = d.vat_enabled && /^\d{1,3}(\.\d{1,2})?$/.test(d.vat_rate) ? Math.round(parseFloat(d.vat_rate) * 100) : 0;
        const vat = Math.floor((dpp * rate) / 10000);
        const pphRate = /^\d{1,3}(\.\d{1,2})?$/.test(d.pph23_rate) ? Math.round(parseFloat(d.pph23_rate) * 100) : 0;
        const total = dpp + vat;
        const paid = onCredit ? 0 : Math.min(cents(d.paid_amount), total);

        return { rows, discount, other, dpp, vat, pph23: Math.floor((dpp * pphRate) / 10000), total, paid, remaining: total - paid };
    }, [d.lines, d.other_incomes, d.vat_enabled, d.vat_rate, d.pph23_rate, d.paid_amount, onCredit]);

    const setLine = (i: number, patch: Partial<Line>) => form.setData('lines', d.lines.map((l, x) => (x === i ? { ...l, ...patch } : l)));
    const setOther = (i: number, patch: Partial<Other>) => form.setData('other_incomes', d.other_incomes.map((o, x) => (x === i ? { ...o, ...patch } : o)));

    const pickProduct = (i: number, id: number | '') => {
        const p = products.find((x) => x.id === id);
        setLine(i, p ? { product_id: p.id, description: p.name, unit_price: String(Number(p.price)), revenue_account_id: p.revenue_account_id ?? d.lines[i].revenue_account_id } : { product_id: '' });
    };

    const customer = customers.find((c) => c.id === d.customer_id);
    const e = (k: string) => errors[k];
    const canSubmit = d.customer_id !== '' && d.lines.every((l) => l.description.trim() && cents(l.unit_price) >= 0 && l.revenue_account_id !== '') && calc.total > 0 && !form.processing;

    const submit = () => {
        if (!window.confirm('Posting invoice ini? Setelah diposting tidak bisa diubah langsung; koreksi dilakukan lewat jurnal pembalikan.')) return;
        form.transform((data) => ({
            branch_id: data.branch_id,
            fiscal_period_id: data.fiscal_period_id,
            customer_id: data.customer_id,
            issued_on: data.issued_on,
            receivable_account_id: defaults.receivable_account_id,
            discount_account_id: defaults.discount_account_id,
            idempotency_key: key.current,
            vat_enabled: data.vat_enabled,
            vat_rate: data.vat_enabled ? data.vat_rate : null,
            vat_output_account_id: data.vat_enabled ? defaults.vat_output_account_id : null,
            pph23_rate: data.pph23_rate,
            payment_method: data.payment_method || null,
            paid_amount: fromCents(calc.paid),
            payment_account_id: calc.paid > 0 ? method?.account_id : null,
            lines: data.lines.map((l) => ({ product_id: l.product_id || null, description: l.description, revenue_account_id: l.revenue_account_id, unit_price: l.unit_price, quantity: l.quantity, discount_percent: l.discount_percent || '0' })),
            other_incomes: data.other_incomes.filter((o) => cents(o.amount) > 0).map((o) => ({ label: o.label || 'Pendapatan lain', account_id: o.account_id, amount: o.amount })),
            attachments: data.attachments,
        }));
        form.post('/accounting/invoices', { forceFormData: true, preserveScroll: true });
    };

    const docLines = d.lines.map((l, i) => ({ description: l.description, quantity: l.quantity, unit_price: fromCents(cents(l.unit_price)), discount_percent: l.discount_percent, line_total: fromCents(calc.rows[i].total) }));

    return (
        <>
            <Head title="Input Invoice" />
            <main className="mx-auto w-full max-w-[1400px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Invoice</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Mencatat penjualan, pendapatan, pajak, kas, dan piutang. Nomor invoice dibuat otomatis. Sisa yang belum dibayar menjadi piutang.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/transactions/invoices">EDIT DATA</Link></Button>
                </header>

                {e('invoice') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{e('invoice')}</p>}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-6">
                        <section className="grid gap-3 rounded-xl border bg-card p-5 sm:grid-cols-2 lg:grid-cols-3">
                            <label className="grid gap-1 text-sm">Cabang
                                <select className={field} value={d.branch_id} onChange={(x) => form.setData('branch_id', Number(x.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                                <span className="text-xs text-destructive">{e('branch_id')}</span>
                            </label>
                            <label className="grid gap-1 text-sm">Tanggal
                                <Input type="date" value={d.issued_on} onChange={(x) => form.setData((s) => ({ ...s, issued_on: x.target.value, fiscal_period_id: periodFor(x.target.value) }))} />
                                <span className="text-xs text-destructive">{e('issued_on') ?? e('fiscal_period_id')}</span>
                            </label>
                            <div className="grid gap-1 text-sm">No. Invoice
                                <p className="rounded-md border bg-muted/40 p-2 tabular-nums">{nextNumber} <span className="text-xs text-muted-foreground">(otomatis)</span></p>
                            </div>
                            <label className="grid gap-1 text-sm sm:col-span-2">Nama customer
                                <select className={field} value={d.customer_id} onChange={(x) => form.setData('customer_id', x.target.value === '' ? '' : Number(x.target.value))}>
                                    <option value="">Pilih customer…</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                <span className="text-xs text-destructive">{e('customer_id')}{customers.length === 0 && 'Belum ada customer (System → Master).'}</span>
                            </label>
                            <label className="grid gap-1 text-sm">Jenis pembayaran
                                <select className={field} value={d.payment_method} onChange={(x) => form.setData('payment_method', x.target.value)}>{paymentMethods.map((m) => <option key={m.id} value={m.code}>{m.label}</option>)}</select>
                                {paymentMethods.length === 0 && <span className="text-xs text-destructive">Muat template bawaan di Template Jurnal.</span>}
                            </label>
                            <fieldset className="grid gap-1 text-sm sm:col-span-2 lg:col-span-3">
                                <legend>Mode harga</legend>
                                <div className="flex gap-4">
                                    {(['master', 'manual'] as const).map((m) => (
                                        <label key={m} className="flex items-center gap-2"><input type="radio" name="price_mode" checked={d.price_mode === m} onChange={() => form.setData('price_mode', m)} />{m === 'master' ? 'Harga master (Template Jurnal)' : 'Harga manual'}</label>
                                    ))}
                                </div>
                            </fieldset>
                        </section>

                        <section className="rounded-xl border bg-card">
                            <div className="flex items-center justify-between border-b px-5 py-4"><h2 className="font-semibold">Produk <span className="font-normal text-muted-foreground">({d.lines.length}/{MAX_LINES})</span></h2>
                                <Button size="sm" variant="outline" disabled={d.lines.length >= MAX_LINES} onClick={() => form.setData('lines', [...d.lines, blankLine(firstIncome)])}><Plus />Tambah baris</Button></div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground"><tr><th className="p-2">Produk</th><th className="p-2">Kelompok Pendapatan</th><th className="p-2 text-right">Harga</th><th className="p-2 text-right">Qty</th><th className="p-2 text-right">Disc %</th><th className="p-2 text-right">Total</th><th /></tr></thead>
                                    <tbody>
                                        {d.lines.map((l, i) => (
                                            <tr key={i} className="border-b align-top last:border-0">
                                                <td className="p-2">
                                                    {d.price_mode === 'master' ? (
                                                        <select aria-label={`Produk baris ${i + 1}`} className={`${field} w-full min-w-48`} value={l.product_id} onChange={(x) => pickProduct(i, x.target.value === '' ? '' : Number(x.target.value))}>
                                                            <option value="">Pilih produk…</option>{products.map((p) => <option key={p.id} value={p.id}>{p.sku} — {p.name}</option>)}
                                                        </select>
                                                    ) : (
                                                        <Input aria-label={`Deskripsi baris ${i + 1}`} placeholder="Nama produk / jasa" value={l.description} onChange={(x) => setLine(i, { description: x.target.value, product_id: '' })} />
                                                    )}
                                                    <span className="text-xs text-destructive">{e(`lines.${i}.description`) ?? e(`lines.${i}.product_id`)}</span>
                                                </td>
                                                <td className="p-2"><select aria-label={`Kelompok pendapatan baris ${i + 1}`} className={`${field} w-full min-w-44`} value={l.revenue_account_id} onChange={(x) => setLine(i, { revenue_account_id: Number(x.target.value) })}>{incomeAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}</select><span className="text-xs text-destructive">{e(`lines.${i}.revenue_account_id`)}</span></td>
                                                <td className="p-2"><Input aria-label={`Harga baris ${i + 1}`} inputMode="decimal" className="w-32 text-right tabular-nums" readOnly={d.price_mode === 'master'} value={l.unit_price} onChange={(x) => setLine(i, { unit_price: x.target.value })} /><span className="text-xs text-destructive">{e(`lines.${i}.unit_price`)}</span></td>
                                                <td className="p-2"><Input aria-label={`Qty baris ${i + 1}`} inputMode="decimal" className="w-20 text-right tabular-nums" value={l.quantity} onChange={(x) => setLine(i, { quantity: x.target.value })} /><span className="text-xs text-destructive">{e(`lines.${i}.quantity`)}</span></td>
                                                <td className="p-2"><Input aria-label={`Diskon baris ${i + 1}`} inputMode="decimal" className="w-20 text-right tabular-nums" value={l.discount_percent} onChange={(x) => setLine(i, { discount_percent: x.target.value })} /><span className="text-xs text-destructive">{e(`lines.${i}.discount_percent`)}</span></td>
                                                <td className="p-3 text-right tabular-nums">{money(calc.rows[i].total / 100)}</td>
                                                <td className="p-2"><Button variant="ghost" size="icon" aria-label={`Hapus baris ${i + 1}`} disabled={d.lines.length <= 1} onClick={() => form.setData('lines', d.lines.filter((_, x) => x !== i))}><Trash2 /></Button></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {e('lines') && <p className="px-5 pb-3 text-xs text-destructive">{e('lines')}</p>}
                        </section>

                        <section className="rounded-xl border bg-card">
                            <div className="flex items-center justify-between border-b px-5 py-4"><h2 className="font-semibold">Pendapatan lain <span className="font-normal text-muted-foreground">({d.other_incomes.length}/{MAX_OTHER})</span></h2>
                                <Button size="sm" variant="outline" disabled={d.other_incomes.length >= MAX_OTHER} onClick={() => form.setData('other_incomes', [...d.other_incomes, { label: '', account_id: firstIncome, amount: '' }])}><Plus />Tambah</Button></div>
                            {d.other_incomes.length === 0 ? <p className="p-5 text-sm text-muted-foreground">Mis. ongkos kirim atau biaya layanan yang ditagihkan.</p> : (
                                <div className="grid gap-2 p-3">{d.other_incomes.map((o, i) => (
                                    <div key={i} className="grid gap-2 sm:grid-cols-[1fr_16rem_10rem_auto]">
                                        <Input aria-label={`Keterangan pendapatan lain ${i + 1}`} placeholder="Keterangan" value={o.label} onChange={(x) => setOther(i, { label: x.target.value })} />
                                        <select aria-label={`Akun pendapatan lain ${i + 1}`} className={field} value={o.account_id} onChange={(x) => setOther(i, { account_id: Number(x.target.value) })}>{incomeAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}</select>
                                        <Input aria-label={`Jumlah pendapatan lain ${i + 1}`} inputMode="decimal" className="text-right tabular-nums" placeholder="0" value={o.amount} onChange={(x) => setOther(i, { amount: x.target.value })} />
                                        <Button variant="ghost" size="icon" aria-label={`Hapus pendapatan lain ${i + 1}`} onClick={() => form.setData('other_incomes', d.other_incomes.filter((_, x) => x !== i))}><Trash2 /></Button>
                                    </div>))}
                                </div>)}
                            {e('other_incomes') && <p className="px-5 pb-3 text-xs text-destructive">{e('other_incomes')}</p>}
                        </section>

                        <section className="grid gap-3 rounded-xl border bg-card p-5 sm:grid-cols-2">
                            <div className="grid gap-2 text-sm">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={d.vat_enabled} onChange={(x) => form.setData('vat_enabled', x.target.checked)} />Kenakan PPN (dari DPP)</label>
                                {d.vat_enabled && <label className="grid gap-1">Tarif PPN (%)<Input inputMode="decimal" className="w-28 text-right" value={d.vat_rate} onChange={(x) => form.setData('vat_rate', x.target.value)} /><span className="text-xs text-destructive">{e('vat_rate')}</span></label>}
                                {d.vat_enabled && !defaults.vat_output_account_id && <p className="text-xs text-destructive">Akun 2301 PPN Keluaran belum ada.</p>}
                            </div>
                            <label className="grid gap-1 text-sm">PPh 23 (%), estimasi dari DPP
                                <Input inputMode="decimal" className="w-28 text-right" value={d.pph23_rate} onChange={(x) => form.setData('pph23_rate', x.target.value)} />
                                <span className="text-xs text-muted-foreground">Tidak mengurangi tagihan. Dibukukan sebagai kredit pajak saat pelanggan memotong pembayaran dengan bukti potong.</span>
                            </label>
                        </section>

                        <section className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">Bukti <span className="font-normal text-muted-foreground">(maks {MAX_FILES} file JPG/PNG/WebP/PDF, 5 MB)</span></h2>
                            <input aria-label="Unggah bukti" className="mt-3 block w-full text-sm" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(x) => form.setData('attachments', Array.from(x.target.files ?? []).slice(0, MAX_FILES))} />
                            <ul className="mt-2 text-sm text-muted-foreground">{d.attachments.map((f, i) => <li key={i}>{f.name}</li>)}</ul>
                            <p className="text-xs text-destructive">{e('attachments') ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1]}</p>
                        </section>

                        {preview && (
                            <section aria-label="Pratinjau invoice">
                                <InvoiceDocument company={company} number={nextNumber} issuedOn={d.issued_on} customer={customer?.name ?? ''} lines={docLines}
                                    otherIncomes={d.other_incomes.filter((o) => cents(o.amount) > 0).map((o) => ({ label: o.label || 'Pendapatan lain', amount: fromCents(cents(o.amount)) }))}
                                    totals={{ dpp: calc.dpp / 100, discount: calc.discount / 100, vat: calc.vat / 100, vatRate: d.vat_rate, total: calc.total / 100, paid: calc.paid / 100, remaining: calc.remaining / 100 }} />
                            </section>
                        )}
                    </div>

                    <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
                        <section aria-label="Ringkasan" className="rounded-xl border bg-card p-5">
                            <h2 className="font-semibold">Ringkasan</h2>
                            <dl className="mt-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
                                <dt className="text-muted-foreground">Diskon</dt><dd className="text-right tabular-nums">{money(calc.discount / 100)}</dd>
                                <dt className="text-muted-foreground">Pendapatan lain</dt><dd className="text-right tabular-nums">{money(calc.other / 100)}</dd>
                                <dt>DPP</dt><dd className="text-right tabular-nums">{money(calc.dpp / 100)}</dd>
                                <dt>PPN {d.vat_enabled ? `${Number(d.vat_rate) || 0}%` : ''}</dt><dd className="text-right tabular-nums">{money(calc.vat / 100)}</dd>
                                <dt className="text-muted-foreground">Est. PPh 23</dt><dd className="text-right tabular-nums text-muted-foreground">{money(calc.pph23 / 100)}</dd>
                                <dt className="border-t pt-2 font-semibold">Total tagihan</dt><dd className="border-t pt-2 text-right font-semibold tabular-nums">{money(calc.total / 100)}</dd>
                            </dl>
                            <label className="mt-4 grid gap-1 text-sm">Total dibayar
                                <Input aria-label="Total dibayar" inputMode="decimal" className="text-right tabular-nums" disabled={onCredit} value={onCredit ? '0' : d.paid_amount} onChange={(x) => form.setData('paid_amount', x.target.value)} />
                                {!onCredit && <button type="button" className="justify-self-end text-xs underline" onClick={() => form.setData('paid_amount', fromCents(calc.total))}>Lunas</button>}
                                <span className="text-xs text-destructive">{e('paid_amount') ?? e('payment_account_id')}</span>
                            </label>
                            <p className="mt-3 flex items-center justify-between text-sm"><span>Sisa piutang</span><span className="font-semibold tabular-nums">{money(calc.remaining / 100)}</span></p>
                            <p className="mt-1 text-right">{calc.total === 0 ? <Badge variant="outline">Belum diisi</Badge> : calc.remaining === 0 ? <Badge>Lunas</Badge> : calc.paid > 0 ? <Badge variant="secondary">Sebagian</Badge> : <Badge variant="outline">Piutang</Badge>}</p>
                        </section>
                        <div className="grid gap-2">
                            <Button disabled={!canSubmit} onClick={submit}>Posting invoice</Button>
                            <Button variant="outline" onClick={() => setPreview((v) => !v)}><Eye />{preview ? 'Sembunyikan pratinjau' : 'Pratinjau'}</Button>
                            <Button variant="ghost" onClick={() => { if (window.confirm('Kosongkan seluruh isian?')) form.setData(initial()); }}>Reset</Button>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}
