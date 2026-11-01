import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { PRINT_CSS } from '@/components/invoice-document';
import { Button } from '@/components/ui/button';

type Props = { title: string; subtitle: string; period?: string; filters: ReactNode; children: ReactNode };

/** Common frame of the financial statements: heading, filter bar, print button and print stylesheet. */
export function ReportShell({ title, subtitle, period, filters, children }: Props) {
    return (
        <>
            <Head title={title} />
            <style>{PRINT_CSS}</style>
            <main className="mx-auto w-full max-w-[1600px] space-y-4 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="no-print text-sm text-muted-foreground">Laporan</p>
                        <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
                        {period && <p className="hidden text-sm print:block">{period}</p>}
                        <p className="no-print mt-1 max-w-3xl text-sm text-muted-foreground">{subtitle}</p>
                    </div>
                    <Button className="no-print" variant="outline" onClick={() => window.print()}><Printer />Cetak</Button>
                </header>
                <div className="no-print">{filters}</div>
                {children}
            </main>
        </>
    );
}

export const filterBar = 'flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4';
export const selectClass = 'rounded-md border bg-background p-2 text-sm';
