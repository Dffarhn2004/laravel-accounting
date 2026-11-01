import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
};

export type NavStatus = 'partial' | 'pending';

export type ContractItem = {
    id: string;
    label: string;
    path: string;
    status: NavStatus;
};

export type ContractSection =
    | ({ type: 'link' } & ContractItem)
    | { type: 'group'; id: string; label: string; items: ContractItem[] }
    | { type: 'action'; id: string; label: string };
