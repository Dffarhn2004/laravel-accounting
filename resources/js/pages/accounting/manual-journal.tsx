import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string };
type Props = {
    accounts: Account[];
    branches: { id: number; code: string; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    maxAttachments: number;
};

type Line = { account_id: number | ''; description: string; debit: string; credit: string };
const blank = (): Line => ({ account_id: '', description: '', debit: '', credit: '' });
const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;
const cents = (value: string) => Math.round(Number(value || 0) * 100);
const money = (centsValue: number) => (centsValue / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ManualJournal({ accounts, branches, fiscalPeriods, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `jm-${Date.now()}`);
    const form = useForm({
        branch_id: branches.length === 1 ? branches[0].id : ('' as number | ''),
        posted_on: new Date().toISOString().slice(0, 10),
        description: '',
        idempotency_key: key.current,
        lines: [blank(), blank()] as Line[],
        attachments: [] as File[],
    });

    const debit = form.data.lines.reduce((sum, line) => sum + cents(line.debit), 0);
    const credit = form.data.lines.reduce((sum, line) => sum + cents(line.credit), 0);
    const difference = debit - credit;
    const yearOpen = fiscalPeriods.some((period) => form.data.posted_on >= period.starts_on && form.data.posted_on <= period.ends_on);
    const ready = difference === 0 && debit > 0 && form.data.lines.length >= 2 && form.data.lines.length <= 6 && yearOpen;

    const setLine = (index: number, patch: Partial<Line>) => {
        form.setData('lines', form.data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!ready || !window.confirm(`Posting jurnal manual senilai ${money(debit)}?`)) return;
        form.post('/accounting/manual-journal', { forceFormData: true });
    };

    return (
        <>
            <Head title="Input Jurnal Manual" />
            <main className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Jurnal Manual</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">2 sampai 6 baris. Total debit harus sama dengan total kredit. Tahun buku diambil dari tanggal. Nomor JM diberikan saat disimpan.</p>
                    </div>
                    <Button variant="outline" asChild><Link href="/data/d-jm">EDIT DATA</Link></Button>
                </header>

                <form className="space-y-6" onSubmit={submit}>
                    <section className="grid gap-4 rounded-xl border bg-card p-4 md:grid-cols-3">
                        <label className="grid gap-1 text-sm">Cabang
                            <select className={field} value={form.data.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))} required>
                                <option value="">Pilih cabang</option>
                                {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} — {branch.name}</option>)}
                            </select>
                            <span className="text-xs text-destructive">{errors.branch_id}</span>
                        </label>
                        <label className="grid gap-1 text-sm">Tanggal
                            <Input type="date" value={form.data.posted_on} onChange={(e) => form.setData('posted_on', e.target.value)} required />
                            {!yearOpen && <span className="text-xs text-destructive">Tidak ada tahun buku terbuka untuk tanggal ini.</span>}
                            <span className="text-xs text-destructive">{errors.posted_on}</span>
                        </label>
                        <label className="grid gap-1 text-sm">Keterangan
                            <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={255} required />
                            <span className="text-xs text-destructive">{errors.description}</span>
                        </label>
                    </section>

                    <section className="space-y-3">
                        {form.data.lines.map((line, index) => (
                            <div key={index} className="grid gap-3 rounded-xl border bg-card p-4 md:grid-cols-[1.4fr_1fr_8rem_8rem_auto]">
                                <label className="grid gap-1 text-sm">Akun
                                    <select className={field} value={line.account_id} onChange={(e) => setLine(index, { account_id: Number(e.target.value) })} required>
                                        <option value="">Pilih akun</option>
                                        {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.name}</option>)}
                                    </select>
                                    <span className="text-xs text-destructive">{errors[`lines.${index}.account_id`]}</span>
                                </label>
                                <label className="grid gap-1 text-sm">Keterangan baris
                                    <Input value={line.description} onChange={(e) => setLine(index, { description: e.target.value })} />
                                </label>
                                <label className="grid gap-1 text-sm">Debit
                                    <Input inputMode="decimal" value={line.debit} onChange={(e) => setLine(index, { debit: e.target.value, credit: e.target.value ? '' : line.credit })} />
                                    <span className="text-xs text-destructive">{errors[`lines.${index}.debit`]}</span>
                                </label>
                                <label className="grid gap-1 text-sm">Kredit
                                    <Input inputMode="decimal" value={line.credit} onChange={(e) => setLine(index, { credit: e.target.value, debit: e.target.value ? '' : line.debit })} />
                                </label>
                                <Button type="button" variant="ghost" size="icon" aria-label={`Hapus baris ${index + 1}`} disabled={form.data.lines.length <= 2} onClick={() => form.setData('lines', form.data.lines.filter((_, i) => i !== index))}><Trash2 /></Button>
                            </div>
                        ))}
                        <Button type="button" variant="outline" disabled={form.data.lines.length >= 6} onClick={() => form.setData('lines', [...form.data.lines, blank()])}><Plus />Tambah baris</Button>
                        <span className="ml-3 text-xs text-destructive">{errors.lines}</span>
                    </section>

                    <section aria-label="Ringkasan" className="grid gap-3 text-sm sm:grid-cols-3">
                        <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total debit</p><p className="mt-1 font-semibold tabular-nums">{money(debit)}</p></div>
                        <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Total kredit</p><p className="mt-1 font-semibold tabular-nums">{money(credit)}</p></div>
                        <div className="rounded-lg border bg-card p-3"><p className="text-xs text-muted-foreground">Selisih</p><p className={`mt-1 font-semibold tabular-nums ${difference === 0 ? '' : 'text-destructive'}`}>{money(difference)}</p></div>
                    </section>

                    <label className="grid max-w-md gap-1 text-sm">Bukti (opsional, maks {maxAttachments})
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => {
                            const files = Array.from(e.target.files ?? []).slice(0, maxAttachments);
                            if (files.some((file) => file.size > MAX_BYTES)) return;
                            form.setData('attachments', files);
                        }} />
                        <span className="text-xs text-destructive">{errors.attachments}</span>
                    </label>

                    <Button type="submit" disabled={!ready || form.processing}>{form.processing ? 'Menyimpan…' : 'Posting jurnal'}</Button>
                </form>
            </main>
        </>
    );
}
