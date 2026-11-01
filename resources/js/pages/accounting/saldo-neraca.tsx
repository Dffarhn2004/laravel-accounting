import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string; type: 'asset' | 'liability' | 'equity'; is_contra: boolean };
type Branch = { id: number; code: string; name: string };
type Period = { id: number; starts_on: string; ends_on: string };
type Opening = { id: number; branch_id: number; fiscal_period_id: number; method: string | null; posted_on: string; branch?: { name: string } };
type Props = { accounts: Account[]; branches: Branch[]; fiscalPeriods: Period[]; openings: Opening[] };

const groups: [Account['type'], string][] = [['asset', 'Aset'], ['liability', 'Kewajiban'], ['equity', 'Ekuitas']];
const methodLabel: Record<string, string> = { saldo_neraca: 'Saldo Neraca', modal_awal: 'Setoran Modal Awal' };

/** Cents as an integer so previews never accumulate float error. The server recomputes with bcmath. */
const toCents = (v: string) => (/^\d{1,18}(\.\d{1,2})?$/.test(v.trim()) ? Math.round(parseFloat(v) * 100) : 0);
const idr = (cents: number) => `Rp ${(cents / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function SaldoNeraca({ accounts, branches, fiscalPeriods, openings }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const form = useForm({
        branch_id: (branches[0]?.id ?? '') as number | '',
        fiscal_period_id: (fiscalPeriods[0]?.id ?? '') as number | '',
        posted_on: fiscalPeriods[0]?.starts_on?.slice(0, 10) ?? '',
        amounts: {} as Record<number, string>,
    });

    const locked = openings.find((o) => o.branch_id === form.data.branch_id && o.fiscal_period_id === form.data.fiscal_period_id);

    const totals = useMemo(() => {
        const t = { asset: 0, liability: 0, equity: 0 };
        for (const a of accounts) {
            const cents = toCents(form.data.amounts[a.id] ?? '');
            t[a.type] += a.is_contra ? -cents : cents;
        }
        return { ...t, difference: t.asset - (t.liability + t.equity) };
    }, [accounts, form.data.amounts]);

    const filled = accounts.filter((a) => toCents(form.data.amounts[a.id] ?? '') > 0);
    const balanced = filled.length >= 2 && totals.difference === 0;
    let no = 0;

    const submit = () => {
        form.transform((data) => ({
            branch_id: data.branch_id,
            fiscal_period_id: data.fiscal_period_id,
            posted_on: data.posted_on,
            items: filled.map((a) => ({ account_id: a.id, amount: data.amounts[a.id].trim() })),
        }));
        form.post('/inputan/saldo', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Input Saldo Neraca Akhir Periode" />
            <main className="mx-auto w-full max-w-[1200px] space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Input Saldo Neraca Akhir Periode</h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        Isi saldo setiap akun neraca sebagai angka positif. Akun pengurang (mis. akumulasi penyusutan, prive) otomatis dihitung negatif. Saldo awal hanya diposting sekali dan tidak boleh dipakai bersamaan dengan Setoran Modal Awal di cabang yang sama.
                    </p>
                </header>

                <section className="grid gap-3 rounded-xl border bg-card p-5 sm:grid-cols-3">
                    <label className="grid gap-1 text-sm">Cabang
                        <select className="rounded-md border bg-background p-2" value={form.data.branch_id} onChange={(e) => form.setData('branch_id', Number(e.target.value))}>
                            {branches.map((b) => <option key={b.id} value={b.id}>{b.code} — {b.name}</option>)}
                        </select>
                        <span className="text-xs text-destructive">{form.errors.branch_id ?? errors.branch_id}</span>
                    </label>
                    <label className="grid gap-1 text-sm">Tahun buku
                        <select
                            className="rounded-md border bg-background p-2"
                            value={form.data.fiscal_period_id}
                            onChange={(e) => {
                                const period = fiscalPeriods.find((p) => p.id === Number(e.target.value));
                                form.setData((d) => ({ ...d, fiscal_period_id: Number(e.target.value), posted_on: period?.starts_on.slice(0, 10) ?? d.posted_on }));
                            }}
                        >
                            {fiscalPeriods.map((p) => <option key={p.id} value={p.id}>{p.starts_on.slice(0, 10)} s/d {p.ends_on.slice(0, 10)}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">Tanggal saldo awal
                        <Input type="date" value={form.data.posted_on} onChange={(e) => form.setData('posted_on', e.target.value)} />
                        <span className="text-xs text-destructive">{form.errors.posted_on}</span>
                    </label>
                </section>

                {locked && (
                    <p role="status" className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        Cabang ini sudah memiliki saldo awal ({methodLabel[locked.method ?? ''] ?? 'jurnal saldo awal'}, {locked.posted_on.slice(0, 10)}) untuk tahun buku ini. Form dikunci; koreksi lewat jurnal pembalikan.
                    </p>
                )}
                {errors.items && <p role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">{errors.items}</p>}

                <section aria-label="Ringkasan neraca" className="grid gap-3 sm:grid-cols-5">
                    {[
                        ['Total Aset', totals.asset],
                        ['Total Kewajiban', totals.liability],
                        ['Ekuitas', totals.equity],
                        ['Selisih', totals.difference],
                    ].map(([label, value]) => (
                        <div key={label as string} className="rounded-lg border bg-card p-4">
                            <p className="text-sm text-muted-foreground">{label}</p>
                            <p className="mt-1 text-lg font-semibold tabular-nums">{idr(value as number)}</p>
                        </div>
                    ))}
                    <div className="rounded-lg border bg-card p-4">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <p className="mt-2">{filled.length < 2 ? <Badge variant="outline">Belum diisi</Badge> : balanced ? <Badge>Seimbang</Badge> : <Badge variant="destructive">Tidak seimbang</Badge>}</p>
                    </div>
                </section>

                <section className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="p-3">No</th><th className="p-3">Kode Akun</th><th className="p-3">Nama Akun</th><th className="p-3">Posisi</th>
                                <th className="p-3 text-right">Saldo Awal</th><th className="p-3 text-right">Nilai ke Neraca</th>
                            </tr>
                        </thead>
                        {groups.map(([type, title]) => (
                            <tbody key={type}>
                                <tr className="bg-muted/20"><th colSpan={6} scope="colgroup" className="p-2 text-left text-xs font-semibold uppercase tracking-wide">{title}</th></tr>
                                {accounts.filter((a) => a.type === type).map((a) => {
                                    const cents = toCents(form.data.amounts[a.id] ?? '');
                                    return (
                                        <tr key={a.id} className="border-b last:border-0">
                                            <td className="p-3 tabular-nums text-muted-foreground">{++no}</td>
                                            <td className="p-3 font-medium tabular-nums">{a.code}</td>
                                            <td className="p-3">{a.name}</td>
                                            <td className="p-3"><Badge variant="outline">{a.is_contra ? 'Minus (−)' : 'Plus (+)'}</Badge></td>
                                            <td className="p-2 text-right">
                                                <Input
                                                    aria-label={`Saldo ${a.code} ${a.name}`}
                                                    inputMode="decimal"
                                                    className="ml-auto w-44 text-right tabular-nums"
                                                    disabled={Boolean(locked)}
                                                    placeholder="0"
                                                    value={form.data.amounts[a.id] ?? ''}
                                                    onChange={(e) => form.setData('amounts', { ...form.data.amounts, [a.id]: e.target.value })}
                                                />
                                                <span className="text-xs text-destructive">{(form.errors as Record<string, string>)[`items.${filled.findIndex((f) => f.id === a.id)}.amount`]}</span>
                                            </td>
                                            <td className="p-3 text-right tabular-nums">{cents ? idr(a.is_contra ? -cents : cents) : '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        ))}
                    </table>
                    {accounts.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">Belum ada akun neraca. Muat template bawaan di System → Template Jurnal.</p>}
                </section>

                <div className="flex justify-end gap-3">
                    <Button disabled={form.processing || !balanced || Boolean(locked) || form.data.branch_id === ''} onClick={submit}>Posting saldo awal</Button>
                </div>
            </main>
        </>
    );
}
