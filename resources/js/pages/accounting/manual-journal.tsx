import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    accounts: { id: number; code: string; name: string }[];
    branches: { id: number; name: string }[];
    fiscalPeriods: { id: number; starts_on: string; ends_on: string }[];
};

export default function ManualJournal({ accounts, branches, fiscalPeriods }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        branch_id: branches[0]?.id ?? '',
        fiscal_period_id: fiscalPeriods[0]?.id ?? '',
        posted_on: new Date().toISOString().slice(0, 10),
        description: '',
        idempotency_key: `manual-${Date.now()}`,
        lines: [
            { account_id: accounts[0]?.id ?? 0, debit: '', credit: '' },
            { account_id: accounts[1]?.id ?? 0, debit: '', credit: '' },
        ],
    });
    const debitTotal = data.lines.reduce((total, line) => total + (Number(line.debit) || 0), 0);
    const creditTotal = data.lines.reduce((total, line) => total + (Number(line.credit) || 0), 0);
    const difference = debitTotal - creditTotal;
    const isBalanced = data.lines.length >= 2 && debitTotal > 0 && difference === 0;

    return (
        <>
            <Head title="Jurnal Manual" />
            <main className="mx-auto max-w-6xl space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div><h1 className="text-3xl font-semibold tracking-tight">Jurnal Manual</h1><p className="mt-1 text-sm text-muted-foreground">Buat jurnal seimbang untuk transaksi yang tidak berasal dari modul operasional.</p></div>
                    <Link href="/accounting/journals" className="text-sm font-medium text-muted-foreground hover:text-foreground">Lihat jurnal umum</Link>
                </header>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/accounting/manual-journal');
                    }}
                    className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]"
                >
                    <div className="space-y-5 rounded-xl border bg-card p-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <select value={data.branch_id} onChange={(event) => setData('branch_id', Number(event.target.value))} className="rounded-md border bg-background p-2" aria-label="Cabang">
                            {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                        </select>
                        <select value={data.fiscal_period_id} onChange={(event) => setData('fiscal_period_id', Number(event.target.value))} className="rounded-md border bg-background p-2" aria-label="Periode fiskal">
                            {fiscalPeriods.map((period) => <option key={period.id} value={period.id}>{period.starts_on} — {period.ends_on}</option>)}
                        </select>
                        <input type="date" value={data.posted_on} onChange={(event) => setData('posted_on', event.target.value)} className="rounded-md border bg-background p-2" aria-label="Tanggal jurnal" />
                    </div>
                    <input
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Deskripsi jurnal"
                        className="w-full rounded-md border bg-background p-2"
                    />
                    <div className="overflow-x-auto rounded-lg border">
                        <div className="grid min-w-[640px] grid-cols-[minmax(15rem,1fr)_8rem_8rem_2.5rem] gap-2 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground"><span>Akun</span><span className="text-right">Debit</span><span className="text-right">Kredit</span><span /></div>
                    {data.lines.map((line, index) => (
                        <div key={index} className="grid min-w-[640px] grid-cols-[minmax(15rem,1fr)_8rem_8rem_2.5rem] gap-2 border-b px-3 py-2 last:border-0">
                            <select
                                value={line.account_id}
                                onChange={(event) => {
                                    const lines = [...data.lines];
                                    lines[index].account_id = Number(
                                        event.target.value,
                                    );
                                    setData('lines', lines);
                                }}
                                className="rounded-md border bg-background p-2"
                            >
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} — {account.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                value={line.debit}
                                onChange={(event) => {
                                    const lines = [...data.lines];
                                    lines[index].debit = event.target.value;
                                    setData('lines', lines);
                                }}
                                placeholder="Debit"
                                className="rounded-md border bg-background p-2 text-right tabular-nums"
                            />
                            <input
                                value={line.credit}
                                onChange={(event) => {
                                    const lines = [...data.lines];
                                    lines[index].credit = event.target.value;
                                    setData('lines', lines);
                                }}
                                placeholder="Kredit"
                                className="rounded-md border bg-background p-2 text-right tabular-nums"
                            />
                            <Button type="button" variant="ghost" size="icon" disabled={data.lines.length <= 2} onClick={() => setData('lines', data.lines.filter((_, lineIndex) => lineIndex !== index))} aria-label="Hapus baris"><Trash2 className="size-4" /></Button>
                        </div>
                    ))}
                    </div>
                    <Button type="button" variant="outline" onClick={() => setData('lines', [...data.lines, { account_id: accounts[0]?.id ?? 0, debit: '', credit: '' }])}><Plus className="size-4" />Tambah baris</Button>
                    {errors.lines && (
                        <p className="text-sm text-red-600">{errors.lines}</p>
                    )}
                    </div>
                    <aside className="h-fit space-y-4 rounded-xl border bg-card p-5 lg:sticky lg:top-4">
                        <div><p className="text-sm font-medium">Ringkasan jurnal</p><p className="mt-1 text-xs text-muted-foreground">Posting hanya tersedia saat debit dan kredit seimbang.</p></div>
                        <dl className="space-y-3 text-sm"><div className="flex justify-between"><dt className="text-muted-foreground">Total debit</dt><dd className="font-medium tabular-nums">{debitTotal.toLocaleString('id-ID')}</dd></div><div className="flex justify-between"><dt className="text-muted-foreground">Total kredit</dt><dd className="font-medium tabular-nums">{creditTotal.toLocaleString('id-ID')}</dd></div><div className="flex justify-between border-t pt-3"><dt className="font-medium">Selisih</dt><dd className={difference === 0 ? 'font-medium text-emerald-600' : 'font-medium text-destructive'}>{difference.toLocaleString('id-ID')}</dd></div></dl>
                        <Button type="submit" className="w-full" disabled={processing || !isBalanced}>Posting jurnal</Button>
                        {!isBalanced && <p className="text-xs text-muted-foreground">Isi sedikitnya dua baris dan seimbangkan nominal sebelum memposting.</p>}
                    </aside>
                </form>
            </main>
        </>
    );
}
