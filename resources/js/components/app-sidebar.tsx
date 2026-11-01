import { Link } from '@inertiajs/react';
import { BookOpen, CircleDollarSign, FileBarChart2, LayoutDashboard, LogOut, ReceiptText, Settings2, Table2, WalletCards } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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

const navIcons: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard,
    system: Settings2,
    inputan: ReceiptText,
    data: Table2,
    laporan: FileBarChart2,
    anggaran: WalletCards,
    dividen: CircleDollarSign,
    front: LogOut,
};

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
                <NavMain icons={navIcons} />
            </SidebarContent>
            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
