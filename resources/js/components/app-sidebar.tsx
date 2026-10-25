import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BookOpen,
    ChartNoAxesCombined,
    Coins,
    Database,
    LayoutDashboard,
    Settings2,
    WalletCards,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
    { title: 'Master & Sistem', href: '/accounting/master-data', icon: Settings2 },
    {
        title: 'Input Transaksi',
        href: '/accounting/transactions',
        icon: ArrowLeftRight,
    },
    { title: 'Data Transaksi', href: '/accounting/transactions', icon: Database },
    {
        title: 'Laporan Keuangan',
        href: '/accounting/reports',
        icon: ChartNoAxesCombined,
    },
    { title: 'Anggaran', href: '/accounting/budgets', icon: WalletCards },
    { title: 'Bagi Hasil & Dividen', href: '/accounting/reports', icon: Coins },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Dokumentasi',
        href: 'https://github.com/Dffarhn2004/laravel-accounting/tree/main/docs',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>
            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
