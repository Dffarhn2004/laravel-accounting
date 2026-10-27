import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-4 py-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto hidden items-center gap-2 lg:flex">
                <Select defaultValue="all">
                    <SelectTrigger className="h-8 w-36" aria-label="Pilih cabang"><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem value="all">Semua cabang</SelectItem></SelectContent>
                </Select>
                <Select defaultValue="current">
                    <SelectTrigger className="h-8 w-40" aria-label="Pilih periode"><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem value="current">Periode berjalan</SelectItem></SelectContent>
                </Select>
                <Badge variant="outline" className="gap-1.5 py-1 text-xs"><span className="size-1.5 rounded-full bg-emerald-500" />Periode terbuka</Badge>
                <Button size="sm" variant="outline" className="h-8" asChild><a href="/transactions/invoices">+ Buat transaksi</a></Button>
            </div>
        </header>
    );
}
