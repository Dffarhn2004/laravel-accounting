import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Editing = { id: number; branch_id: number; code: string; activity: string; unit: string | null; rate: string; qty: Record<string, string> } & { account_id?: number };
type Props = {
    year: number;
    accounts: { id: number; code: string; name: string; type: string }[];
    branches: { id: number; code: string; name: string }[];
    summary: { branch: string; lines: number; income: string; expense: string; profit: string }[];
    editing: Editing | null;
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const num = (v: number | string) => Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const field = 'rounded-md border bg-background p-2 text-sm';

export default function Budget({ year, accounts, branches, summary, editing }: Props) {
    const editAccount = editing ? accounts.find((a) => a.code === editing.code) : undefined;
    const form = useForm({
        branch_id: editing ? String(editing.branch_id) : String(branches[0]?.id ?? ''),
        fiscal_year: String(year),
        account_id: editAccount ? String(editAccount.id) : String(accounts[0]?.id ?? ''),
        activity: editing?.activity ?? '',
        unit: editing?.unit ?? '',
        rate: editing ? editing.rate : '0',
        quantities: Array.from({ length: 12 }, (_, i) => (editing ? editing.qty[String(i + 1)] : '0')),
    });
    const [quick, setQuick] = useState('');
    const rate = Number(form.data.rate) || 0;
    const total = useMemo(() => form.data.quantities.reduce((c, q) => c + (Number(q) || 0) * rate, 0), [form.data.quantities, rate]);
    const totalQty = form.data.quantities.reduce((c, q) => c + (Number(q) || 0), 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) form.patch(`/accounting/budgets/${editing.id}`);
        else form.post('/accounting/budgets');
    };
    const err = (k: string) => (form.errors as Record<string, string | undefined>)[k];

    return (
        <>
            <Head title="Input Anggaran" />
            <main className="mx-auto w-full max-w-[1600px] space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Anggaran</p>
                        <h1 className="text-3xl font-semibold tracking-tight">{editing ? 'Ubah Anggaran' : 'Input Anggaran'}</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">Satu baris = cabang + akun Laba Rugi + kegiatan. Nilai tiap bulan = Qty × Tarif. Hanya akun pendapatan dan beban (kode 4 dan 5).</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={`/anggaran/ang-data?year=${year}`}>Data Anggaran</Link></Button>
                        <Button variant="outline" asChild><Link href={`/anggaran/ang-rekap?year=${year}`}>Rekap Anggaran</Link></Button>
                    </div>
                </header>

                <form onSubmit={submit} className="space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                        <label className="grid gap-1 text-sm">Tahun<Input type="number" min={2000} max={2100} value={form.data.fiscal_year} onChange={(e) => form.setData('fiscal_year', e.target.value)} />{err('fiscal_year') && <span className="text-xs text-destructive">{err('fiscal_year')}</span>}</label>
                        <label className="grid gap-1 text-sm">Cabang
                            <select className={field} value={form.data.branch_id} onChange={(e) => form.setData('branch_id', e.target.value)}>{branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}</select>
                            {err('branch_id') && <span className="text-xs text-destructive">{err('branch_id')}</span>}
                        </label>
                        <label className="grid gap-1 text-sm md:col-span-2">Akun Laba Rugi
                            <select className={field} value={form.data.account_id} onChange={(e) => form.setData('account_id', e.target.value)}>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}</select>
                            {err('account_id') && <span className="text-xs text-destructive">{err('account_id')}</span>}
                        </label>
                        <label className="grid gap-1 text-sm md:col-span-2">Rincian kegiatan<Input value={form.data.activity} maxLength={255} onChange={(e) => form.setData('activity', e.target.value)} />{err('activity') && <span className="text-xs text-destructive">{err('activity')}</span>}</label>
                        <label className="grid gap-1 text-sm">Satuan<Input value={form.data.unit} maxLength={30} placeholder="mis. unit, jam" onChange={(e) => form.setData('unit', e.target.value)} /></label>
                        <label className="grid gap-1 text-sm">Tarif (Rp)<Input type="number" min="0" step="0.01" value={form.data.rate} onChange={(e) => form.setData('rate', e.target.value)} />{err('rate') && <span className="text-xs text-destructive">{err('rate')}</span>}</label>
                        <label className="grid gap-1 text-sm">Isi cepat Qty
                            <span className="flex gap-2">
                                <Input type="number" min="0" step="0.01" value={quick} onChange={(e) => setQuick(e.target.value)} />
                                <Button type="button" variant="outline" onClick={() => form.setData('quantities', Array.from({ length: 12 }, () => quick || '0'))}>Isi</Button>
                            </span>
                        </label>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead className="text-xs text-muted-foreground"><tr><th className="p-2 text-left" />{MONTHS.map((m) => <th key={m} className="p-2 text-right">{m}</th>)}<th className="p-2 text-right">TOTAL</th></tr></thead>
                            <tbody>
                                <tr>
                                    <th scope="row" className="p-2 text-left text-xs font-medium text-muted-foreground">Qty</th>
                                    {form.data.quantities.map((q, i) => (
                                        <td key={i} className="p-1">
                                            <Input aria-label={`Qty ${MONTHS[i]}`} type="number" min="0" step="0.01" className="text-right tabular-nums" value={q} onChange={(e) => form.setData('quantities', form.data.quantities.map((c, j) => (j === i ? e.target.value : c)))} />
                                        </td>
                                    ))}
                                    <td className="p-2 text-right tabular-nums">{num(totalQty)}</td>
                                </tr>
                                <tr>
                                    <th scope="row" className="p-2 text-left text-xs font-medium text-muted-foreground">Rp</th>
                                    {form.data.quantities.map((q, i) => <td key={i} className="p-2 text-right tabular-nums">{num((Number(q) || 0) * rate)}</td>)}
                                    <td className="p-2 text-right font-semibold tabular-nums">{num(total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    {Object.keys(form.errors).some((k) => k.startsWith('quantities')) && <p className="text-sm text-destructive">Qty tiap bulan harus berupa angka nol atau lebih.</p>}

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>{editing ? 'Simpan perubahan' : 'Simpan anggaran'}</Button>
                        {editing && <Button type="button" variant="outline" onClick={() => router.get('/accounting/budgets')}>Batal ubah</Button>}
                    </div>
                </form>

                <section aria-labelledby="ringkasan" className="space-y-2">
                    <h2 id="ringkasan" className="text-lg font-semibold">Ringkasan anggaran tahun {year}</h2>
                    <div className="overflow-x-auto rounded-xl border bg-card">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground"><tr><th className="p-3">Cabang</th><th className="p-3 text-right">Jumlah baris</th><th className="p-3 text-right">Pendapatan</th><th className="p-3 text-right">Beban</th><th className="p-3 text-right">Laba</th></tr></thead>
                            <tbody>
                                {summary.length ? summary.map((s) => (
                                    <tr key={s.branch} className="border-b last:border-0"><td className="p-3">{s.branch}</td><td className="p-3 text-right tabular-nums">{s.lines}</td><td className="p-3 text-right tabular-nums">{num(s.income)}</td><td className="p-3 text-right tabular-nums">{num(s.expense)}</td><td className="p-3 text-right font-semibold tabular-nums">{num(s.profit)}</td></tr>
                                )) : <tr><td colSpan={5} className="p-8 text-center text-muted-foreground">Belum ada anggaran untuk tahun ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </>
    );
}
