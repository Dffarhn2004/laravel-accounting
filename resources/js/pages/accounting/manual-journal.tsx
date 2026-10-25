import { Head, useForm } from '@inertiajs/react';

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

    return (
        <>
            <Head title="Jurnal Manual" />
            <main className="mx-auto max-w-5xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Jurnal Manual</h1>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/accounting/manual-journal');
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <select value={data.branch_id} onChange={(event) => setData('branch_id', Number(event.target.value))} className="rounded border p-2" aria-label="Cabang">
                            {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                        </select>
                        <select value={data.fiscal_period_id} onChange={(event) => setData('fiscal_period_id', Number(event.target.value))} className="rounded border p-2" aria-label="Periode fiskal">
                            {fiscalPeriods.map((period) => <option key={period.id} value={period.id}>{period.starts_on} — {period.ends_on}</option>)}
                        </select>
                    </div>
                    <input
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Deskripsi jurnal"
                        className="w-full rounded border p-2"
                    />
                    {data.lines.map((line, index) => (
                        <div key={index} className="grid grid-cols-4 gap-2">
                            <select
                                value={line.account_id}
                                onChange={(event) => {
                                    const lines = [...data.lines];
                                    lines[index].account_id = Number(
                                        event.target.value,
                                    );
                                    setData('lines', lines);
                                }}
                                className="rounded border p-2"
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
                                className="rounded border p-2"
                            />
                            <input
                                value={line.credit}
                                onChange={(event) => {
                                    const lines = [...data.lines];
                                    lines[index].credit = event.target.value;
                                    setData('lines', lines);
                                }}
                                placeholder="Kredit"
                                className="rounded border p-2"
                            />
                        </div>
                    ))}
                    {errors.lines && (
                        <p className="text-sm text-red-600">{errors.lines}</p>
                    )}
                    <button
                        disabled={processing}
                        className="rounded bg-primary px-4 py-2 text-primary-foreground"
                    >
                        Posting Jurnal
                    </button>
                </form>
            </main>
        </>
    );
}
