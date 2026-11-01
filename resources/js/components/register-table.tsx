import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type { ColumnDef, VisibilityState } from '@tanstack/react-table';
import { Columns3 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

export type RegisterColumn<T> = {
    /** Header text, taken from the server so UI and CSV stay in sync with the PRD. */
    header: string;
    cell: (row: T) => ReactNode;
    /** Right-align (money, quantities). */
    numeric?: boolean;
};

type Props<T> = {
    label: string;
    rows: T[];
    columns: RegisterColumn<T>[];
    /** Width in px of each sticky left column; the first N columns stay visible while scrolling. */
    sticky?: number[];
    rowClass?: (row: T) => string;
    empty: string;
};

/**
 * Wide transaction register: TanStack Table, horizontal scroll, sticky leading columns and a column
 * visibility menu (columns can be hidden, never removed). Used by every "Data ..." page.
 */
export function RegisterTable<T>({ label, rows, columns, sticky = [], rowClass, empty }: Props<T>) {
    const [visibility, setVisibility] = useState<VisibilityState>({});
    const defs: ColumnDef<T>[] = columns.map((c, i) => ({ id: `c${i}`, header: c.header, cell: ({ row }) => c.cell(row.original), meta: { numeric: c.numeric } }));
    const table = useReactTable({ data: rows, columns: defs, state: { columnVisibility: visibility }, onColumnVisibilityChange: setVisibility, getCoreRowModel: getCoreRowModel() });

    const left = sticky.map((_, i) => sticky.slice(0, i).reduce((a, b) => a + b, 0));
    const style = (i: number, header: boolean) => (i < sticky.length ? { position: 'sticky' as const, left: left[i], minWidth: sticky[i], zIndex: header ? 3 : 1 } : undefined);
    const numeric = (id: string) => Boolean((table.getColumn(id)?.columnDef.meta as { numeric?: boolean } | undefined)?.numeric);

    return (
        <div className="space-y-2">
            <div className="flex justify-end">
                <details className="relative">
                    <summary className="inline-flex cursor-pointer list-none items-center gap-2 rounded-md border px-3 py-2 text-sm"><Columns3 className="size-4" />Kolom</summary>
                    <div className="absolute right-0 z-10 mt-1 grid max-h-80 w-60 gap-1 overflow-y-auto rounded-md border bg-popover p-3 text-sm shadow-md">
                        {table.getAllLeafColumns().map((c, i) => (
                            <label key={c.id} className="flex items-center gap-2"><input type="checkbox" checked={c.getIsVisible()} disabled={i < sticky.length} onChange={c.getToggleVisibilityHandler()} />{columns[i].header || 'Aksi'} <span className="text-xs text-muted-foreground">{i + 1}</span></label>
                        ))}
                    </div>
                </details>
            </div>
            <div className="overflow-x-auto rounded-xl border bg-card" tabIndex={0} aria-label={label}>
                <table className="w-full text-sm">
                    <caption className="sr-only">{label}</caption>
                    <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                        {table.getHeaderGroups().map((hg) => (
                            <tr key={hg.id}>
                                {hg.headers.map((h, i) => (
                                    <th key={h.id} scope="col" style={style(i, true)} className={`h-11 whitespace-nowrap px-3 font-medium ${i < sticky.length ? 'bg-muted' : ''} ${numeric(h.column.id) ? 'text-right' : ''}`}>{flexRender(h.column.columnDef.header, h.getContext())}</th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.length ? table.getRowModel().rows.map((r) => (
                            <tr key={r.id} className={`border-t hover:bg-muted/30 ${rowClass?.(r.original) ?? ''}`}>
                                {r.getVisibleCells().map((c, i) => (
                                    <td key={c.id} style={style(i, false)} className={`whitespace-nowrap px-3 py-2 align-middle ${i < sticky.length ? 'bg-card' : ''} ${numeric(c.column.id) ? 'text-right tabular-nums' : ''}`}>{flexRender(c.column.columnDef.cell, c.getContext())}</td>
                                ))}
                            </tr>
                        )) : <tr><td colSpan={columns.length} className="h-32 text-center text-muted-foreground">{empty}</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
