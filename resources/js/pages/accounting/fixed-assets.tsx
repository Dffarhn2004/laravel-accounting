import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Category = { asset_account_id: number; code: string | null; name: string | null; useful_life_years: number };
type Props = {
    branches: { id: number; code: string; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
    paymentMethods: { id: number; code: string; label: string; settles: 'now' | 'debt' | null }[];
    categories: Category[];
    nextNumber: string;
    maxAttachments: number;
};

const field = 'rounded-md border bg-background p-2 text-sm';
const MAX_BYTES = 5 * 1024 * 1024;

export default function FixedAssets({ branches, fiscalPeriods, paymentMethods, categories, nextNumber, maxAttachments }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const key = useRef(typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `ast-${Date.now()}`);
    const initial = () => ({
        branch_id: (branches[0]?.id ?? '') as number | '',
        acquired_on: new Date().toISOString().slice(0, 10),
        payment_method: paymentMethods[0]?.code ?? '',
        asset_account_id: '' as number | '',
        description: '',
        cost: '',
        attachments: [] as File[],
    });
    const form = useForm(initial());
    const d = form.data;
    const category = categories.find((c) => c.asset_account_id === d.asset_account_id);
    const inPeriod = fiscalPeriods.some((p) => d.acquired_on >= p.starts_on.slice(0, 10) && d.acquired_on <= p.ends_on.slice(0, 10));
    const costOk = /^\d{1,15}(\.\d{1,2})?$/.test(d.cost) && Number(d.cost) > 0;
    const tooBig = d.attachments.some((f) => f.size > MAX_BYTES);
    const months = (category?.useful_life_years ?? 0) * 12;
    const monthly = costOk && months > 0 ? (Math.floor(Math.round(Number(d.cost) * 100) / months) / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : null;
    const canSubmit = d.branch_id !== '' && d.asset_account_id !== '' && d.payment_method !== '' && d.description.trim() !== '' && costOk && inPeriod && !tooBig && !form.processing;
    const err = (k: string) => errors[k];

    const submit = () => {
        form.transform((data) => ({ ...data, idempotency_key: key.current }));
        form.post('/transactions/fixed-assets', { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Input Pembelian Asset" />
            <main className="mx-auto w-full max-w-[900px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                        <h1 className="text-3xl font-semibold tracking-tight">Input Pembelian Asset</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">Penyusutan garis lurus mulai bulan setelah perolehan. Umur dan akun penyusutan diambil dari Template Jurnal. Nomor berikutnya {nextNumber}.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild><Link href="/data/d-asset">Data Asset</Link></Button>
                        <Button variant="outline" asChild><Link href="/data/simulasi">Simulasi D&A</Link></Button>
                        <Button variant="outline" asChild><Link href="/laporan/lap-asset">Laporan</Link></Button>
                    </div>
                </header>

                {err('asset') && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{err('asset')}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm">Cabang
                        <select className={field} value={d.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                        <span className="text-xs text-destructive">{err('branch_id')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal
                        <Input type="date" value={d.acquired_on} onChange={(e) => form.setData('acquired_on', e.target.value)} />
                        <span className="text-xs text-destructive">{err('acquired_on') ?? (inPeriod ? '' : 'Tidak ada tahun buku terbuka untuk tanggal ini.')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Jenis pembayaran
                        <select className={field} value={d.payment_method} onChange={(e) => form.setData('payment_method', e.target.value)}>
                            {paymentMethods.map((m) => <option key={m.id} value={m.code}>{m.label}</option>)}
                        </select>
                        <span className="text-xs text-muted-foreground">{paymentMethods.find((m) => m.code === d.payment_method)?.settles === 'debt' ? 'Masuk buku hutang sampai dilunasi.' : 'Langsung mengurangi kas atau bank.'}</span>
                        <span className="text-xs text-destructive">{err('payment_method')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Akun asset
                        <select className={field} value={d.asset_account_id} onChange={(e) => form.setData('asset_account_id', e.target.value === '' ? '' : Number(e.target.value))}>
                            <option value="">Pilih kelompok…</option>
                            {categories.map((c) => <option key={c.asset_account_id} value={c.asset_account_id}>{c.code} — {c.name} ({c.useful_life_years > 0 ? `${c.useful_life_years} thn` : 'tidak disusutkan'})</option>)}
                        </select>
                        <span className="text-xs text-destructive">{err('asset_account_id') ?? (categories.length === 0 ? 'Atur kelompok di Template Jurnal bagian 5.' : '')}</span>
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">Keterangan
                        <Input value={d.description} maxLength={255} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. laptop kasir" />
                        <span className="text-xs text-destructive">{err('description')}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Total harga
                        <Input inputMode="decimal" className="text-right tabular-nums" value={d.cost} onChange={(e) => form.setData('cost', e.target.value)} placeholder="0" />
                        <span className="text-xs text-destructive">{err('cost')}</span>
                    </label>
                    <div className="grid content-end gap-1 text-sm">
                        <span className="text-muted-foreground">Penyusutan per bulan</span>
                        <p className="text-lg font-semibold tabular-nums">{category?.useful_life_years === 0 ? 'Tidak disusutkan' : (monthly ?? '—')}</p>
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
                    <Button disabled={!canSubmit} onClick={() => { if (window.confirm('Posting pembelian aset ini? Setelah diposting hanya bisa dikoreksi dengan pembalikan.')) submit(); }}>Posting aset</Button>
                </div>
            </main>
        </>
    );
}
