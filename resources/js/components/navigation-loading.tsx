import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Page-transition skeleton: every page change shows a skeleton, in every layout.
 *
 * "Page change" means any GET navigation that replaces the page content: moving to another menu,
 * but also pagination and filter submits (same path, new data). It appears immediately, stays at
 * least a moment so it never flickers, and the window is scrolled to the top so it is never
 * off-screen.
 *
 * Prefetched menu links (hover prefetch) are covered too: a click served from the prefetch cache
 * is still a page change, so it shows the skeleton like any other.
 *
 * Not covered on purpose, because the page must stay on screen while the user works in it:
 *   - POST/PATCH/DELETE (saving rows, posting an invoice): the button shows its own busy state
 *   - the background prefetch request itself, partial reloads and polling
 */
const MIN_VISIBLE_MS = 250;
/** Never leave the skeleton up forever if a visit ends without a finish event. */
const SAFETY_MS = 20000;

type Variant = 'auth' | 'dashboard' | 'form' | 'settings' | 'table';

const AUTH_PATHS = /^\/(login|register|forgot-password|reset-password|two-factor-challenge|email\/verify|user\/confirm-password)/;

/** Pick a skeleton shaped like the destination so the real page does not jump in. */
export function variantFor(pathname: string): Variant {
    if (AUTH_PATHS.test(pathname)) return 'auth';
    if (pathname === '/dashboard') return 'dashboard';
    if (pathname.startsWith('/settings/')) return 'settings';
    if (pathname.endsWith('/create') || pathname.startsWith('/inputan/') || pathname === '/system/template') return 'form';

    return 'table';
}

type VisitLike = { method: string; async?: boolean; prefetch?: boolean; only?: string[]; except?: string[] };

/** A visit that replaces the page: a real GET (cached or not), not a background prefetch or partial request. */
export function replacesPage(visit: VisitLike): boolean {
    if (visit.method.toLowerCase() !== 'get' || visit.async || visit.prefetch) return false;

    return (visit.only?.length ?? 0) === 0 && (visit.except?.length ?? 0) === 0;
}

export function NavigationLoading() {
    const [variant, setVariant] = useState<Variant | null>(null);
    const timer = useRef<number | undefined>(undefined);
    const shownAt = useRef(0);

    useEffect(() => {
        const hide = () => {
            if (!shownAt.current) return;

            window.clearTimeout(timer.current);
            const wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt.current));
            timer.current = window.setTimeout(() => {
                shownAt.current = 0;
                setVariant(null);
            }, wait);
        };

        // `before` fires for every visit, including ones answered from the prefetch cache
        // (which may skip `start`), so the skeleton appears the moment the click happens.
        const offBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (!replacesPage(visit)) return;

            window.clearTimeout(timer.current);
            window.scrollTo({ top: 0 });
            shownAt.current = Date.now();
            setVariant(variantFor(visit.url.pathname));
            timer.current = window.setTimeout(hide, SAFETY_MS);
        });
        const offs = [offBefore, router.on('finish', hide), router.on('cancel', hide), router.on('navigate', hide), router.on('error', hide), router.on('networkError', hide), router.on('httpException', hide)];

        return () => {
            offs.forEach((off) => off());
            window.clearTimeout(timer.current);
        };
    }, []);

    if (!variant) return null;

    return (
        <div className="absolute inset-0 z-20 bg-background p-4 md:p-8" role="status" aria-live="polite" aria-busy="true">
            <span className="sr-only">Memuat halaman…</span>
            <div className="motion-reduce:[&_*]:animate-none">{skeletons[variant]}</div>
        </div>
    );
}

const heading = (
    <div className="space-y-3"><Skeleton className="h-4 w-28" /><Skeleton className="h-9 w-72 max-w-full" /><Skeleton className="h-4 w-96 max-w-full" /></div>
);

const skeletons: Record<Variant, React.ReactNode> = {
    auth: (
        <div className="mx-auto flex min-h-[70svh] w-full max-w-sm flex-col items-center justify-center gap-8">
            <Skeleton className="size-9 rounded-md" />
            <div className="w-full space-y-3 text-center"><Skeleton className="mx-auto h-6 w-48" /><Skeleton className="mx-auto h-4 w-64 max-w-full" /></div>
            <div className="w-full space-y-4"><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /><Skeleton className="h-10 w-full" /></div>
        </div>
    ),
    dashboard: (
        <div className="mx-auto w-full max-w-[1500px] space-y-6">
            {heading}
            <Skeleton className="h-20 w-full rounded-xl" />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} className="h-28 rounded-xl" />)}</div>
            <Skeleton className="h-48 w-full rounded-xl" />
            <div className="grid gap-6 xl:grid-cols-2"><Skeleton className="h-64 rounded-xl" /><Skeleton className="h-64 rounded-xl" /></div>
        </div>
    ),
    form: (
        <div className="mx-auto w-full max-w-[1200px] space-y-6">
            {heading}
            <div className="grid gap-3 rounded-xl border p-5 sm:grid-cols-2 lg:grid-cols-3">{Array.from({ length: 6 }, (_, i) => <div key={i} className="space-y-2"><Skeleton className="h-4 w-24" /><Skeleton className="h-10 w-full" /></div>)}</div>
            <div className="space-y-2 rounded-xl border p-5"><Skeleton className="h-10 w-full" /><Skeleton className="h-12 w-full" /><Skeleton className="h-12 w-full" /><Skeleton className="h-12 w-full" /></div>
        </div>
    ),
    settings: (
        <div className="mx-auto w-full max-w-3xl space-y-6">
            {heading}
            {Array.from({ length: 3 }, (_, i) => <div key={i} className="space-y-2"><Skeleton className="h-4 w-32" /><Skeleton className="h-10 w-full" /></div>)}
            <Skeleton className="h-10 w-32" />
        </div>
    ),
    table: (
        <div className="mx-auto w-full max-w-[1600px] space-y-6">
            <div className="flex items-start justify-between gap-4">{heading}<Skeleton className="h-10 w-32" /></div>
            <div className="flex flex-wrap gap-3"><Skeleton className="h-10 w-64" /><Skeleton className="h-10 w-36" /><Skeleton className="h-10 w-36" /><Skeleton className="h-10 w-24" /></div>
            <div className="space-y-px rounded-xl border p-4"><Skeleton className="h-10 w-full" />{Array.from({ length: 7 }, (_, i) => <Skeleton key={i} className="h-12 w-full" />)}</div>
        </div>
    ),
};
