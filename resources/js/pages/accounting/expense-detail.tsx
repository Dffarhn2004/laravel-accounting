import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Expense = {
    id: number;
    reference: string | null;
    description: string | null;
    expense_date: string;
    amount: string;
    status: string;
    reversal_reason: string | null;
    branch?: { name: string } | null;
    vendor?: { name: string } | null;
    expense_account?: { code: string; name: string } | null;
    settlement_account?: { code: string; name: string } | null;
    payments: { id: number; paid_on: string; amount: string }[];
    attachments: { id: number; original_name: string }[];
};
type Journal = { id: number; posted_on: string; lines: { id: number; debit: string; credit: string; account: { code: string; name: string } }[] };

const money = (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const STATUS: Record<string, string> = { paid: 'Lunas', open: 'Hutang', partial: 'Dicicil', reversed: 'Dibalik' };

export default function ExpenseDetail({ expense, journalEntry }: { expense: Expense; journalEntry?: Journal | null }) {
    const paid = expense.payments.reduce((s, p) => s + Number(p.amount), 0);

    return (
        <>
            <Head title={`Beban ${expense.reference ?? expense.id}`} />
            <main className="mx-auto max-w-4xl space-y-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link href="/transactions/expenses" className="text-sm text-muted-foreground hover:text-foreground">← Data Beban</Link>
                        <div className="mt-3 flex items-center gap-3">
                            <h1 className="text-3xl font-semibold tracking-tight">{expense.reference ?? `#${expense.id}`}</h1>
                            <Badge variant="secondary">{STATUS[expense.status] ?? expense.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">{expense.expense_date.slice(0, 10)} · {expense.branch?.name}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href="/transactions/expenses/create">Input beban baru</Link></Button>
                        <Button variant="outline" asChild><Link href="/accounting/journals">Lihat jurnal umum</Link></Button>
                    </div>
                </header>

                {expense.status === 'reversed' && <p role="status" className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm">Beban ini dibalik. Alasan: {expense.reversal_reason}</p>}

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div><p className="text-sm text-muted-foreground">Keterangan</p><p className="mt-1">{expense.description ?? '—'}</p></div>
                    <div><p className="text-sm text-muted-foreground">Total</p><p className="mt-1 text-xl font-semibold tabular-nums">{money(expense.amount)}</p></div>
                    <div><p className="text-sm text-muted-foreground">Akun beban</p><p className="mt-1">{expense.expense_account ? `${expense.expense_account.code} — ${expense.expense_account.name}` : '—'}</p></div>
                    <div><p className="text-sm text-muted-foreground">Akun lawan</p><p className="mt-1">{expense.settlement_account ? `${expense.settlement_account.code} — ${expense.settlement_account.name}` : '—'}</p></div>
                    {expense.status !== 'paid' && expense.status !== 'reversed' && <div><p className="text-sm text-muted-foreground">Sisa hutang</p><p className="mt-1 font-semibold tabular-nums">{money(Number(expense.amount) - paid)}</p></div>}
                </section>

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Bukti</h2></div>
                    {expense.attachments.length === 0 ? <p className="p-5 text-sm text-muted-foreground">Tidak ada lampiran.</p> : (
                        <ul className="divide-y">{expense.attachments.map((a) => <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm"><span>{a.original_name}</span><a className="underline" href={`/attachments/${a.id}`} target="_blank" rel="noreferrer">Buka</a></li>)}</ul>
                    )}
                </section>

                {expense.payments.length > 0 && (
                    <section className="rounded-xl border bg-card">
                        <div className="border-b px-5 py-4"><h2 className="font-semibold">Pelunasan hutang</h2></div>
                        <ul className="divide-y">{expense.payments.map((p) => <li key={p.id} className="flex justify-between px-5 py-3 text-sm"><span>{p.paid_on.slice(0, 10)}</span><span className="tabular-nums">{money(p.amount)}</span></li>)}</ul>
                    </section>
                )}

                <section className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-4"><h2 className="font-semibold">Jurnal terkait</h2></div>
                    {journalEntry ? (
                        <div className="divide-y">
                            {journalEntry.lines.map((l) => (
                                <div key={l.id} className="grid grid-cols-[1fr_8rem_8rem] gap-3 px-5 py-2 text-sm">
                                    <span>{l.account.code} — {l.account.name}</span>
                                    <span className="text-right tabular-nums">{Number(l.debit) ? money(l.debit) : '—'}</span>
                                    <span className="text-right tabular-nums">{Number(l.credit) ? money(l.credit) : '—'}</span>
                                </div>
                            ))}
                        </div>
                    ) : <p className="p-8 text-center text-sm text-muted-foreground">Jurnal sumber belum tersedia.</p>}
                </section>
            </main>
        </>
    );
}
