import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    accounts: { id: number; code: string; name: string }[];
    branches: { id: number; code: string; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    paymentMethods: { id: number; code: string; label: string; settles: 'now' | 'debt' | null }[];
    maxAttachments: number;
};

const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;

export default function ExpenseForm({ accounts, branches, fiscalPeriods, paymentMethods, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `exp-${Date.now()}`);
    const initial = () => ({
        branch_id: (branches[0]?.id ?? '') as number | '',
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: paymentMethods[0]?.code ?? '',
        expense_account_id: '' as number | '',
        description: '',
        amount: '',
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const d = form.data;
    const method = paymentMethods.find((m) => m.code === d.payment_method);
    const inPeriod = fiscalPeriods.some((p) => d.expense_date >= p.starts_on.slice(0, 10) && d.expense_date <= p.ends_on.slice(0, 10));
    const amountOk = /^\d{1,15}(\.\d{1,2})?$/.test(d.amount) && Number(d.amount) > 0;
    const tooBig = d.attachments.some((f) => f.size > MAX_BYTES);
    const canSubmit = d.branch_id !== '' && d.expense_account_id !== '' && d.description.trim() !== '' && amountOk && d.payment_method !== '' && inPeriod && !tooBig && !form.processing;
    const err = (k: string) => errors[k];

    const submit = () => {
        form.transform((data) => ({ ...data, idempotency_key: key.current }));
        form.post('/accounting/expenses', { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Input Beban" />
            <main className="mx-auto w-full max-w-[900px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Beban</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">Mencatat pengeluaran operasional, dibayar sekarang atau masih berutang. Jurnal: debit akun beban, kredit kas/bank atau hutang. Kategori mengikuti Master Akun (awalan 5).</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/transactions/expenses">EDIT DATA</Link></Button>
                </header>

                {err('expense') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('expense')}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={field} value={d.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                        <span className="text-xs text-destructive">{err('branch_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal
                        <Input type="date" value={d.expense_date} onChange={(e) => form.setData('expense_date', e.target.value)} />
                        <span className="text-xs text-destructive">{err('expense_date') ?? (inPeriod ? '' : 'Tidak ada tahun buku terbuka untuk tanggal ini.')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis pembayaran
                        <select className={field} value={d.payment_method} onChange={(e) => form.setData('payment_method', e.target.value)}>{paymentMethods.map((m) => <option key={m.id} value={m.code}>{m.label}</option>)}</select>
                        <span className="text-xs text-muted-foreground">{method?.settles === 'debt' ? 'Dicatat sebagai hutang usaha; belum mengurangi kas.' : method?.settles === 'now' ? 'Mengurangi saldo kas/bank saat ini.' : ''}</span>
                        <span className="text-xs text-destructive">{err('payment_method') ?? (paymentMethods.length === 0 ? 'Muat template bawaan di Template Jurnal.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Akun beban
                        <select className={field} value={d.expense_account_id} onChange={(e) => form.setData('expense_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih akun beban…</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('expense_account_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Keterangan
                        <Input value={d.description} maxLength={255} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. bayar listrik Maret" />
                        <span className="text-xs text-destructive">{err('description')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Total (Rp)
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.amount} onChange={(e) => form.setData('amount', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{err('amount') ?? (d.amount !== '' && !amountOk ? 'Angka lebih dari 0, maksimal 2 desimal.' : '')}</span>
                    </label>
                    <div className="grid content-end gap-1 text-sm">
                        <span className="text-muted-foreground">Status</span>
                        <p>{method?.settles === 'debt' ? <Badge variant="secondary">Hutang (terbuka)</Badge> : <Badge>Lunas</Badge>}</p>
                    </div>
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Bukti <span className="font-normal text-muted-foreground">(maks {maxAttachments} file JPG/PNG/WebP/PDF, 5 MB)</span></h2>
                    <input aria-label="Unggah bukti" className="mt-3 block w-full text-sm" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, maxAttachments))} />
                    <ul className="mt-2 text-sm text-muted-foreground">{d.attachments.map((f, i) => <li key={i}>{f.name}</li>)}</ul>
                    <p className="text-xs text-destructive">{tooBig ? 'Ada file lebih dari 5 MB.' : (err('attachments') ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1])}</p>
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => { if (window.confirm('Kosongkan seluruh isian?')) form.setData(initial()); }}>Reset</Button>
                    <Button disabled={!canSubmit} onClick={() => { if (window.confirm('Posting beban ini? Setelah diposting hanya bisa dikoreksi dengan pembalikan.')) submit(); }}>Posting beban</Button>
                </div>
            </main>
        </>
    );
}
