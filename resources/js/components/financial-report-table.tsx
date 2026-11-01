import type { ReactNode } from 'react';

export type FinancialReportRow = { label: ReactNode; debit?: number | string; credit?: number | string; amount?: number | string; level?: number; emphasis?: 'subtotal' | 'total' };
const money = (value?: number | string) => value === undefined ? '—' : `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export function FinancialReportTable({ rows, mode = 'amount' }: { rows: FinancialReportRow[]; mode?: 'amount' | 'debit-credit' }) {
    return <div className="overflow-x-auto rounded-xl border bg-card"><table className="w-full min-w-[680px] text-sm"><thead className="sticky top-0 z-10 border-b bg-muted/50 text-left text-xs text-muted-foreground"><tr><th className="p-3">Keterangan</th>{mode === 'debit-credit' ? <><th className="p-3 text-right">Debit</th><th className="p-3 text-right">Kredit</th></> : <th className="p-3 text-right">Nilai</th>}</tr></thead><tbody>{rows.map((row, index) => <tr key={index} className={row.emphasis === 'total' ? 'border-t-2 bg-muted/30 font-semibold' : row.emphasis === 'subtotal' ? 'border-t bg-muted/15 font-medium' : 'border-t'}><td className="p-3" style={{ paddingLeft: `${0.75 + (row.level ?? 0) * 1.25}rem` }}>{row.label}</td>{mode === 'debit-credit' ? <><td className="p-3 text-right tabular-nums">{money(row.debit)}</td><td className="p-3 text-right tabular-nums">{money(row.credit)}</td></> : <td className="p-3 text-right tabular-nums">{money(row.amount)}</td>}</tr>)}</tbody></table></div>;
}
