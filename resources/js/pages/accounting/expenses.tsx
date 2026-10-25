import { Head, Link } from '@inertiajs/react';

type Expense = {
    id: number;
    expense_date: string;
    amount: string;
    status: string;
    reference?: string;
    vendor?: { name: string };
};

export default function Expenses({ expenses }: { expenses: { data: Expense[] } }) {
    return (
        <>
            <Head title="Expenses" />
            <main className="mx-auto max-w-6xl p-6">
                <Link href="/dashboard" className="text-sm text-muted-foreground">Back to dashboard</Link>
                <h1 className="mt-4 text-2xl font-semibold">Expenses</h1>
                <div className="mt-6 overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead><tr className="border-b bg-muted/40"><th className="p-3">Date</th><th className="p-3">Vendor</th><th className="p-3">Reference</th><th className="p-3">Amount</th><th className="p-3">Status</th></tr></thead>
                        <tbody>
                            {expenses.data.map((expense) => (
                                <tr key={expense.id} className="border-b last:border-0">
                                    <td className="p-3">{expense.expense_date}</td>
                                    <td className="p-3">{expense.vendor?.name ?? '—'}</td>
                                    <td className="p-3">{expense.reference ?? '—'}</td>
                                    <td className="p-3">{expense.amount}</td>
                                    <td className="p-3">{expense.status}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
