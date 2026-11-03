import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { RegisterTable } from '@/components/register-table';
import type { RegisterColumn } from '@/components/register-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Invoice = {
    id: number;
    number: string;
    issued_on: string;
    customer_id: number;
    customer: string | null;
    branch: string | null;
    receivable_code: string | null;
    receivable_name: string | null;
    total: string;
    remaining: string;
    pph23_left: string;
};
type Method = { code: string; label: string; account: string };
type Props = {
    invoices: Invoice[];
    methods: Method[];
    summary: { customers: number; invoices: number; outstanding: string };
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    maxAttachments: number;
};

const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;
const money = (value: string | number) => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const cents = (value: string) => Math.round(Number(value || 0) * 100);

export default function ReceivableForm({ invoices, methods, summary, fiscalPeriods, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `plt-${Date.now()}`);
    const initial = () => ({
        invoice_id: '' as number | '',
        received_on: new Date().toISOString().slice(0, 10),
        payment_method: methods[0]?.code ?? '',
        amount: '',
        pph23_amount: '',
        description: '',
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const d = form.data;
    const invoice = invoices.find((row) => row.id === Number(d.invoice_id));
    const siblings = invoice ? invoices.filter((row) => row.customer_id === invoice.customer_id) : [];
    const inPeriod = fiscalPeriods.some((p) => d.received_on >= p.starts_on.slice(0, 10) && d.received_on <= p.ends_on.slice(0, 10));
    const dateOk = Boolean(invoice) && d.received_on >= (invoice?.issued_on ?? '');
    const amountOk = /^\d{1,15}(\.\d{1,2})?$/.test(d.amount) && Number(d.amount) > 0;
    const pphOk = d.pph23_amount === '' || /^\d{1,15}(\.\d{1,2})?$/.test(d.pph23_amount);
    const withheld = d.pph23_amount === '' ? 0 : cents(d.pph23_amount);
    const gross = amountOk ? cents(d.amount) + withheld : 0;
    const withinRemaining = invoice !== undefined && gross > 0 && gross <= cents(invoice.remaining);
    const withinPph = invoice !== undefined && withheld <= cents(invoice.pph23_left);
    const tooBig = d.attachments.some((f) => f.size > MAX_BYTES);
    const canSubmit = Boolean(invoice) && d.payment_method !== '' && d.description.trim() !== '' && dateOk && inPeriod && amountOk && pphOk && withinRemaining && withinPph && d.attachments.length <= maxAttachments && !tooBig && !form.processing;
    const err = (name: string) => errors[name];

    const submit = () => {
        form.transform((data) => ({ ...data, idempotency_key: key.current, pph23_amount: data.pph23_amount === '' ? '0' : data.pph23_amount }));
        form.post('/transactions/receivables', { forceFormData: true, preserveScroll: true });
    };

    const cols: RegisterColumn<Invoice>[] = [
        { header: 'No Invoice', cell: (row) => row.number },
        { header: 'Tanggal Invoice', cell: (row) => <span className="tabular-nums">{row.issued_on}</span> },
        { header: 'Cabang', cell: (row) => row.branch },
        { header: 'Akun Piutang', cell: (row) => `${row.receivable_code ?? ''} ${row.receivable_name ?? ''}` },
        { numeric: true, header: 'Total', cell: (row) => money(row.total) },
        { numeric: true, header: 'Sisa', cell: (row) => money(row.remaining) },
    ];

    return (
        <>
            <Head title="Input Pelunasan Piutang" />
            <main className="mx-auto w-full max-w-[1100px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Pelunasan Piutang</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">Melunasi satu invoice yang masih terbuka. Cabang dan akun piutang mengikuti invoice. Jurnal: debit kas/bank, kredit piutang. PPh 23 dibukukan hanya bila pelanggan memotong.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/data/d-piutang">EDIT DATA</Link></Button>
                </header>

                {err('amount') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('amount')}</p>}

                <section className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Customer berpiutang</p><p className="mt-1 text-xl font-semibold tabular-nums">{summary.customers}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Invoice terbuka</p><p className="mt-1 text-xl font-semibold tabular-nums">{summary.invoices}</p></div>
                    <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Sisa piutang</p><p className="mt-1 text-xl font-semibold tabular-nums">{money(summary.outstanding)}</p></div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm sm:col-span-2">Invoice
                        <select className={field} value={d.invoice_id} onChange={(e) => form.setData('invoice_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih invoice terbuka…</option>
                            {invoices.map((row) => <option key={row.id} value={row.id}>{row.number} — {row.customer} — sisa {money(row.remaining)}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('invoice_id') ?? (invoices.length === 0 ? 'Tidak ada invoice terbuka.' : '')}</span>
                    </label>
                    <p className="text-sm text-muted-foreground">Customer<span className="mt-1 block text-foreground">{invoice?.customer ?? '—'}</span></p>
                    <p className="text-sm text-muted-foreground">Cabang<span className="mt-1 block text-foreground">{invoice?.branch ?? '—'}</span></p>
                    <p className="text-sm text-muted-foreground">Tanggal invoice<span className="mt-1 block tabular-nums text-foreground">{invoice?.issued_on ?? '—'}</span></p>
                    <p className="text-sm text-muted-foreground">Akun piutang<span className="mt-1 block text-foreground">{invoice ? `${invoice.receivable_code} ${invoice.receivable_name}` : '—'}</span></p>
                    <p className="text-sm text-muted-foreground">Sisa piutang<span className="mt-1 block tabular-nums text-foreground">{invoice ? money(invoice.remaining) : '—'}</span></p>
                    <p className="text-sm text-muted-foreground">Estimasi PPh 23 belum dibukukan<span className="mt-1 block tabular-nums text-foreground">{invoice ? money(invoice.pph23_left) : '—'}</span></p>
                    <label className="grid gap-1 text-sm">Tanggal bayar
                        <Input type="date" value={d.received_on} onChange={(e) => form.setData('received_on', e.target.value)} />
                        <span className="text-xs text-destructive">{err('received_on') ?? (!dateOk && invoice ? 'Tanggal bayar tidak boleh sebelum tanggal invoice.' : (!inPeriod && d.received_on ? 'Tidak ada tahun buku terbuka untuk tanggal ini.' : ''))}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis pembayaran
                        <select className={field} value={d.payment_method} onChange={(e) => form.setData('payment_method', e.target.value)}>
                            <option value="">Pilih kas/bank…</option>
                            {methods.map((method) => <option key={method.code} value={method.code}>{method.label} — {method.account}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('payment_method') ?? (methods.length === 0 ? 'Muat template bawaan di Template Jurnal.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jumlah diterima (Rp)
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.amount} onChange={(e) => form.setData('amount', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{amountOk && invoice && !withinRemaining ? 'Pembayaran melebihi sisa piutang.' : ''}</span>
                    </label>
                    <label className="grid gap-1 text-sm">PPh 23 dipotong (opsional)
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.pph23_amount} onChange={(e) => form.setData('pph23_amount', e.target.value)} placeholder="0" />
                        <span className="text-xs text-muted-foreground">Kosong berarti tidak ada kredit pajak. Bruto = jumlah diterima + potongan.</span>
                        <span className="text-xs text-destructive">{pphOk && invoice && !withinPph ? 'PPh23 dipotong melebihi estimasi yang belum dibukukan.' : ''}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Keterangan
                        <Input value={d.description} maxLength={255} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. pelunasan invoice Maret" />
                        <span className="text-xs text-destructive">{err('description')}</span>
                    </label>
                    {invoice && amountOk && withinRemaining && (
                        <p className="text-sm text-muted-foreground sm:col-span-2">Diterima {money(d.amount)}{withheld > 0 ? ` + PPh 23 ${money(withheld / 100)}` : ''}. Piutang berkurang {money(gross / 100)}. Sisa setelah bayar {money((cents(invoice.remaining) - gross) / 100)}.</p>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold">Invoice terbuka customer ini</h2>
                    <RegisterTable label="Invoice terbuka customer yang sama" rows={siblings} columns={cols} empty={invoice ? 'Tidak ada invoice terbuka lain.' : 'Pilih invoice untuk melihat piutang customer yang sama.'} />
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Bukti <span className="font-normal text-muted-foreground">(maks {maxAttachments} file JPG/PNG/WebP/PDF, 5 MB)</span></h2>
                    <input aria-label="Unggah bukti" className="mt-3 block w-full text-sm" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, maxAttachments))} />
                    <ul className="mt-2 text-sm text-muted-foreground">{d.attachments.map((f, i) => <li key={i}>{f.name}</li>)}</ul>
                    <p className="text-xs text-destructive">{tooBig ? 'Ada file lebih dari 5 MB.' : (err('attachments') ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1])}</p>
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => { if (window.confirm('Kosongkan seluruh isian?')) form.setData(initial()); }}>Reset</Button>
                    <Button disabled={!canSubmit} onClick={() => { if (window.confirm('Posting pelunasan piutang ini? Setelah diposting hanya bisa dikoreksi dengan pembalikan.')) submit(); }}>Posting</Button>
                </div>
            </main>
        </>
    );
}
