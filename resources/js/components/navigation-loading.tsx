import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function NavigationLoading() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const removeBefore = router.on('before', () => setVisible(true));
        const stop = () => {
            setVisible(false);
        };
        const removeFinish = router.on('finish', stop);
        const removeError = router.on('error', stop);

        return () => {
            removeBefore();
            removeFinish();
            removeError();
        };
    }, []);

    if (!visible) return null;

    return <div className="absolute inset-0 z-20 bg-background/95 p-4 md:p-8" aria-live="polite" aria-label="Memuat halaman">
        <div className="mx-auto w-full max-w-[1440px] space-y-6">
            <div className="flex items-start justify-between"><div className="space-y-3"><Skeleton className="h-4 w-32" /><Skeleton className="h-9 w-64" /><Skeleton className="h-4 w-96 max-w-full" /></div><Skeleton className="h-10 w-32" /></div>
            <div className="flex flex-wrap gap-3"><Skeleton className="h-10 w-64" /><Skeleton className="h-10 w-36" /><Skeleton className="h-10 w-36" /></div>
            <div className="space-y-px rounded-xl border p-4"><Skeleton className="h-10 w-full" /><Skeleton className="h-14 w-full" /><Skeleton className="h-14 w-full" /><Skeleton className="h-14 w-full" /><Skeleton className="h-14 w-full" /><Skeleton className="h-14 w-full" /></div>
        </div>
        <span className="sr-only">Memuat halaman baru</span>
    </div>;
}
