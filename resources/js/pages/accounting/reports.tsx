import { Head } from '@inertiajs/react';

type Summary = Record<string, { debit: string; credit: string }>;

export default function Reports({
    summary,
    receivables,
    payables,
}: {
    summary: Summary;
    receivables: string;
    payables: string;
}) {
    return (
        <>
            <Head title="Accounting reports" />
            <main className="mx-auto max-w-6xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Accounting reports</h1>
                <div className="grid gap-4 md:grid-cols-3">
                    {Object.entries(summary).map(([type, value]) => (
                        <section className="rounded-lg border p-4" key={type}>
                            <h2 className="font-medium capitalize">{type}</h2>
                            <p>Debit: {value.debit}</p>
                            <p>Credit: {value.credit}</p>
                        </section>
                    ))}
                </div>
                <section className="rounded-lg border p-4">
                    <h2 className="font-medium">Subledger balances</h2>
                    <p>Receivables: {receivables}</p>
                    <p>Payables: {payables}</p>
                </section>
            </main>
        </>
    );
}
