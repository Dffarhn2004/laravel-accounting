import { Link } from '@inertiajs/react';
import { BookOpen, BookOpenCheck, Building2, CircleDollarSign, FileBarChart2, LayoutDashboard, ReceiptText, Settings2, WalletCards } from 'lucide-react';
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
    {
        title: 'Master Data', href: '/master/accounts', icon: Building2,
        items: [
            { title: 'Chart of Accounts', href: '/master/accounts' },
            { title: 'Cabang / Brand', href: '/master/branches' },
            { title: 'Pelanggan', href: '/master/customers' },
            { title: 'Pemasok', href: '/master/vendors' },
            { title: 'Produk & Harga', href: '/master/products' },
            { title: 'Kas & Bank', href: '/master/accounts-cash' },
        ],
    },
    {
        title: 'Transaksi', href: '/transactions/invoices', icon: ReceiptText,
        items: [
            { title: 'Penjualan & Invoice', href: '/transactions/invoices' },
            { title: 'Penerimaan Piutang', href: '/transactions/receivables' },
            { title: 'Pengeluaran & Beban', href: '/transactions/expenses' },
            { title: 'Kas & Bank', href: '/transactions/cash-bank' },
            { title: 'Persediaan', href: '/accounting/inventory' },
            { title: 'Aset Tetap', href: '/accounting/fixed-assets' },
        ],
    },
    {
        title: 'Akuntansi', href: '/accounting/journals', icon: BookOpenCheck,
        items: [
            { title: 'Saldo Awal & Cut-off', href: '/accounting/opening-balances' },
            { title: 'Jurnal Umum', href: '/accounting/journals' },
            { title: 'Jurnal Manual', href: '/accounting/manual-journal' },
            { title: 'Buku Besar', href: '/accounting/ledger' },
            { title: 'Tutup Buku & Periode', href: '/accounting/closing' },
        ],
    },
    {
        title: 'Laporan', href: '/accounting/reports', icon: FileBarChart2,
        items: [
            { title: 'Ringkasan Laporan', href: '/accounting/reports' },
            { title: 'Laba Rugi', href: '/reports/profit-loss' },
            { title: 'Neraca', href: '/reports/balance-sheet' },
            { title: 'Umur Piutang / Hutang', href: '/accounting/aging' },
        ],
    },
    {
        title: 'Anggaran', href: '/accounting/budgets', icon: WalletCards,
        items: [
            { title: 'Rencana Anggaran', href: '/accounting/budgets' },
            { title: 'Realisasi Anggaran', href: '/accounting/budgets' },
        ],
    },
    {
        title: 'Bagi Hasil & Dividen', href: '/modules/dividen', icon: CircleDollarSign,
        items: [{ title: 'Pemegang Saham & Distribusi', href: '/modules/dividen' }],
    },
    {
        title: 'Pengaturan', href: '/settings/profile', icon: Settings2,
        items: [
            { title: 'Profil Perusahaan', href: '/settings/company' },
            { title: 'Preferensi Aplikasi', href: '/settings/appearance' },
        ],
    },
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
