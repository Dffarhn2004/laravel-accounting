import { Link } from '@inertiajs/react';

export type MatrixRow = {
    type: 'heading' | 'section' | 'account' | 'subtotal' | 'total';
    label: string;
    code?: string | null;
    account_id?: number | null;
    values?: (string | null)[];
    total?: string;
    ratio?: string | null;
    growth?: string | null;
    computed?: boolean;
};

type Props = {
    title: string;
    columns: string[];
    rows: MatrixRow[];
    /** Show a TOTAL column (sum of the value columns). */
    showTotal?: boolean;
    /** Extra trailing column: percent of revenue, or growth between the two positions. */
    extra?: { key: 'ratio' | 'growth'; label: string };
    /** Query carried to Buku Besar when an account row is opened. */
    drill?: Record<string, string>;
};

/** Accounting style: negatives in parentheses, zero as a dash, N/A for undefined percentages. */
export function fmt(value: string | null | undefined): string {
    if (value === null || value === undefined) return '';
    const n = Number(value);
    if (n === 0) return '–';
    const text = Math.abs(n).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? `(${text})` : text;
}

const pct = (value: string | null | undefined) => (value === null || value === undefined ? 'N/A' : `${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`);

const STYLE: Record<MatrixRow['type'], string> = {
    heading: 'bg-muted font-semibold uppercase tracking-wide',
    section: 'bg-muted/30 font-medium',
    account: '',
    subtotal: 'border-t font-medium',
    total: 'border-t-2 bg-muted/40 font-semibold',
};

export function ReportMatrix({ title, columns, rows, showTotal = false, extra, drill }: Props) {
    const span = 2 + columns.length + (showTotal ? 1 : 0) + (extra ? 1 : 0);
    const href = (id: number) => `/accounting/ledger?${new URLSearchParams({ ...drill, account_id: String(id) }).toString()}`;

    return (
        <div className="overflow-x-auto rounded-xl border bg-card" tabIndex={0} aria-label={title}>
            <table className="w-full text-sm">
                <caption className="sr-only">{title}</caption>
                <thead className="bg-muted/60 text-xs text-muted-foreground">
                    <tr>
                        <th scope="col" className="sticky left-0 z-20 h-11 w-20 bg-muted px-3 text-left font-medium">Kode</th>
                        <th scope="col" className="sticky left-20 z-20 h-11 min-w-64 bg-muted px-3 text-left font-medium">Uraian</th>
                        {columns.map((c) => <th key={c} scope="col" className="h-11 whitespace-nowrap px-3 text-right font-medium">{c}</th>)}
                        {showTotal && <th scope="col" className="h-11 px-3 text-right font-medium">TOTAL</th>}
                        {extra && <th scope="col" className="h-11 whitespace-nowrap px-3 text-right font-medium">{extra.label}</th>}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={span} className="h-24 text-center text-muted-foreground">Belum ada data pada periode ini.</td></tr>}
                    {rows.map((r, i) => {
                        const isLabelOnly = r.type === 'heading' || r.type === 'section';
                        const indent = r.type === 'account' ? 'pl-6' : '';
                        return (
                            <tr key={i} className={`${STYLE[r.type]} ${r.computed ? 'italic' : ''}`}>
                                <td className="sticky left-0 z-10 bg-inherit px-3 py-2 tabular-nums text-muted-foreground">{r.code}</td>
                                <th scope="row" className={`sticky left-20 z-10 bg-inherit px-3 py-2 text-left font-[inherit] ${indent}`} colSpan={isLabelOnly ? span - 1 : 1}>
                                    {r.account_id ? <Link href={href(r.account_id)} className="underline-offset-2 hover:underline">{r.label}</Link> : r.label}
                                </th>
                                {!isLabelOnly && columns.map((_, c) => <td key={c} className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{fmt(r.values?.[c])}</td>)}
                                {!isLabelOnly && showTotal && <td className="whitespace-nowrap px-3 py-2 text-right font-medium tabular-nums">{fmt(r.total)}</td>}
                                {!isLabelOnly && extra && <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-muted-foreground">{r.type === 'account' || r.type === 'subtotal' || r.type === 'total' ? pct(r[extra.key]) : ''}</td>}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
