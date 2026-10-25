import { Head, useForm } from '@inertiajs/react';

type Option = { id: number; name?: string; code?: string };
type Transaction = { id: number; description: string; amount: string; status: string };

export default function Treasury({
    cashAccounts = [],
    branches = [],
    fiscalPeriods = [],
    accounts = [],
    transactions,
    reconciliation,
}: {
    cashAccounts?: Option[];
    branches?: Option[];
    fiscalPeriods?: Option[];
    accounts?: Option[];
    transactions?: { data: Transaction[]; total?: number };
    reconciliation?: { treasury: number; gl: number; difference: number };
}) {
    const form = useForm({
        branch_id: branches[0]?.id ?? '',
        fiscal_period_id: fiscalPeriods[0]?.id ?? '',
        cash_account_id: cashAccounts[0]?.id ?? '',
        counter_account_id: accounts[0]?.id ?? '',
        amount: '',
        direction: 'in',
        description: '',
        posted_on: new Date().toISOString().slice(0, 10),
        idempotency_key: `treasury-${Date.now()}`,
        lines: [],
    });

    return (
        <>
            <Head title="Treasury" />
            <main className="mx-auto max-w-7xl space-y-6 p-4 md:p-8">
                <header>
                    <h1 className="text-3xl font-semibold">Kas & Bank</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pergerakan kas scoped berdasarkan company dan branch aktif.
                    </p>
                </header>
                <form onSubmit={(event) => { event.preventDefault(); form.post('/accounting/treasury/movements'); }} className="grid gap-3 rounded-xl border bg-card p-5 md:grid-cols-4">
                    <select className="rounded border p-2" value={form.data.cash_account_id} onChange={(event) => form.setData('cash_account_id', Number(event.target.value))}>
                        {cashAccounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}
                    </select>
                    <select className="rounded border p-2" value={form.data.counter_account_id} onChange={(event) => form.setData('counter_account_id', Number(event.target.value))}>
                        {accounts.map((account) => <option key={account.id} value={account.id}>{account.code ?? account.name}</option>)}
                    </select>
                    <input required type="number" min="0.01" step="0.01" placeholder="Nominal" className="rounded border p-2" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
                    <select className="rounded border p-2" value={form.data.direction} onChange={(event) => form.setData('direction', event.target.value)}>
                        <option value="in">Penerimaan</option>
                        <option value="out">Pengeluaran</option>
                    </select>
                    <input required placeholder="Keterangan" className="rounded border p-2 md:col-span-3" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    <button disabled={form.processing} className="rounded bg-primary px-4 py-2 text-primary-foreground">Posting</button>
                    {form.errors.amount && <p className="text-sm text-destructive md:col-span-4">{form.errors.amount}</p>}
                </form>
                <section className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border p-5"><p className="text-sm text-muted-foreground">Saldo Treasury</p><p className="mt-2 text-2xl font-semibold">{reconciliation?.treasury ?? 'N/A'}</p></div>
                    <div className="rounded-xl border p-5"><p className="text-sm text-muted-foreground">Saldo GL</p><p className="mt-2 text-2xl font-semibold">{reconciliation?.gl ?? 'N/A'}</p></div>
                    <div className="rounded-xl border p-5"><p className="text-sm text-muted-foreground">Selisih</p><p className="mt-2 text-2xl font-semibold">{reconciliation?.difference ?? 'N/A'}</p></div>
                </section>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b text-left"><th className="p-3">Keterangan</th><th className="p-3">Nominal</th><th className="p-3">Status</th></tr></thead>
                        <tbody>{transactions?.data?.length ? transactions.data.map((transaction) => <tr key={transaction.id} className="border-b"><td className="p-3">{transaction.description}</td><td className="p-3">{transaction.amount}</td><td className="p-3">{transaction.status}</td></tr>) : <tr><td colSpan={3} className="p-8 text-center text-muted-foreground">Belum ada transaksi treasury.</td></tr>}</tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
