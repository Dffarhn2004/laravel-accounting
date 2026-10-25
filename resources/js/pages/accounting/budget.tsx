import { Head } from '@inertiajs/react';

type BudgetPageProps = {
    budgets: {
        data: Array<{
            id: number;
            fiscal_year: number;
            activity: string;
            status: string;
            account?: { code: string; name: string };
            monthly_lines?: Array<{ month: number; amount: string }>;
        }>;
    };
    ratios: Array<{ name: string; value: number | null; status: string }>;
};

export default function Budget({ budgets, ratios }: BudgetPageProps) {
    return (
        <>
            <Head title="Budget & Rasio" />
            <main className="mx-auto grid w-full max-w-6xl gap-6 p-4 md:p-8">
                <section className="rounded-xl border bg-card p-6">
                    <h1 className="text-2xl font-semibold">Budget & Rasio Keuangan</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Budget membaca ledger sebagai actual. Rasio bernilai N/A jika denominator nol.
                    </p>
                </section>
                <section className="rounded-xl border bg-card p-6">
                    <h2 className="mb-4 font-semibold">Budget</h2>
                    {budgets.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Belum ada budget.</p>
                    ) : (
                        <div className="grid gap-3">
                            {budgets.data.map((budget) => (
                                <div key={budget.id} className="flex justify-between rounded-lg border p-3 text-sm">
                                    <span>{budget.account?.code} {budget.account?.name} · {budget.activity}</span>
                                    <span>{budget.status} · {budget.fiscal_year}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
                <section className="rounded-xl border bg-card p-6">
                    <h2 className="mb-4 font-semibold">19 Rasio Keuangan</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {ratios.map((ratio) => (
                            <div key={ratio.name} className="rounded-lg border p-3">
                                <div className="text-sm text-muted-foreground">{ratio.name}</div>
                                <div className="mt-1 text-lg font-semibold tabular-nums">
                                    {ratio.status === 'N/A' ? 'N/A' : ratio.value}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </main>
        </>
    );
}
