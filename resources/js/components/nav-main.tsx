import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Clock, LogOut } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import type { ContractItem } from '@/types';

type Props = { icons: Record<string, LucideIcon> };

const STORAGE_KEY = 'accounting-navigation-groups';

function readStored(): Record<string, boolean> {
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '{}') as Record<string, boolean>;
    } catch {
        return {};
    }
}

/**
 * Renders the sidebar exactly as defined by config/navigation.php
 * (shared as the `navigation` Inertia prop).
 */
export function NavMain({ icons }: Props) {
    const { navigation } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const cleanup = useMobileNavigation();
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    useEffect(() => {
        setOpenGroups(readStored());
    }, []);

    const setGroupOpen = (id: string, open: boolean) => {
        const next = { ...openGroups, [id]: open };
        setOpenGroups(next);
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // storage unavailable: group state is simply not remembered
        }
    };

    const isActive = (item: ContractItem) => isCurrentUrl(item.path);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Menu</SidebarGroupLabel>
            <SidebarMenu>
                {navigation.map((section) => {
                    const Icon = icons[section.id];

                    if (section.type === 'action') {
                        return (
                            <SidebarMenuItem key={section.id}>
                                <SidebarMenuButton asChild tooltip={{ children: section.label }}>
                                    <Link
                                        href={logout()}
                                        as="button"
                                        onClick={cleanup}
                                        data-test="sidebar-logout"
                                    >
                                        {Icon ? <Icon /> : <LogOut />}
                                        <span>{section.label}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    if (section.type === 'link') {
                        return (
                            <SidebarMenuItem key={section.id}>
                                <SidebarMenuButton asChild isActive={isActive(section)} tooltip={{ children: section.label }}>
                                    <Link href={section.path} prefetch={section.status !== 'pending'}>
                                        {Icon && <Icon />}
                                        <span>{section.label}</span>
                                        {section.status === 'pending' && <PendingMark />}
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    const hasActiveItem = section.items.some(isActive);
                    const isOpen = openGroups[section.id] ?? hasActiveItem;

                    return (
                        <Collapsible
                            key={section.id}
                            open={isOpen || hasActiveItem}
                            onOpenChange={(open) => setGroupOpen(section.id, open)}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton isActive={hasActiveItem} tooltip={{ children: section.label }}>
                                        {Icon && <Icon />}
                                        <span>{section.label}</span>
                                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenu className="mt-1 border-l pl-3">
                                        {section.items.map((item) => (
                                            <SidebarMenuItem key={item.id}>
                                                <SidebarMenuButton
                                                    asChild
                                                    size="sm"
                                                    isActive={isActive(item)}
                                                    className={item.status === 'pending' ? 'text-muted-foreground' : undefined}
                                                >
                                                    <Link href={item.path} prefetch={item.status !== 'pending'}>
                                                        <span>{item.label}</span>
                                                        {item.status === 'pending' && <PendingMark />}
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        ))}
                                    </SidebarMenu>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function PendingMark() {
    return (
        <>
            <Clock className="ml-auto size-3 shrink-0 opacity-60" aria-hidden />
            <span className="sr-only">Belum tersedia</span>
        </>
    );
}
