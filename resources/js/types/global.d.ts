import type { Auth } from '@/types/auth';
import type { ContractSection } from '@/types/navigation';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            navigation: ContractSection[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
