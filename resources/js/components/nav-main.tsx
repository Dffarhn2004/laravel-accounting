import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
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
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    useEffect(() => {
        const stored = window.localStorage.getItem('accounting-navigation-groups');
        if (stored) {
            setOpenGroups(JSON.parse(stored) as Record<string, boolean>);
        }
    }, []);

    const setGroupOpen = (title: string, open: boolean) => {
        const next = { ...openGroups, [title]: open };
        setOpenGroups(next);
        window.localStorage.setItem('accounting-navigation-groups', JSON.stringify(next));
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasItems = Boolean(item.items?.length);
                    const hasActiveItem = item.items?.some((child) => isCurrentUrl(child.href, undefined, true));
                    const isOpen = openGroups[item.title] ?? hasActiveItem ?? false;

                    if (!hasItems) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild isActive={isCurrentUrl(item.href)} tooltip={{ children: item.title }}>
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <Collapsible key={item.title} open={isOpen} onOpenChange={(open) => setGroupOpen(item.title, open)} className="group/collapsible">
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton isActive={hasActiveItem} tooltip={{ children: item.title }}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenu className="mt-1 border-l pl-3">
                                        {item.items?.map((child) => (
                                            <SidebarMenuItem key={child.title}>
                                                <SidebarMenuButton asChild size="sm" isActive={isCurrentUrl(child.href, undefined, true)}>
                                                    <Link href={child.href} prefetch><span>{child.title}</span></Link>
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
