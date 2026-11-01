import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Account = { id: number; code: string; name: string; type: 'asset' | 'liability'; is_contra: boolean };
type Branch = { id: number; code: string; name: string };
type Period = { id: number; starts_on: string; ends_on: string };
type Opening = { id: number; branch_id: number; fiscal_period_id: number; method: string | null; posted_on: string };
type AssetClass = { asset_account_id: number; useful_life_years: number; accumulated_account_id: number | null };
type Recorded = { id: number; posted_on: string; branch?: { name: string }; lines: { id: number; description: string | null; amount: string; side: string; account?: { code: string; name: string } }[] };
type Props = { accounts: Account[]; branches: Branch[]; fiscalPeriods: Period[]; openings: Opening[]; assetClasses: AssetClass[]; recorded: Recorded[] };

type Row = { account_id: number | ''; description: string; amount: string; acquired_on: string; useful_life_years: string; accumulated: string };
const blank = (): Row => ({ account_id: '', description: '', amount: '', acquired_on: '', useful_life_years: '', accumulated: '' });
const methodLabel: Record<string, string> = { saldo_neraca: 'Saldo Neraca', modal_awal: 'Setoran Modal Awal' };

const toCents = (v: string) => (/^\d{1,18}(\.\d{1,2})?$/.test(v.trim()) ? Math.round(parseFloat(v) * 100) : 0);
const idr = (cents: number) => `Rp ${(cents / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function ModalAwal({ accounts, branches, fiscalPeriods, openings, assetClasses, recorded }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const form = useForm({
        branch_id: (branches[0]?.id ?? '') as number | '',
        fiscal_period_id: (fiscalPeriods[0]?.id ?? '') as number | '',
        posted_on: fiscalPeriods[0]?.starts_on?.slice(0, 10) ?? '',
        rows: [blank(), blank()] as Row[],
    });

    const byId = useMemo(() => new Map(accounts.map((a) => [a.id, a])), [accounts]);
    const classOf = (id: number | '') => assetClasses.find((c) => c.asset_account_id === id);
    const locked = openings.find((o) => o.branch_id === form.data.branch_id && o.fiscal_period_id === form.data.fiscal_period_id);

    const setRow = (index: number, patch: Partial<Row>) => form.setData('rows', form.data.rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

    const totals = useMemo(() => {
        let assets = 0;
        let debts = 0;
        for (const r of form.data.rows) {
            const a = r.account_id === '' ? undefined : byId.get(r.account_id);
            const cents = toCents(r.amount);
            if (!a || !cents) continue;
            if (a.type === 'asset') assets += a.is_contra ? -cents : cents;
            else debts += cents;
            assets -= classOf(r.account_id) ? Math.min(toCents(r.accumulated), cents) : 0;
        }
        return { assets, debts, capital: assets - debts };
    }, [form.data.rows, byId]); // eslint-disable-line react-hooks/exhaustive-deps

    const usable = form.data.rows.filter((r) => r.account_id !== '' && toCents(r.amount) > 0);

    const submit = () => {
        form.transform((data) => ({
            branch_id: data.branch_id,
            fiscal_period_id: data.fiscal_period_id,
            posted_on: data.posted_on,
            items: usable.map((r) => ({
                account_id: r.account_id,
                description: r.description || null,
                amount: r.amount.trim(),
                ...(classOf(r.account_id) ? { acquired_on: r.acquired_on || null, useful_life_years: r.useful_life_years ? Number(r.useful_life_years) : null, accumulated: r.accumulated.trim() || null } : {}),
            })),
        }));
        form.post('/inputan/modal', { preserveScroll: true, onSuccess: () => form.setData('rows', [blank(), blank()]) });
    };

    const err = (index: number, field: string) => (form.errors as Record<string, string>)[`items.${usable.indexOf(form.data.rows[index])}.${field}`];

    return (
        <>
            <Head title="Input Setoran Modal Awal" />
            <main className="mx-auto w-full max-w-[1200px] space-y-6 p-4 md:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Inputan Transaksi</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Input Setoran Modal Awal</h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        Untuk bisnis yang sudah berjalan: daftar harta dan hutang pada tanggal cut-off. Modal awal pemilik = total harta dikurangi total hutang. Aset tetap menyimpan tanggal perolehan, umur, dan akumulasi penyusutan untuk register aset. Jangan dipakai bersamaan dengan Saldo Neraca di cabang yang sama.
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
                    <label className="grid gap-1 text-sm">Tanggal cut-off
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

                <section aria-label="Ringkasan modal awal" className="grid gap-3 sm:grid-cols-3">
                    {[['Total Harta (neto)', totals.assets], ['Total Hutang', totals.debts], ['Modal Awal Pemilik', totals.capital]].map(([label, value]) => (
                        <div key={label as string} className="rounded-lg border bg-card p-4">
                            <p className="text-sm text-muted-foreground">{label}</p>
                            <p className="mt-1 text-lg font-semibold tabular-nums">{idr(value as number)}</p>
                        </div>
                    ))}
                </section>

                <section className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full min-w-[980px] text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr><th className="p-3">No</th><th className="p-3">Akun</th><th className="p-3">Keterangan</th><th className="p-3">Posisi</th><th className="p-3 text-right">Nilai</th><th className="p-3">Info penyusutan (aset tetap)</th><th /></tr>
                        </thead>
                        <tbody>
                            {form.data.rows.map((r, i) => {
                                const a = r.account_id === '' ? undefined : byId.get(r.account_id);
                                const cls = classOf(r.account_id);
                                return (
                                    <tr key={i} className="border-b align-top last:border-0">
                                        <td className="p-3 tabular-nums text-muted-foreground">{i + 1}</td>
                                        <td className="p-2">
                                            <select aria-label={`Akun baris ${i + 1}`} className="w-full min-w-56 rounded-md border bg-background p-2" disabled={Boolean(locked)} value={r.account_id} onChange={(e) => setRow(i, { account_id: e.target.value === '' ? '' : Number(e.target.value), useful_life_years: String(classOf(Number(e.target.value))?.useful_life_years ?? '') })}>
                                                <option value="">Pilih akun…</option>
                                                {accounts.map((x) => <option key={x.id} value={x.id}>{x.code} — {x.name}</option>)}
                                            </select>
                                            <span className="text-xs text-destructive">{err(i, 'account_id')}</span>
                                        </td>
                                        <td className="p-2"><Input aria-label={`Keterangan baris ${i + 1}`} disabled={Boolean(locked)} value={r.description} onChange={(e) => setRow(i, { description: e.target.value })} /></td>
                                        <td className="p-3">{a ? <Badge variant="outline">{a.type === 'asset' ? 'Harta' : 'Hutang'}</Badge> : '—'}</td>
                                        <td className="p-2 text-right">
                                            <Input aria-label={`Nilai baris ${i + 1}`} inputMode="decimal" className="ml-auto w-40 text-right tabular-nums" disabled={Boolean(locked)} value={r.amount} onChange={(e) => setRow(i, { amount: e.target.value })} />
                                            <span className="text-xs text-destructive">{err(i, 'amount')}</span>
                                        </td>
                                        <td className="p-2">
                                            {cls ? (
                                                <div className="grid gap-1">
                                                    <Input aria-label={`Tanggal perolehan baris ${i + 1}`} type="date" disabled={Boolean(locked)} value={r.acquired_on} onChange={(e) => setRow(i, { acquired_on: e.target.value })} />
                                                    <div className="flex gap-1">
                                                        <Input aria-label={`Umur tahun baris ${i + 1}`} type="number" min="1" max="80" className="w-20" value={r.useful_life_years} onChange={(e) => setRow(i, { useful_life_years: e.target.value })} />
                                                        <Input aria-label={`Akumulasi penyusutan baris ${i + 1}`} inputMode="decimal" placeholder="Akumulasi" className="text-right tabular-nums" value={r.accumulated} onChange={(e) => setRow(i, { accumulated: e.target.value })} />
                                                    </div>
                                                    <span className="text-xs text-destructive">{err(i, 'acquired_on') ?? err(i, 'accumulated')}</span>
                                                </div>
                                            ) : <span className="text-xs text-muted-foreground">—</span>}
                                        </td>
                                        <td className="p-2"><Button variant="ghost" size="icon" aria-label={`Hapus baris ${i + 1}`} disabled={form.data.rows.length <= 1} onClick={() => form.setData('rows', form.data.rows.filter((_, x) => x !== i))}><Trash2 /></Button></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <div className="p-3"><Button variant="outline" size="sm" disabled={Boolean(locked)} onClick={() => form.setData('rows', [...form.data.rows, blank()])}><Plus />Tambah baris</Button></div>
                </section>

                <div className="flex justify-end">
                    <Button disabled={form.processing || usable.length === 0 || Boolean(locked) || form.data.branch_id === ''} onClick={submit}>Posting modal awal</Button>
                </div>

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Modal Awal yang Sudah Tercatat</h2></div>
                    {recorded.length === 0 ? <p className="p-8 text-center text-sm text-muted-foreground">Belum ada setoran modal awal.</p> : (
                        <ul className="divide-y">
                            {recorded.map((o) => (
                                <li key={o.id} className="p-5">
                                    <p className="font-medium">{o.branch?.name} <span className="font-normal text-muted-foreground">· cut-off {o.posted_on.slice(0, 10)}</span></p>
                                    <ul className="mt-2 grid gap-1 text-sm">
                                        {o.lines.map((l) => <li key={l.id} className="flex justify-between gap-4"><span>{l.account?.code} {l.account?.name}{l.description ? ` — ${l.description}` : ''}</span><span className="tabular-nums">{l.side === 'debit' ? 'D' : 'K'} {idr(toCents(l.amount))}</span></li>)}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </main>
        </>
    );
}
