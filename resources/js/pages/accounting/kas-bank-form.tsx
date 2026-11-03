import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string; debit_normal: boolean; categories: string[] };
type Balance = { branch_id: number; account_id: number; balance: string };
type Type = { code: string; label: string; from_category: string; to_category: string };
type Props = {
    types: Type[];
    accounts: Account[];
    balances: Balance[];
    branches: { id: number; code: string; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    maxAttachments: number;
};

const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;
const money = (value: string | number) => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const cents = (value: string) => Math.round(Number(value) * 100);

export default function KasBankForm({ types, accounts, balances, branches, fiscalPeriods, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `kas-${Date.now()}`);
    const initial = () => ({
        branch_id: (branches[0]?.id ?? '') as number | '',
        posted_on: new Date().toISOString().slice(0, 10),
        transaction_type: types[0]?.code ?? '',
        from_account_id: '' as number | '',
        to_account_id: '' as number | '',
        from_amount: '',
        to_amount: '',
        description: '',
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const d = form.data;
    const type = types.find((t) => t.code === d.transaction_type);
    const inCategory = (category: string) => accounts.filter((a) => a.categories.includes(category));
    const fromAccounts = type ? inCategory(type.from_category) : [];
    const toAccounts = type ? inCategory(type.to_category).filter((a) => a.id !== Number(d.from_account_id)) : [];
    const inPeriod = fiscalPeriods.some((p) => d.posted_on >= p.starts_on.slice(0, 10) && d.posted_on <= p.ends_on.slice(0, 10));
    const amountOk = /^\d{1,15}(\.\d{1,2})?$/.test(d.from_amount) && Number(d.from_amount) > 0;
    const sameAmount = d.from_amount !== '' && d.from_amount === d.to_amount;
    const tooBig = d.attachments.some((f) => f.size > MAX_BYTES);
    const canSubmit = d.branch_id !== '' && d.from_account_id !== '' && d.to_account_id !== '' && d.description.trim() !== '' && amountOk && sameAmount && d.transaction_type !== '' && inPeriod && !tooBig && !form.processing;
    const err = (k: string) => errors[k];

    const branchId = Number(d.branch_id);
    const cashIds = new Set(accounts.filter((a) => a.categories.includes('kas_bank')).map((a) => a.id));
    const cashRows = balances.filter((b) => b.branch_id === branchId && cashIds.has(b.account_id));
    const cashTotal = cashRows.reduce((sum, row) => sum + cents(row.balance), 0);
    const from = accounts.find((a) => a.id === Number(d.from_account_id));
    const fromBalance = balances.find((b) => b.branch_id === branchId && b.account_id === from?.id);
    const after = from && fromBalance && amountOk ? (from.debit_normal ? cents(fromBalance.balance) - cents(d.from_amount) : cents(fromBalance.balance) + cents(d.from_amount)) : null;

    const submit = () => {
        form.transform((data) => ({ ...data, idempotency_key: key.current }));
        form.post('/transactions/cash-bank', { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Input Pindah Saldo Kas/Bank" />
            <main className="mx-auto w-full max-w-[1100px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Pindah Saldo Kas/Bank</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">Mencatat transfer serta transaksi kas, bank, hutang, pajak, dan modal. Jurnal: debit akun Ke, kredit akun Dari, dengan nilai yang sama. Akun dibatasi oleh jenis transaksi di Template Jurnal.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/data/d-kasbank">EDIT DATA</Link></Button>
                </header>

                {err('cashbank') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('cashbank')}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={field} value={d.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                        <span className="text-xs text-destructive">{err('branch_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal
                        <Input type="date" value={d.posted_on} onChange={(e) => form.setData('posted_on', e.target.value)} />
                        <span className="text-xs text-destructive">{err('posted_on') ?? (inPeriod ? '' : 'Tidak ada tahun buku terbuka untuk tanggal ini.')}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Jenis transaksi
                        <select className={field} value={d.transaction_type} onChange={(e) => form.setData({ ...d, transaction_type: e.target.value, from_account_id: '', to_account_id: '' })}>
                            {types.map((t) => <option key={t.code} value={t.code}>{t.label}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('transaction_type') ?? (types.length === 0 ? 'Muat template bawaan di Template Jurnal.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Dari akun (kredit)
                        <select className={field} value={d.from_account_id} onChange={(e) => form.setData('from_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih akun…</option>{fromAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('from_account_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Ke akun (debit)
                        <select className={field} value={d.to_account_id} onChange={(e) => form.setData('to_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih akun…</option>{toAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('to_account_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Nilai Dari (Rp)
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.from_amount} onChange={(e) => form.setData('from_amount', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{err('from_amount')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Nilai Ke (Rp)
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.to_amount} onChange={(e) => form.setData('to_amount', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{err('to_amount') ?? (d.to_amount !== '' && !sameAmount ? 'Nilai Dari dan Nilai Ke harus sama.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Keterangan
                        <Input value={d.description} maxLength={255} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. transfer kas ke bank" />
                        <span className="text-xs text-destructive">{err('description')}</span>
                    </label>
                    {from && fromBalance && (
                        <p className="text-sm text-muted-foreground sm:col-span-2">Saldo {from.code} saat ini {money(fromBalance.balance)}{after !== null ? ` · sisa setelah transaksi ${money(after / 100)}` : ''}.</p>
                    )}
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Saldo kas & bank</h2>
                    <table className="mt-3 w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground"><tr><th className="py-2">Kode</th><th>Nama</th><th className="text-right">Saldo</th></tr></thead>
                        <tbody>
                            {cashRows.map((row) => {
                                const account = accounts.find((a) => a.id === row.account_id);
                                return <tr key={row.account_id} className="border-t"><td className="py-2 tabular-nums text-muted-foreground">{account?.code}</td><td>{account?.name}</td><td className="text-right tabular-nums">{money(row.balance)}</td></tr>;
                            })}
                        </tbody>
                        <tfoot><tr className="border-t font-semibold"><td className="py-2" colSpan={2}>Total</td><td className="text-right tabular-nums">{money(cashTotal / 100)}</td></tr></tfoot>
                    </table>
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <h2 className="font-semibold">Bukti <span className="font-normal text-muted-foreground">(maks {maxAttachments} file JPG/PNG/WebP/PDF, 5 MB)</span></h2>
                    <input aria-label="Unggah bukti" className="mt-3 block w-full text-sm" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, maxAttachments))} />
                    <ul className="mt-2 text-sm text-muted-foreground">{d.attachments.map((f, i) => <li key={i}>{f.name}</li>)}</ul>
                    <p className="text-xs text-destructive">{tooBig ? 'Ada file lebih dari 5 MB.' : (err('attachments') ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1])}</p>
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => { if (window.confirm('Kosongkan seluruh isian?')) form.setData(initial()); }}>Reset</Button>
                    <Button disabled={!canSubmit} onClick={() => { if (window.confirm('Posting transaksi kas/bank ini? Setelah diposting hanya bisa dikoreksi dengan pembalikan.')) submit(); }}>Posting</Button>
                </div>
            </main>
        </>
    );
}
