import { Head, Link, useForm } from '@inertiajs/react';

type Invoice = {
    id: number;
    number: string;
    issued_on: string;
    total: string;
    status: string;
    customer?: { name: string };
};

type Option = { id: number; name?: string; code?: string };

export default function Invoices({ invoices, customers = [], accounts = [], branches = [], fiscalPeriods = [] }: { invoices: { data: Invoice[] }; customers?: Option[]; accounts?: Option[]; branches?: Option[]; fiscalPeriods?: Option[] }) {
    const form = useForm({
        branch_id: branches[0]?.id ?? '',
        fiscal_period_id: fiscalPeriods[0]?.id ?? '',
        customer_id: customers[0]?.id ?? '',
        receivable_account_id: accounts.find((account) => account.code?.startsWith('1'))?.id ?? accounts[0]?.id ?? '',
        revenue_account_id: accounts.find((account) => account.code?.startsWith('4'))?.id ?? accounts[0]?.id ?? '',
        invoice_number: '',
        issued_on: new Date().toISOString().slice(0, 10),
        idempotency_key: `invoice-${Date.now()}`,
        lines: [{ description: '', quantity: '1', unit_price: '' }],
    });

    return (
        <>
            <Head title="Invoices" />
            <main className="mx-auto max-w-6xl space-y-6 p-6">
                <Link href="/dashboard" className="text-sm text-muted-foreground">← Dashboard</Link>
                <h1 className="text-3xl font-semibold">Invoices</h1>
                <form onSubmit={(event) => { event.preventDefault(); form.post('/accounting/invoices'); }} className="grid gap-3 rounded-xl border bg-card p-5 md:grid-cols-4">
                    <input required placeholder="Nomor invoice" className="rounded border p-2" value={form.data.invoice_number} onChange={(event) => form.setData('invoice_number', event.target.value)} />
                    <select required className="rounded border p-2" value={form.data.customer_id} onChange={(event) => form.setData('customer_id', Number(event.target.value))}>
                        <option value="">Customer</option>
                        {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}
                    </select>
                    <input required type="number" min="0" step="0.01" placeholder="Total" className="rounded border p-2" value={form.data.lines[0].unit_price} onChange={(event) => form.setData('lines', [{ ...form.data.lines[0], unit_price: event.target.value }])} />
                    <button disabled={form.processing} className="rounded bg-primary px-4 py-2 text-primary-foreground">Posting Invoice</button>
                    {form.errors.invoice_number && <p className="text-sm text-destructive md:col-span-4">{form.errors.invoice_number}</p>}
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b text-left"><th className="p-3">Invoice</th><th className="p-3">Customer</th><th className="p-3">Date</th><th className="p-3">Total</th><th className="p-3">Status</th></tr></thead>
                        <tbody>
                            {invoices.data.map((invoice) => (
                                <tr key={invoice.id} className="border-b last:border-0">
                                    <td className="p-3">{invoice.number}</td>
                                    <td className="p-3">{invoice.customer?.name ?? '—'}</td>
                                    <td className="p-3">{invoice.issued_on}</td>
                                    <td className="p-3">Rp {invoice.total}</td>
                                    <td className="p-3 capitalize">{invoice.status}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </>
    );
}
