import { Head, Link } from '@inertiajs/react';
import { InvoiceDocument, PRINT_CSS } from '@/components/invoice-document';
import type { DocCompany } from '@/components/invoice-document';
import { Button } from '@/components/ui/button';

type Invoice = {
    id: number;
    invoice_number: string;
    issued_on: string;
    dpp: string;
    discount_total: string;
    vat_rate: string;
    vat_amount: string;
    total: string;
    paid_amount: string;
    customer?: { name: string };
    lines: { id: number; description: string; quantity: string; unit_price: string; discount_percent: string; line_total: string }[];
    other_incomes: { id: number; label: string; amount: string }[];
};

export default function InvoicePrint({ invoice, company }: { invoice: Invoice; company: DocCompany }) {
    const remaining = (Number(invoice.total) - Number(invoice.paid_amount)).toFixed(2);

    return (
        <>
            <Head title={`Cetak ${invoice.invoice_number}`} />
            <style>{PRINT_CSS}</style>
            <main className="space-y-4 p-4 md:p-8">
                <div className="no-print mx-auto flex max-w-[210mm] items-center justify-between">
                    <Link href={`/transactions/invoices/${invoice.id}`} className="text-sm text-muted-foreground hover:text-foreground">← Kembali ke invoice</Link>
                    <Button onClick={() => window.print()}>Cetak / Simpan PDF</Button>
                </div>
                <InvoiceDocument
                    company={company}
                    number={invoice.invoice_number}
                    issuedOn={invoice.issued_on.slice(0, 10)}
                    customer={invoice.customer?.name ?? ''}
                    lines={invoice.lines}
                    otherIncomes={invoice.other_incomes}
                    totals={{ dpp: invoice.dpp, discount: invoice.discount_total, vat: invoice.vat_amount, vatRate: invoice.vat_rate, total: invoice.total, paid: invoice.paid_amount, remaining }}
                />
            </main>
        </>
    );
}
