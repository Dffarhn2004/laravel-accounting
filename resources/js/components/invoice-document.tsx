export type DocLine = { description: string; quantity: string | number; unit_price: string | number; discount_percent: string | number; line_total: string | number };
export type DocOther = { label: string; amount: string | number };
export type DocCompany = {
    name: string;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    invoice_bank_name?: string | null;
    invoice_bank_account_name?: string | null;
    invoice_bank_account_number?: string | null;
};
export type DocTotals = { dpp: string | number; discount: string | number; vat: string | number; vatRate: string | number; total: string | number; paid: string | number; remaining: string | number };

export const money = (value: string | number) => `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Browsers print only what is visible, so the app chrome is hidden for the print layout. */
export const PRINT_CSS = `@page { size: A4; margin: 14mm; }
@media print {
  [data-slot="sidebar"], [data-slot="sidebar-gap"], [data-slot="sidebar-container"], [data-slot="sidebar-rail"], header, .no-print { display: none !important; }
  [data-slot="sidebar-inset"] { margin: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
  body { background: #fff !important; }
}`;

type Props = {
    company: DocCompany;
    number: string;
    issuedOn: string;
    customer: string;
    lines: DocLine[];
    otherIncomes: DocOther[];
    totals: DocTotals;
};

/** A4 invoice used for the live preview in the form and for the print page. */
export function InvoiceDocument({ company, number, issuedOn, customer, lines, otherIncomes, totals }: Props) {
    const hasBank = company.invoice_bank_name || company.invoice_bank_account_number;

    return (
        <article className="mx-auto w-full max-w-[210mm] space-y-6 rounded-lg border bg-white p-8 text-sm text-neutral-900 print:max-w-none print:rounded-none print:border-0 print:p-0">
            <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-4">
                <div>
                    <h2 className="text-lg font-semibold">{company.name}</h2>
                    {company.address && <p className="whitespace-pre-line">{company.address}</p>}
                    <p className="text-neutral-600">{[company.phone, company.email].filter(Boolean).join(' · ')}</p>
                </div>
                <div className="text-right">
                    <p className="text-2xl font-semibold tracking-wide">INVOICE</p>
                    <p className="tabular-nums">{number}</p>
                    <p className="text-neutral-600">{issuedOn}</p>
                </div>
            </header>

            <p><span className="text-neutral-600">Kepada:</span> <strong>{customer || '—'}</strong></p>

            <table className="w-full">
                <thead>
                    <tr className="border-b text-left text-xs text-neutral-600">
                        <th className="py-2">Deskripsi</th><th className="py-2 text-right">Qty</th><th className="py-2 text-right">Harga</th><th className="py-2 text-right">Disc %</th><th className="py-2 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((l, i) => (
                        <tr key={i} className="border-b align-top">
                            <td className="py-2">{l.description || '—'}</td>
                            <td className="py-2 text-right tabular-nums">{Number(l.quantity)}</td>
                            <td className="py-2 text-right tabular-nums">{money(l.unit_price)}</td>
                            <td className="py-2 text-right tabular-nums">{Number(l.discount_percent)}</td>
                            <td className="py-2 text-right tabular-nums">{money(l.line_total)}</td>
                        </tr>
                    ))}
                    {otherIncomes.map((o, i) => (
                        <tr key={`o${i}`} className="border-b">
                            <td className="py-2" colSpan={4}>{o.label}</td>
                            <td className="py-2 text-right tabular-nums">{money(o.amount)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <dl className="ml-auto grid w-full max-w-xs grid-cols-[1fr_auto] gap-x-6 gap-y-1">
                <dt>DPP</dt><dd className="text-right tabular-nums">{money(totals.dpp)}</dd>
                {Number(totals.discount) > 0 && <><dt className="text-neutral-600">Sudah termasuk diskon</dt><dd className="text-right tabular-nums text-neutral-600">{money(totals.discount)}</dd></>}
                {Number(totals.vat) > 0 && <><dt>PPN {Number(totals.vatRate)}%</dt><dd className="text-right tabular-nums">{money(totals.vat)}</dd></>}
                <dt className="border-t pt-1 font-semibold">Total tagihan</dt><dd className="border-t pt-1 text-right font-semibold tabular-nums">{money(totals.total)}</dd>
                <dt>Dibayar</dt><dd className="text-right tabular-nums">{money(totals.paid)}</dd>
                <dt className="font-semibold">Sisa piutang</dt><dd className="text-right font-semibold tabular-nums">{money(totals.remaining)}</dd>
            </dl>

            {hasBank && (
                <footer className="border-t pt-4">
                    <p className="text-xs text-neutral-600">Pembayaran ke</p>
                    <p>{company.invoice_bank_name} · {company.invoice_bank_account_number}</p>
                    {company.invoice_bank_account_name && <p>a.n. {company.invoice_bank_account_name}</p>}
                </footer>
            )}
        </article>
    );
}
