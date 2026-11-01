import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export default function ThemeToggle({ compact = false }: { compact?: boolean }) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const next = isDark ? 'light' : 'dark';

    return <Button type="button" variant="ghost" size={compact ? 'icon' : 'sm'} onClick={() => updateAppearance(next)} aria-label={`Gunakan tema ${next === 'dark' ? 'gelap' : 'terang'}`} title={`Gunakan tema ${next === 'dark' ? 'gelap' : 'terang'}`} className={compact ? 'size-9' : 'gap-2'}>
        {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        {!compact && <span>{isDark ? 'Tema terang' : 'Tema gelap'}</span>}
    </Button>;
}
