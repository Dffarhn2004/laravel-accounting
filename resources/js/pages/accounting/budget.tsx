import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

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
    ratios?: Array<{ name: string; value: number | null; status: string }>;
    accounts?: Array<{ id: number; code: string; name: string }>;
    branches?: Array<{ id: number; name: string }>;
};

export default function Budget({ budgets, ratios = [], accounts = [], branches = [] }: BudgetPageProps) {
    const form = useForm({ branch_id: branches[0]?.id ?? '', fiscal_year: new Date().getFullYear(), account_id: accounts[0]?.id ?? '', activity: '', monthly_amounts: Array.from({ length: 12 }, () => '0') });
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
                    <form onSubmit={(event) => { event.preventDefault(); form.post('/accounting/budgets'); }} className="mb-6 grid gap-3 rounded-lg border bg-muted/10 p-4 md:grid-cols-4">
                        <select className="rounded-md border bg-background p-2" value={form.data.branch_id} onChange={(event) => form.setData('branch_id', Number(event.target.value))}>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select>
                        <input type="number" min="2000" className="rounded-md border bg-background p-2" value={form.data.fiscal_year} onChange={(event) => form.setData('fiscal_year', Number(event.target.value))} />
                        <select className="rounded-md border bg-background p-2" value={form.data.account_id} onChange={(event) => form.setData('account_id', Number(event.target.value))}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.code} — {account.name}</option>)}</select>
                        <input className="rounded-md border bg-background p-2" placeholder="Aktivitas (opsional)" value={form.data.activity} onChange={(event) => form.setData('activity', event.target.value)} />
                        <div className="grid grid-cols-3 gap-2 md:col-span-4 xl:grid-cols-6">{form.data.monthly_amounts.map((amount, index) => <label key={index} className="grid gap-1 text-xs text-muted-foreground">Bln {index + 1}<input type="number" min="0" step="0.01" className="rounded-md border bg-background p-2 text-sm" value={amount} onChange={(event) => form.setData('monthly_amounts', form.data.monthly_amounts.map((current, currentIndex) => currentIndex === index ? event.target.value : current))} /></label>)}</div>
                        <Button type="submit" disabled={form.processing}>Simpan draft anggaran</Button>
                    </form>
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
