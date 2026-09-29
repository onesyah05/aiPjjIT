import useTheme from '@/hooks/useTheme';
import { Moon, Sun } from 'lucide-react';

export default function ThemeToggle({ variant = 'default', className = '' }) {
    const { isDark, toggle } = useTheme();

    const styles =
        variant === 'inverse'
            ? 'text-brand-200 hover:bg-white/10 hover:text-white'
            : variant === 'onLight'
              ? 'border border-stone-200 bg-white text-stone-700 hover:bg-stone-50'
              : 'border border-stone-200 bg-white text-stone-700 hover:bg-stone-50 dark:border-white/15 dark:bg-white/[0.07] dark:text-brand-100 dark:hover:bg-white/15';

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={isDark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap'}
            aria-pressed={isDark}
            title={isDark ? 'Tema terang' : 'Tema gelap'}
            className={`grid h-10 w-10 place-items-center rounded-lg transition-colors ${styles} ${className}`}
        >
            {isDark ? <Sun size={20} strokeWidth={1.8} aria-hidden="true" /> : <Moon size={20} strokeWidth={1.8} aria-hidden="true" />}
        </button>
    );
}
