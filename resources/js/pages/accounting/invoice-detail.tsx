import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Invoice = {
    id: number;
    invoice_number: string;
    issued_on: string;
    total: string;
    dpp: string;
    discount_total: string;
    vat_rate: string;
    vat_amount: string;
    pph23_rate: string;
    pph23_amount: string;
    status: string;
    customer?: { name: string };
    lines: { id: number; description: string; quantity: string; unit_price: string; discount_percent: string; line_total: string; revenue_account?: { code: string; name: string } | null }[];
    other_incomes: { id: number; label: string; amount: string; account?: { code: string; name: string } }[];
    attachments: { id: number; original_name: string; mime: string }[];
    receipt_allocations?: { id: number; amount: string }[];
};
type Journal = { id: number; posted_on: string; description: string; lines: { id: number; debit: string; credit: string; account: { code: string; name: string } }[] };

const money = (value: string | number) => `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const statusLabel: Record<string, string> = { unpaid: 'Piutang', partial: 'Sebagian', paid: 'Lunas' };

export default function InvoiceDetail({ invoice, journalEntry }: { invoice: Invoice; journalEntry?: Journal | null }) {
    const paid = invoice.receipt_allocations?.reduce((sum, allocation) => sum + Number(allocation.amount), 0) ?? 0;
    const outstanding = Number(invoice.total) - paid;

    return (
        <>
            <Head title={`Invoice ${invoice.invoice_number}`} />
            <main className="mx-auto max-w-5xl space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link href="/transactions/invoices" className="text-sm text-muted-foreground hover:text-foreground">← Data Pendapatan</Link>
                        <div className="mt-3 flex items-center gap-3">
                            <h1 className="text-3xl font-semibold tracking-tight">{invoice.invoice_number}</h1>
                            <Badge variant="secondary">{statusLabel[invoice.status] ?? invoice.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">{invoice.issued_on.slice(0, 10)} · {invoice.customer?.name ?? 'Pelanggan tidak tersedia'}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={`/transactions/invoices/${invoice.id}/print`}><Printer />Cetak</Link></Button>
                        <Button variant="outline" asChild><Link href="/accounting/journals">Lihat jurnal umum</Link></Button>
                    </div>
                </header>

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Rincian invoice</h2></div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr><th className="p-3">Deskripsi</th><th className="p-3">Kelompok Pendapatan</th><th className="p-3 text-right">Qty</th><th className="p-3 text-right">Harga</th><th className="p-3 text-right">Disc %</th><th className="p-3 text-right">Total</th></tr>
                            </thead>
                            <tbody>
                                {invoice.lines.map((line) => (
                                    <tr key={line.id} className="border-b last:border-0">
                                        <td className="p-3">{line.description}</td>
                                        <td className="p-3 text-muted-foreground">{line.revenue_account ? `${line.revenue_account.code} ${line.revenue_account.name}` : '—'}</td>
                                        <td className="p-3 text-right tabular-nums">{Number(line.quantity)}</td>
                                        <td className="p-3 text-right tabular-nums">{money(line.unit_price)}</td>
                                        <td className="p-3 text-right tabular-nums">{Number(line.discount_percent)}</td>
                                        <td className="p-3 text-right font-medium tabular-nums">{money(line.line_total)}</td>
                                    </tr>
                                ))}
                                {invoice.other_incomes.map((o) => (
                                    <tr key={`o${o.id}`} className="border-b last:border-0">
                                        <td className="p-3" colSpan={2}>{o.label} <span className="text-muted-foreground">{o.account ? `(${o.account.code})` : ''}</span></td>
                                        <td colSpan={3} />
                                        <td className="p-3 text-right font-medium tabular-nums">{money(o.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="text-sm">
                                <tr><td colSpan={5} className="p-3 text-right">DPP</td><td className="p-3 text-right tabular-nums">{money(invoice.dpp)}</td></tr>
                                {Number(invoice.vat_amount) > 0 && <tr><td colSpan={5} className="p-3 text-right">PPN {Number(invoice.vat_rate)}%</td><td className="p-3 text-right tabular-nums">{money(invoice.vat_amount)}</td></tr>}
                                <tr><td colSpan={5} className="p-3 text-right font-semibold">Total tagihan</td><td className="p-3 text-right font-semibold tabular-nums">{money(invoice.total)}</td></tr>
                                {Number(invoice.pph23_amount) > 0 && <tr className="text-muted-foreground"><td colSpan={5} className="p-3 text-right">Estimasi PPh 23 {Number(invoice.pph23_rate)}% (tidak mengurangi tagihan)</td><td className="p-3 text-right tabular-nums">{money(invoice.pph23_amount)}</td></tr>}
                            </tfoot>
                        </table>
                    </div>
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <section className="rounded-xl border bg-card p-5"><p className="text-sm text-muted-foreground">Total invoice</p><p className="mt-2 text-xl font-semibold tabular-nums">{money(invoice.total)}</p></section>
                    <section className="rounded-xl border bg-card p-5"><p className="text-sm text-muted-foreground">Sudah diterima</p><p className="mt-2 text-xl font-semibold tabular-nums">{money(paid)}</p></section>
                    <section className="rounded-xl border bg-card p-5"><p className="text-sm text-muted-foreground">Sisa piutang</p><p className="mt-2 text-xl font-semibold tabular-nums">{money(outstanding)}</p></section>
                </div>

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Bukti</h2></div>
                    {invoice.attachments.length === 0 ? <p className="p-5 text-sm text-muted-foreground">Tidak ada lampiran.</p> : (
                        <ul className="divide-y">
                            {invoice.attachments.map((a) => (
                                <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                    <span>{a.original_name}</span>
                                    <a className="underline" href={`/attachments/${a.id}`} target="_blank" rel="noreferrer">Buka</a>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Jurnal terkait</h2><p className="text-sm text-muted-foreground">Referensi jurnal yang dibuat saat invoice diposting.</p></div>
                    {journalEntry ? (
                        <div className="p-5">
                            <div className="mb-4 flex justify-between gap-3 text-sm"><span>{journalEntry.posted_on.slice(0, 10)}</span><span className="text-muted-foreground">#{journalEntry.id}</span></div>
                            <div className="divide-y rounded-lg border">
                                {journalEntry.lines.map((line) => (
                                    <div key={line.id} className="grid grid-cols-[1fr_8rem_8rem] gap-3 px-3 py-2 text-sm">
                                        <span>{line.account.code} — {line.account.name}</span>
                                        <span className="text-right tabular-nums">{Number(line.debit) ? money(line.debit) : '—'}</span>
                                        <span className="text-right tabular-nums">{Number(line.credit) ? money(line.credit) : '—'}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : <p className="p-8 text-center text-sm text-muted-foreground">Jurnal sumber belum tersedia.</p>}
                </section>
            </main>
        </>
    );
}
